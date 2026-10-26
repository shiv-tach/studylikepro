<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPaymentSlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slip' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:'.max(1, (int) config('studylikepro.payments.slip_max_kb')),
            ],
            'bank_account_id' => [
                'nullable',
                'integer',
                Rule::exists('bank_accounts', 'id')->where('is_active', true),
            ],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slip.required' => 'Upload the payment slip or a photo of the deposit proof.',
            'slip.mimes' => 'The slip must be an image (JPG, PNG or WEBP) or a PDF.',
            'slip.max' => 'That slip is too large — upload a file under :max KB.',
            'bank_account_id.exists' => 'Pick one of the bank accounts shown on this page.',
        ];
    }
}
