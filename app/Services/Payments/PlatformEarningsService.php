<?php

namespace App\Services\Payments;

use App\Enums\RefundStatus;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads the platform's side of the money: what was collected, the commission and
 * booking fees the platform keeps, what was refunded and what the teachers are
 * owed. Shared by the admin earnings console and its CSV export so both report
 * the same figures.
 */
class PlatformEarningsService
{
    /**
     * Settled payments in the range, eager loaded with everything a breakdown needs.
     *
     * @return Builder<Payment>
     */
    public function settled(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Payment::query()
            ->settled()
            ->whereBetween('captured_at', [$from, $to])
            ->with([
                'student:id,name',
                'booking:id,teacher_profile_id,platform_fee_minor,teacher_payout_minor,booking_fee_minor,booking_fee_discount_minor',
                'booking.teacherProfile:id,user_id',
                'booking.teacherProfile.user:id,name',
                // The label columns (service_type/lesson/subject/offering) are
                // selected too — scopeLabel() falls back to the service type when
                // nothing more specific is set.
                'purchase:id,teacher_profile_id,service_type,subject_id,lesson_id,offering_id,platform_fee_minor,teacher_payout_minor,booking_fee_minor,booking_fee_discount_minor',
                'purchase.teacherProfile:id,user_id',
                'purchase.teacherProfile.user:id,name',
                'purchase.lesson:id,name',
                'purchase.subject:id,name',
                'purchase.offering:id,title',
                'earning:id,payment_id,reversed_minor',
                'refunds:id,payment_id,status,amount_minor',
            ]);
    }

    /**
     * The platform's slice of one collected payment.
     *
     * Net platform earnings are what is left of a collection once refunds are
     * returned and the teacher's (unreversed) payout is set aside, so a full
     * refund nets to zero: collected − refunded − teacher payout.
     *
     * @return array{source: string, collected: int, commission: int, booking_fee: int, refunded: int, teacher: int, net: int}
     */
    public function breakdown(Payment $payment): array
    {
        $booking = $payment->booking;
        $purchase = $payment->purchase;

        $commission = (int) ($booking?->platform_fee_minor ?? $purchase?->platform_fee_minor ?? 0);
        $bookingFee = (int) ($booking?->netBookingFeeMinor() ?? $purchase?->netBookingFeeMinor() ?? 0);
        $teacherPayout = (int) ($booking?->teacher_payout_minor ?? $purchase?->teacher_payout_minor ?? 0);
        $reversed = (int) ($payment->earning?->reversed_minor ?? 0);
        $teacher = max(0, $teacherPayout - $reversed);

        $refunded = (int) $payment->refunds
            ->where('status', RefundStatus::Processed)
            ->sum('amount_minor');

        $collected = (int) $payment->amount_minor;

        return [
            'source' => $purchase !== null ? 'services' : 'lessons',
            'collected' => $collected,
            'commission' => $commission,
            'booking_fee' => $bookingFee,
            'refunded' => $refunded,
            'teacher' => $teacher,
            'net' => $collected - $refunded - $teacher,
        ];
    }
}
