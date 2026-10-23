<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The default hourly rate on /teacher/availability. Bounds mirror
 * TeacherProfileRequest so the same figure cannot be saved on one screen and
 * rejected on the other.
 */
class AvailabilityPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hourly_rate' => ['required', 'numeric', 'min:100', 'max:100000'],
        ];
    }
}
