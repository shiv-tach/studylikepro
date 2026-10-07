<?php

namespace App\Models;

use App\Enums\BookingFeeDiscountType;
use Carbon\CarbonInterface;
use Database\Factories\BookingFeePromotionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-run special offer that discounts the student booking fee while its
 * window is open. `discount_value` is a percentage for `percent` offers and a
 * minor-unit amount for `fixed` ones; a `waive` offer ignores it.
 */
class BookingFeePromotion extends Model
{
    /** @use HasFactory<BookingFeePromotionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => BookingFeeDiscountType::class,
            'discount_value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Offers that are switched on and inside their window at the given instant.
     */
    public function scopeRunningAt(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at = $at ?? now();

        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $at));
    }

    public function isRunning(?CarbonInterface $at = null): bool
    {
        $at = $at ?? now();

        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($at))
            && ($this->ends_at === null || $this->ends_at->greaterThanOrEqualTo($at));
    }

    /**
     * How much of the given booking fee this offer takes off, in minor units.
     */
    public function discountFor(int $feeMinor): int
    {
        return min($feeMinor, $this->discount_type->discountFor($feeMinor, $this->discount_value));
    }

    /**
     * Short description for the admin console, e.g. "50% off the fee".
     */
    public function describeDiscount(): string
    {
        return match ($this->discount_type) {
            BookingFeeDiscountType::Waive => 'Booking fee waived',
            BookingFeeDiscountType::Percent => $this->discount_value.'% off the booking fee',
            BookingFeeDiscountType::Fixed => platform_settings()->formatMinor($this->discount_value).' off the booking fee',
        };
    }
}
