<?php

namespace App\Services\Payments;

use App\Enums\BookingStatus;
use App\Enums\EarningStatus;
use App\Enums\PurchaseStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ServicePurchase;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The teacher earnings ledger: one row per paid lesson holding the payout, the
 * amount reversed by refunds, and where the money sits (pending → available →
 * paid out).
 */
class EarningsService
{
    /**
     * Record the payout a captured payment owes the teacher. Idempotent: a
     * repeated capture for the same booking updates the existing row.
     */
    public function recordForBooking(Booking $booking, ?Payment $payment = null): TeacherEarning
    {
        // A payment captured after the lesson was delivered is already payable.
        $delivered = $booking->status === BookingStatus::Completed;

        $availableAt = $delivered
            ? now()
            : ($booking->ends_at
                ? $booking->ends_at->copy()->addMinutes((int) config('studylikepro.booking.auto_complete_grace_minutes'))
                : now());

        return TeacherEarning::query()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'teacher_profile_id' => $booking->teacher_profile_id,
                'payment_id' => $payment?->id,
                'amount_minor' => $booking->teacher_payout_minor,
                'currency' => $booking->currency,
                'status' => $delivered ? EarningStatus::Eligible : EarningStatus::Pending,
                'available_at' => $availableAt,
            ],
        );
    }

    /**
     * The lesson was delivered, so the payout stops being provisional.
     */
    public function markEligibleForBooking(Booking $booking): ?TeacherEarning
    {
        $earning = $booking->earning;

        if ($earning === null || $earning->status === EarningStatus::Reversed) {
            return $earning;
        }

        if ($earning->status === EarningStatus::Pending) {
            $earning->forceFill([
                'status' => EarningStatus::Eligible,
                'available_at' => now(),
            ])->save();
        }

        return $earning;
    }

    /**
     * Reverse the teacher's share of a refunded payment. A full refund marks the
     * row reversed; a partial refund just reduces what the row is worth.
     */
    public function reverseForRefund(Booking $booking, int $percent): ?TeacherEarning
    {
        $earning = $booking->earning;

        if ($earning === null || $percent <= 0) {
            return $earning;
        }

        $reversed = (int) round($earning->amount_minor * min(100, $percent) / 100);

        $earning->forceFill([
            'reversed_minor' => min($earning->amount_minor, $earning->reversed_minor + $reversed),
        ]);

        if ($earning->reversed_minor >= $earning->amount_minor) {
            $earning->status = EarningStatus::Reversed;
        }

        $earning->save();

        return $earning;
    }

    /**
     * Record the payout a captured service purchase owes the teacher. The row
     * stays pending until the service is delivered (plan completed, paper
     * returned), mirroring booking earnings. Idempotent.
     */
    public function recordForPurchase(ServicePurchase $purchase, ?Payment $payment = null): TeacherEarning
    {
        $delivered = $purchase->status->isFinal() && $purchase->status !== PurchaseStatus::Cancelled
            && $purchase->status !== PurchaseStatus::Expired;

        return TeacherEarning::query()->updateOrCreate(
            ['service_purchase_id' => $purchase->id],
            [
                'teacher_profile_id' => $purchase->teacher_profile_id,
                'payment_id' => $payment?->id,
                'amount_minor' => $purchase->teacher_payout_minor,
                'currency' => $purchase->currency,
                'status' => $delivered ? EarningStatus::Eligible : EarningStatus::Pending,
                'available_at' => $delivered ? now() : null,
            ],
        );
    }

    /**
     * The service was delivered, so the purchase payout stops being provisional.
     */
    public function markEligibleForPurchase(ServicePurchase $purchase): ?TeacherEarning
    {
        $earning = $purchase->earning;

        if ($earning === null || $earning->status === EarningStatus::Reversed) {
            return $earning;
        }

        if ($earning->status === EarningStatus::Pending) {
            $earning->forceFill([
                'status' => EarningStatus::Eligible,
                'available_at' => now(),
            ])->save();
        }

        return $earning;
    }

    /**
     * Reverse the teacher's share of a refunded purchase. A full refund marks
     * the row reversed; a partial refund just reduces what the row is worth.
     */
    public function reverseForPurchase(ServicePurchase $purchase, int $percent): ?TeacherEarning
    {
        $earning = $purchase->earning;

        if ($earning === null || $percent <= 0) {
            return $earning;
        }

        $reversed = (int) round($earning->amount_minor * min(100, $percent) / 100);

        $earning->forceFill([
            'reversed_minor' => min($earning->amount_minor, $earning->reversed_minor + $reversed),
        ]);

        if ($earning->reversed_minor >= $earning->amount_minor) {
            $earning->status = EarningStatus::Reversed;
        }

        $earning->save();

        return $earning;
    }

    /**
     * @return array{available: int, pending: int, lifetime: int, reversed: int, paid: int}
     */
    public function totalsFor(TeacherProfile $teacher): array
    {
        $rows = $teacher->earnings()->get();

        return [
            'available' => (int) $rows->where('status', EarningStatus::Eligible)->sum(fn (TeacherEarning $row) => $row->netMinor()),
            'pending' => (int) $rows->where('status', EarningStatus::Pending)->sum(fn (TeacherEarning $row) => $row->netMinor()),
            'paid' => (int) $rows->where('status', EarningStatus::Paid)->sum(fn (TeacherEarning $row) => $row->netMinor()),
            'lifetime' => (int) $rows->sum(fn (TeacherEarning $row) => $row->amount_minor),
            'reversed' => (int) $rows->sum(fn (TeacherEarning $row) => $row->reversed_minor),
        ];
    }

    /**
     * How many ledger rows sit in each state, plus the total.
     *
     * One grouped query, read from the teacher's whole ledger rather than the
     * filtered page, so a status filter never makes the other tabs read 0. Every
     * state is present even when it is empty: "no reverses" is information.
     *
     * @return array<string, int>
     */
    public function statusCountsFor(TeacherProfile $teacher): array
    {
        $grouped = $teacher->earnings()
            // The relation already sorts by created_at; a group by needs that
            // order cleared or MySQL's ONLY_FULL_GROUP_BY rejects the query.
            ->reorder()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $counts = ['all' => 0];

        foreach (EarningStatus::cases() as $case) {
            $counts[$case->value] = (int) ($grouped[$case->value] ?? 0);
            $counts['all'] += $counts[$case->value];
        }

        return $counts;
    }

    /**
     * What the teacher earned in each of the last `$months` calendar months,
     * net of refunds, oldest first.
     *
     * Grouped in PHP on purpose: the test suite runs on SQLite while production
     * runs MySQL, and a `strftime`/`DATE_FORMAT` expression would make the two
     * disagree. Empty months are included so the trend never draws a gap.
     *
     * @return list<array{key: string, label: string, minor: int, count: int}>
     */
    public function monthlyFor(TeacherProfile $teacher, int $months = 6): array
    {
        $months = max(1, $months);
        $start = now()->startOfMonth()->subMonths($months - 1);

        $grouped = $teacher->earnings()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'amount_minor', 'reversed_minor'])
            ->groupBy(fn (TeacherEarning $row) => $row->created_at->format('Y-m'));

        return collect(range(0, $months - 1))
            ->map(function (int $offset) use ($start, $grouped): array {
                $month = $start->copy()->addMonths($offset);
                $rows = $grouped->get($month->format('Y-m'));

                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->format('M'),
                    'minor' => $rows === null
                        ? 0
                        : (int) $rows->sum(fn (TeacherEarning $row) => max(0, $row->amount_minor - $row->reversed_minor)),
                    'count' => $rows?->count() ?? 0,
                ];
            })
            ->all();
    }

    /**
     * Eligible rows that have not been batched into a payout yet.
     *
     * @return Collection<int, TeacherEarning>
     */
    public function unbatchedFor(TeacherProfile $teacher): Collection
    {
        return $teacher->earnings()
            ->eligible()
            ->whereNull('payout_id')
            ->whereColumn('amount_minor', '>', DB::raw('reversed_minor'))
            ->orderBy('available_at')
            ->get();
    }
}
