<?php

namespace App\Http\Requests;

use App\Models\EducationLevel;
use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Slug is optional in the form; deriving it before validation keeps the
     * uniqueness check honest when the admin types only a name.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && filled($this->input('name'))) {
            $slug = Str::slug((string) $this->input('name'));

            if ($slug !== '') {
                $this->merge(['slug' => $this->availableSlug($slug)]);
            }
        }
    }

    public function rules(): array
    {
        $subject = $this->route('subject');
        $levelId = $subject?->education_level_id ?? $this->input('education_level_id');

        return [
            'education_level_id' => [
                $subject === null ? 'required' : 'nullable',
                'integer',
                Rule::exists('education_levels', 'id'),
            ],
            'basket_id' => [
                'nullable',
                'integer',
                // A subject only ever joins a basket of its own level (the O/L
                // Categories I, II and III belong to the O/L subjects).
                Rule::exists('subject_baskets', 'id')->where('education_level_id', $levelId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('subjects', 'slug')->ignore($subject?->id),
            ],
            'icon' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Slugs resolve subjects in every {subject} route, so they must be unique
     * across levels: a name another level already uses gets its level key
     * prefixed, e.g. a second "Mathematics" in O/L becomes "ol-mathematics".
     * A duplicate inside the same level is left alone for the unique rule to
     * report — two identically named subjects in one level are a mistake.
     */
    private function availableSlug(string $slug): string
    {
        $subject = $this->route('subject');
        $levelId = $this->input('education_level_id') ?? $subject?->education_level_id;

        $existing = Subject::query()
            ->where('slug', $slug)
            ->when($subject !== null, fn ($query) => $query->whereKeyNot($subject->id))
            ->first();

        if ($existing === null || (int) $existing->education_level_id === (int) $levelId) {
            return $slug;
        }

        $key = $levelId ? EducationLevel::query()->whereKey($levelId)->value('key') : null;
        $prefixed = ($key ? $key.'-' : '').$slug;

        $clashesInLevel = Subject::query()
            ->where('slug', $prefixed)
            ->where('education_level_id', $levelId)
            ->when($subject !== null, fn ($query) => $query->whereKeyNot($subject->id))
            ->exists();

        return $clashesInLevel ? $slug : $prefixed;
    }
}
