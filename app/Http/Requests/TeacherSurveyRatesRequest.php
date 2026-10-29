<?php

namespace App\Http\Requests;

use App\Support\PendingTeacherSignup;
use App\Support\TeacherSurvey;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Survey question 7: the hourly fee for a lesson plus a price for every service
 * the teacher picked in question 4 (lessons are priced by the hourly rate).
 *
 * The fields are dynamic, so the rules are built from that earlier answer: a
 * service the teacher did not pick cannot be priced here.
 */
class TeacherSurveyRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    public function rules(): array
    {
        $rules = [
            'hourly_rate' => ['required', 'numeric', 'min:100', 'max:100000'],
            'service_rates' => ['nullable', 'array'],
        ];

        foreach (TeacherSurvey::pricedServiceOptions(PendingTeacherSignup::step(TeacherSurvey::STEP_SERVICES)) as $option) {
            $rules['service_rates.'.$option['value']] = ['required', 'numeric', 'min:100', 'max:100000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'hourly_rate.required' => __('Set an hourly fee for your lessons.'),
            'hourly_rate.min' => __('The hourly fee has to be at least :min.', ['min' => 100]),
            'service_rates.*.required' => __('Set a price for every service you picked.'),
            'service_rates.*.min' => __('Each price has to be at least :min.', ['min' => 100]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'hourly_rate' => __('hourly fee'),
            'service_rates.*' => __('service price'),
        ];
    }
}
