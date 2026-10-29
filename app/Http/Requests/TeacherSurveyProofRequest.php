<?php

namespace App\Http\Requests;

use App\Support\PendingTeacherSignup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Survey question 5: prove you are a government teacher (optional).
 *
 * The answer is always submitted — "no" is a real answer that moves the flow on
 * — and the file is only required when the guest says they are one and nothing
 * has been uploaded yet (a guest who comes back to this screen keeps the file
 * they already attached).
 */
class TeacherSurveyProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    /**
     * The "skip" button submits `claims=0`; the upload button submits the file.
     */
    public function rules(): array
    {
        return [
            'claims' => ['required', 'boolean'],
            'document' => [
                Rule::requiredIf(fn () => filter_var($this->input('claims'), FILTER_VALIDATE_BOOLEAN)
                    && PendingTeacherSignup::proofPath() === null),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'claims.required' => __('Tell us whether you are a government teacher.'),
            'document.required_if' => __('Attach the proof that you are a government teacher.'),
            'document.mimes' => __('The proof must be a PDF, JPG, PNG or WebP file.'),
            'document.max' => __('The proof must be 5 MB or smaller.'),
        ];
    }
}
