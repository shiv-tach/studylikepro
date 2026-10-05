<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\TeacherProfile;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports for the ops team: bookings, payments, refunds, payouts and teacher
 * performance, over any date range. Rows are streamed so a big export cannot
 * exhaust memory.
 */
class AdminCsvExporter
{
    /** @var list<string> */
    public const TYPES = ['bookings', 'payments', 'refunds', 'payouts', 'teachers'];

    private string $timezone = 'Asia/Kolkata';

    public function download(string $type, CarbonInterface $from, CarbonInterface $to): StreamedResponse
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $this->timezone = config('studylikepro.default_display_timezone');

        $filename = sprintf('studylikepro-%s-%s-to-%s.csv', $type, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($type, $from, $to) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $this->headers($type));

            foreach ($this->rows($type, $from, $to) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return list<string>
     */
    private function headers(string $type): array
    {
        return match ($type) {
            'bookings' => ['Reference', 'Status', 'Starts at', 'Student', 'Teacher', 'Subject', 'Topic', 'Price', 'Platform fee', 'Teacher payout', 'Currency', 'Payment status', 'Created at'],
            'payments' => ['Payment', 'Gateway', 'Order id', 'Status', 'Amount', 'Refunded', 'Currency', 'Method', 'Captured at', 'Student', 'Booking'],
            'refunds' => ['Refund', 'Booking', 'Payment', 'Amount', 'Percent', 'Initiated by', 'Reason', 'Status', 'Created at'],
            'payouts' => ['Reference', 'Teacher', 'Lessons', 'Amount', 'Currency', 'Status', 'Paid at', 'Notes'],
            'teachers' => ['Teacher', 'Email', 'Verification', 'Rating', 'Reviews', 'Lessons completed', 'Gross billed', 'Commission', 'Net to teacher', 'Available balance', 'Pending balance'],
        };
    }

    /**
     * @return Generator<int, list<string|int|float|null>>
     */
    private function rows(string $type, CarbonInterface $from, CarbonInterface $to): Generator
    {
        return match ($type) {
            'bookings' => $this->bookings($from, $to),
            'payments' => $this->payments($from, $to),
            'refunds' => $this->refunds($from, $to),
            'payouts' => $this->payouts($from, $to),
            'teachers' => $this->teachers($from, $to),
        };
    }

    private function bookings(CarbonInterface $from, CarbonInterface $to): Generator
    {
        $query = Booking::query()
            ->with(['student', 'teacherProfile.user', 'subject', 'topic', 'payment'])
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at');

        foreach ($query->lazy(200) as $booking) {
            yield [
                $booking->id,
                $booking->status->label(),
                $this->date($booking->starts_at),
                $booking->student->name,
                $booking->teacherProfile->user->name,
                $booking->subject?->name,
                $booking->topic?->name,
                $this->money($booking->price_minor),
                $this->money($booking->platform_fee_minor),
                $this->money($booking->teacher_payout_minor),
                $booking->currency,
                $booking->payment?->status->label(),
                $this->date($booking->created_at),
            ];
        }
    }

    private function payments(CarbonInterface $from, CarbonInterface $to): Generator
    {
        $query = Payment::query()
            ->with(['student', 'booking'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id');

        foreach ($query->lazy(200) as $payment) {
            yield [
                $payment->id,
                $payment->gateway,
                $payment->gateway_order_id,
                $payment->status->label(),
                $this->money($payment->amount_minor),
                $this->money($payment->refundedMinor()),
                $payment->currency,
                $payment->method,
                $this->date($payment->captured_at),
                $payment->student->name,
                $payment->booking_id,
            ];
        }
    }

    private function refunds(CarbonInterface $from, CarbonInterface $to): Generator
    {
        $query = Refund::query()
            ->with('booking')
            ->where('status', RefundStatus::Processed->value)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id');

        foreach ($query->lazy(200) as $refund) {
            yield [
                $refund->id,
                $refund->booking_id,
                $refund->payment_id,
                $this->money($refund->amount_minor),
                $refund->percent,
                $refund->initiated_by,
                $refund->reason,
                $refund->status->label(),
                $this->date($refund->created_at),
            ];
        }
    }

    private function payouts(CarbonInterface $from, CarbonInterface $to): Generator
    {
        $query = Payout::query()
            ->with('teacherProfile.user')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id');

        foreach ($query->lazy(200) as $payout) {
            yield [
                $payout->reference,
                $payout->teacherProfile?->user?->name,
                $payout->lessons_count,
                $this->money($payout->amount_minor),
                $payout->currency,
                $payout->status->label(),
                $this->date($payout->paid_at),
                $payout->notes,
            ];
        }
    }

    private function teachers(CarbonInterface $from, CarbonInterface $to): Generator
    {
        $query = TeacherProfile::query()->with('user')->orderBy('id');

        foreach ($query->lazy(100) as $teacher) {
            $completed = Booking::query()
                ->where('teacher_profile_id', $teacher->id)
                ->where('status', BookingStatus::Completed->value)
                ->whereBetween('starts_at', [$from, $to]);

            yield [
                $teacher->user->name,
                $teacher->user->email,
                $teacher->verification_status->label(),
                $teacher->rating_avg === null ? null : (float) $teacher->rating_avg,
                $teacher->rating_count,
                (clone $completed)->count(),
                $this->money((int) (clone $completed)->sum('price_minor')),
                $this->money((int) (clone $completed)->sum('platform_fee_minor')),
                $this->money((int) (clone $completed)->sum('teacher_payout_minor')),
                $this->money($teacher->availableBalanceMinor()),
                $this->money($teacher->pendingBalanceMinor()),
            ];
        }
    }

    private function money(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    private function date(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone($this->timezone)->format('Y-m-d H:i');
    }

    /**
     * The date range a request asked for, defaulting to the last 30 days.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(array $filters): array
    {
        $from = filled($filters['from'] ?? null)
            ? Carbon::parse($filters['from'])->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = filled($filters['to'] ?? null)
            ? Carbon::parse($filters['to'])->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
