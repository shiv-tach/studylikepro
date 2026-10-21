<?php

namespace App\Services;

use App\Enums\BookingFeeType;
use App\Models\BookingFeePromotion;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Prices the student-facing booking fee: the admin-configured amount (a fixed
 * sum or a percentage of the price being sold), reduced by whichever running
 * special offer is most generous when the slot is booked.
 */
class BookingFeeService
{
    public function __construct(
        private readonly PlatformSettings $settings,
    ) {}

    /**
     * The fee a booking made at the given instant carries.
     *
     * The percentage type needs the price being sold (`$priceMinor`); callers
     * that only preview the configuration may omit it, in which case no fee is
     * quoted until a price is known.
     *
     * @return array{booking_fee_minor: int, discount_minor: int, net_minor: int, promotion: BookingFeePromotion|null, type: BookingFeeType, percent: float}
     */
    public function quote(?CarbonInterface $at = null, ?int $priceMinor = null): array
    {
        $at = $at !== null ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        $type = BookingFeeType::tryFrom($this->settings->string('booking_fee_type')) ?? BookingFeeType::Fixed;
        $percent = min(100, max(0, $this->settings->float('booking_fee_percent')));
        $fee = $type->feeFor($priceMinor, max(0, $this->settings->int('booking_fee_minor')), $percent);

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
            'type' => $type,
            'percent' => $percent,
        ];
    }

    /**
     * What the student pays for the fee alone, after any running offer.
     */
    public function netMinor(?CarbonInterface $at = null, ?int $priceMinor = null): int
    {
        return $this->quote($at, $priceMinor)['net_minor'];
    }
}
