<?php

namespace App\Http\Requests;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\SubjectBasket;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InterestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();
        $grade = $user?->studentProfile?->grade;

        $subjectRule = Rule::exists('subjects', 'id')->where('is_active', true);

        if ($grade !== null) {
            $subjectRule->where('education_level_id', $grade->education_level_id);
        }

        return [
            'subjects' => ['nullable', 'array', $this->basketLimitRule($grade)],
            'subjects.*' => ['integer', $subjectRule],
            'lessons' => ['nullable', 'array'],
            'lessons.*' => ['integer', Rule::exists('lessons', 'id')->where('is_active', true)],
        ];
    }

    /**
     * In the O/L basket years (Grades 10-11) a student picks one subject from
     * each basket, so a second pick inside the same basket is rejected while a
     * missing basket is only a reminder, never a blocked save.
     */
    private function basketLimitRule(?Grade $grade): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($grade): void {
            if ($grade === null || ! $grade->requiresBasketSelection()) {
                return;
            }

            $baskets = SubjectBasket::query()
                ->where('education_level_id', $grade->education_level_id)
                ->active()
                ->pluck('name', 'id');

            if ($baskets->isEmpty()) {
                return;
            }

            $counts = Subject::query()
                ->whereIn('id', is_array($value) ? $value : [])
                ->whereIn('basket_id', $baskets->keys())
                ->pluck('basket_id')
                ->countBy();

            $overfull = $counts->filter(fn (int $count) => $count > 1);

            if ($overfull->isEmpty()) {
                return;
            }

            $names = $overfull->keys()->map(fn ($id) => $baskets[$id])->implode(', ');

            $fail(__('Pick one subject from each O/L basket — you selected more than one :basket subject.', ['basket' => $names]));
        };
    }
}
