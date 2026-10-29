<?php

namespace App\Http\Requests;

use App\Support\TeacherSurvey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Survey question 1: "Are you tutoring for O/L, A/L or both?"
 *
 * The invite survey runs before the account exists, so a guest is allowed.
 */
class TeacherSurveyLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    public function rules(): array
    {
        return [
            'level' => ['required', 'string', Rule::in(['ol', 'al', TeacherSurvey::LEVEL_BOTH])],
        ];
    }
}
