<?php

namespace App\Services;

use App\Models\BookingFeePromotion;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Prices the student-facing booking fee: the admin-configured amount, reduced
 * by whichever running special offer is most generous when the slot is booked.
 */
class BookingFeeService
{
    public function __construct(
        private readonly PlatformSettings $settings,
    ) {}

    /**
     * The fee a booking made at the given instant carries.
     *
     * @return array{booking_fee_minor: int, discount_minor: int, net_minor: int, promotion: BookingFeePromotion|null}
     */
    public function quote(?CarbonInterface $at = null): array
    {
        $at = $at !== null ? CarbonImmutable::instance($at) : CarbonImmutable::now();
        $fee = max(0, $this->settings->int('booking_fee_minor'));

        $promotion = null;
        $discount = 0;

        if ($fee > 0) {
            $best = BookingFeePromotion::query()
                ->runningAt($at)
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (BookingFeePromotion $offer) => [
                    'promotion' => $offer,
                    'discount_minor' => $offer->discountFor($fee),
                ])
                ->filter(fn (array $offer) => $offer['discount_minor'] > 0)
                ->sortByDesc('discount_minor')
                ->first();

            if ($best !== null) {
                $promotion = $best['promotion'];
                $discount = $best['discount_minor'];
            }
        }

        return [
            'booking_fee_minor' => $fee,
            'discount_minor' => $discount,
            'net_minor' => $fee - $discount,
            'promotion' => $promotion,
        ];
    }

    /**
     * What the student pays for the fee alone, after any running offer.
     */
    public function netMinor(?CarbonInterface $at = null): int
    {
        return $this->quote($at)['net_minor'];
    }
}
