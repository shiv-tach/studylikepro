<?php

namespace App\Http\Requests;

use App\Enums\ServiceType;
use App\Support\TeacherSurvey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Survey question 4: the services the teacher wants to offer, each one priced
 * on the spot. Stored as intent on the profile (pre-selected in the services
 * console) plus the price the teacher set for it.
 *
 * The price fields follow the picks, so the rules are built from the submitted
 * list: a service that was not ticked cannot be priced here. The hourly fee is
 * the price of the lessons themselves, so it is only asked from teachers who
 * actually offer lessons.
 */
class TeacherSurveyServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    public function rules(): array
    {
        $services = array_values(array_map('strval', (array) $this->input('services', [])));

        $rules = [
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['string', 'distinct', Rule::enum(ServiceType::class)],
            'hourly_rate' => [
                Rule::requiredIf(in_array(ServiceType::Lesson->value, $services, true)),
                'nullable',
                'numeric',
                'min:100',
                'max:100000',
            ],
            'service_rates' => ['nullable', 'array'],
        ];

        foreach (TeacherSurvey::pricedServiceOptions($services) as $option) {
            $rules['service_rates.'.$option['value']] = ['required', 'numeric', 'min:100', 'max:100000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'services.required' => __('Pick at least one service you would like to offer.'),
            'services.min' => __('Pick at least one service you would like to offer.'),
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
