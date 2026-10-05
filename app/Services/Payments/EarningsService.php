<?php

namespace App\Services\Payments;

use App\Enums\BookingStatus;
use App\Enums\EarningStatus;
use App\Models\Booking;
use App\Models\Payment;
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
