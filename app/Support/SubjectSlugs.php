<?php

namespace App\Support;

use App\Models\EducationLevel;
use App\Models\Subject;
use Illuminate\Support\Str;

/**
 * Slug derivation for subjects created outside the admin form (a subject
 * request approval). Mirrors the admin form's rule — a name another level
 * already uses gets its level key prefixed — and adds a numeric suffix when
 * the slug is still taken, because an approved request must never collide.
 */
final class SubjectSlugs
{
    public static function unique(string $name, ?int $levelId): string
    {
        $base = Str::slug($name) ?: 'subject';

        $slug = $base;

        if (Subject::query()
            ->where('slug', $base)
            ->where('education_level_id', '!=', $levelId)
            ->exists()) {
            $key = $levelId !== null
                ? EducationLevel::query()->whereKey($levelId)->value('key')
                : null;

            $slug = ($key !== null ? $key.'-' : '').$base;
        }

        $candidate = $slug;
        $counter = 2;

        while (Subject::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
