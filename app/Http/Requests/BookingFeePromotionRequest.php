<?php

namespace App\Http\Requests;

use App\Enums\BookingFeeDiscountType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A special offer on the student booking fee. `discount_percent` and
 * `discount_amount` are the admin-facing views of the stored `discount_value`;
 * only the one matching the chosen type is used.
 */
class BookingFeePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $percent = fn () => $this->input('discount_type') === BookingFeeDiscountType::Percent->value;
        $fixed = fn () => $this->input('discount_type') === BookingFeeDiscountType::Fixed->value;

        return [
            'name' => ['required', 'string', 'max:120'],
            'discount_type' => ['required', 'string', Rule::in(array_column(BookingFeeDiscountType::cases(), 'value'))],
            'discount_percent' => ['nullable', 'integer', 'min:1', 'max:100', Rule::requiredIf($percent)],
            'discount_amount' => ['nullable', 'numeric', 'min:0.01', 'max:100000', Rule::requiredIf($fixed)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The offer columns, with the discount normalised to minor units.
     *
     * @return array<string, mixed>
     */
    public function offerAttributes(): array
    {
        $type = BookingFeeDiscountType::from($this->validated('discount_type'));

        return [
            'name' => $this->validated('name'),
            'discount_type' => $type->value,
            'discount_value' => match ($type) {
                BookingFeeDiscountType::Waive => 0,
                BookingFeeDiscountType::Percent => (int) $this->validated('discount_percent'),
                BookingFeeDiscountType::Fixed => (int) round(((float) $this->validated('discount_amount')) * 100),
            },
            'starts_at' => $this->windowDate('starts_at'),
            'ends_at' => $this->windowDate('ends_at'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * Offer windows are typed in the marketplace timezone and stored in UTC.
     */
    private function windowDate(string $key): ?CarbonImmutable
    {
        $value = $this->validated($key);

        return $value === null
            ? null
            : CarbonImmutable::parse($value, (string) config('studylikepro.default_display_timezone'))->utc();
    }
}
