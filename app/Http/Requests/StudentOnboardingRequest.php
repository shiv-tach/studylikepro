<?php

namespace App\Http\Requests;

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Services\CatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The student onboarding wizard in one submit: level, grade, the O/L basket
 * subjects of that grade and the learning language.
 */
class StudentOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'level_id' => ['required', 'integer', Rule::exists('education_levels', 'id')->where('is_active', true)],
            'grade_id' => ['required', 'integer', Rule::exists('grades', 'id')->where('is_active', true)],
            'learning_language' => ['required', 'string', Rule::in(config('studylikepro.student_learning_languages'))],
            // Keyed by basket: basket_subjects[category_1] = subject id.
            'basket_subjects' => ['array'],
            'basket_subjects.*' => ['integer'],
        ];
    }

    /**
     * The grade has to sit inside the chosen level, and a grade that picks one
     * subject per O/L basket has to have an answer for every basket.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $level = $this->targetLevel();
            $grade = $this->targetGrade();

            if ($level === null || $grade === null) {
                return; // the base rules already reported the missing pieces
            }

            if ($grade->education_level_id !== $level->id) {
                $validator->errors()->add('grade_id', __('Choose a grade from :level.', ['level' => $level->name]));

                return;
            }

            $this->validateBasketSubjects($validator, $grade);
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'level_id' => __('education level'),
            'grade_id' => __('grade'),
            'learning_language' => __('learning language'),
            'basket_subjects' => __('basket subjects'),
        ];
    }

    private function targetLevel(): ?EducationLevel
    {
        return EducationLevel::query()->find($this->input('level_id'));
    }

    private function targetGrade(): ?Grade
    {
        return Grade::query()->find($this->input('grade_id'));
    }

    /**
     * Every basket of the grade needs exactly one of its own subjects; a grade
     * without baskets must not send any.
     */
    private function validateBasketSubjects(Validator $validator, Grade $grade): void
    {
        $groups = app(CatalogService::class)->basketGroupsForGrade($grade);
        $submitted = $this->submittedBaskets();

        if ($groups->isEmpty()) {
            if ($submitted !== []) {
                $validator->errors()->add('basket_subjects', __('This grade does not choose optional basket subjects.'));
            }

            return;
        }

        foreach ($groups as $group) {
            $key = $group['basket']->key;
            $subjectId = $submitted[$key] ?? null;

            if ($subjectId === null) {
                $validator->errors()->add('basket_subjects', __('Choose one subject from :basket.', ['basket' => $group['basket']->name]));

                continue;
            }

            if (! $group['subjects']->contains('id', $subjectId)) {
                $validator->errors()->add('basket_subjects', __('That subject is not offered in :basket for this grade.', ['basket' => $group['basket']->name]));
            }
        }

        $known = $groups->map(fn (array $group) => $group['basket']->key)->all();

        if (array_diff(array_keys($submitted), $known) !== []) {
            $validator->errors()->add('basket_subjects', __('One of the baskets does not belong to this grade.'));
        }
    }

    /**
     * The picked subject ids keyed by basket key, cast to integers.
     *
     * @return array<string, int>
     */
    private function submittedBaskets(): array
    {
        $submitted = $this->input('basket_subjects');

        if (! is_array($submitted)) {
            return [];
        }

        $baskets = [];

        foreach ($submitted as $key => $subjectId) {
            if (filled($subjectId)) {
                $baskets[$key] = (int) $subjectId;
            }
        }

        return $baskets;
    }
}
