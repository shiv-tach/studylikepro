<?php

namespace App\Http\Requests;

use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Survey question 4: the services the teacher wants to offer. Stored as intent
 * on the profile (pre-selected in the services console), not as offerings.
 */
class TeacherSurveyServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    public function rules(): array
    {
        return [
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['string', 'distinct', Rule::enum(ServiceType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'services.required' => __('Pick at least one service you would like to offer.'),
            'services.min' => __('Pick at least one service you would like to offer.'),
        ];
    }
}
