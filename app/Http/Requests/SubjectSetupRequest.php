<?php

namespace App\Http\Requests;

use App\Models\Grade;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isTeacher() ?? false) && $this->user()->teacherProfile()->exists();
    }

    public function rules(): array
    {
        $subject = $this->route('subject');

        return [
            'lessons' => ['nullable', 'array'],
            'lessons.*' => [
                'integer',
                Rule::exists('lessons', 'id')->where('subject_id', $subject?->id),
            ],
            'grades' => ['nullable', 'array'],
            'grades.*' => [
                'integer',
                Rule::exists('grades', 'id')
                    ->where('is_active', true)
                    ->where('education_level_id', $subject?->education_level_id),
            ],
            'rate_per_hour' => ['nullable', 'numeric', 'min:100', 'max:100000'],
            'grade_rates' => ['nullable', 'array', $this->gradeRateKeysRule()],
            'grade_rates.*' => ['nullable', 'numeric', 'min:100', 'max:100000'],
        ];
    }

    /**
     * Every grade with an explicit rate must belong to this subject's level.
     */
    private function gradeRateKeysRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $allowed = Grade::query()
                ->where('education_level_id', $this->route('subject')?->education_level_id)
                ->pluck('id')
                ->all();

            foreach (array_keys((array) $value) as $gradeId) {
                if (! in_array((int) $gradeId, $allowed, true)) {
                    $fail(__('One of the selected grades does not belong to this subject.'));
                }
            }
        };
    }

    /**
     * The grades this request scopes the subject to, or null when the request
     * carries no grade set (a partial update, which leaves lessons alone).
     * The setup form always sends `sync_grades` so that even "no grades
     * selected" is treated as a deliberate, authoritative choice.
     *
     * @return array<int, int>|null
     */
    public function scopedGradeIds(): ?array
    {
        if (! $this->boolean('sync_grades') && ! $this->has('grades')) {
            return null;
        }

        return array_map('intval', $this->validated('grades') ?? []);
    }

    /**
     * The explicit per-grade rates this request sets, keyed by grade id and
     * stored in minor units. The submitted set is authoritative: a grade
     * without a value (or outside the selected grades) loses its rate.
     * Null means the request did not touch rates at all.
     *
     * @return array<int, int>|null
     */
    public function scopedGradeRates(): ?array
    {
        if (! $this->has('grade_rates')) {
            return null;
        }

        $scope = $this->scopedGradeIds();

        $rates = [];

        foreach ($this->validated('grade_rates') ?? [] as $gradeId => $rate) {
            $gradeId = (int) $gradeId;

            if ($rate === null || $rate === '' || ($scope !== null && ! in_array($gradeId, $scope, true))) {
                continue;
            }

            $rates[$gradeId] = (int) round(((float) $rate) * 100);
        }

        return $rates;
    }
}
