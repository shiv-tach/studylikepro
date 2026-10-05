<?php

namespace App\Http\Requests;

use App\Models\Dispute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportConversationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(array_keys(Dispute::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
