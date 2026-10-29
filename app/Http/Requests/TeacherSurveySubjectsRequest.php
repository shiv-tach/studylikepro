<?php

namespace App\Http\Requests;

use App\Support\PendingTeacherSignup;
use App\Support\SubjectCatalog;
use App\Support\TeacherSurvey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Survey question 2: the subjects that belong to the level answered first.
 *
 * The allowed set is derived from the stored level answer, so a tampered form
 * cannot pick an A/L subject after choosing O/L (the picker never offers it).
 */
class TeacherSurveySubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    public function rules(): array
    {
        $level = PendingTeacherSignup::step(TeacherSurvey::STEP_LEVEL)['value'] ?? null;
        $allowed = SubjectCatalog::subjectIdsForLevels(TeacherSurvey::allowedLevelKeys($level));

        return [
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*' => ['integer', 'distinct', Rule::in($allowed)],
        ];
    }

    public function messages(): array
    {
        return [
            'subjects.required' => __('Choose at least one subject you teach.'),
            'subjects.min' => __('Choose at least one subject you teach.'),
            'subjects.*.in' => __('That subject does not belong to the level you picked.'),
        ];
    }
}
