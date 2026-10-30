<?php

namespace App\Support;

use App\Models\EducationLevel;
use App\Models\Subject;

/**
 * The subject catalog in the shape the pickers need it: every active subject
 * grouped by its education level.
 *
 * Both the teacher survey (the guest invite wizard) and the verification step
 * ask "which subjects do you teach?", so the query lives here once instead of
 * being copied into each controller.
 */
final class SubjectCatalog
{
    /**
     * @param  list<string>|null  $levelKeys  only these levels (null = every active level)
     * @return list<array{key: string, name: string, shortName: string, icon: ?string, subjects: list<array{id: int, name: string, icon: string}>}>
     */
    public static function groups(?array $levelKeys = null): array
    {
        $levels = EducationLevel::query()->active()->ordered()->get();

        if ($levelKeys !== null) {
            $levels = $levels
                ->filter(fn (EducationLevel $level) => in_array((string) $level->key, $levelKeys, true))
                ->values();
        }

        $byLevel = Subject::query()
            ->active()
            ->ordered()
            ->with('educationLevel')
            ->get()
            ->groupBy('education_level_id');

        $groups = $levels->map(fn (EducationLevel $level) => [
            'key' => $level->key,
            'name' => $level->name,
            'shortName' => self::shortLevelName($level),
            'icon' => $level->icon,
            'subjects' => self::subjectRows($byLevel->get($level->id, collect())),
        ]);

        // Subjects that somehow have no level still need a home in the picker.
        // A level-scoped call (the survey asks for O/L or A/L first) leaves them
        // out on purpose.
        $unassigned = $levelKeys === null ? $byLevel->get(null, collect()) : collect();

        if ($unassigned->isNotEmpty()) {
            $groups->push([
                'key' => 'other',
                'name' => __('Other'),
                'shortName' => __('Other'),
                'icon' => '🧭',
                'subjects' => self::subjectRows($unassigned),
            ]);
        }

        return $groups->filter(fn (array $group) => $group['subjects'] !== [])->values()->all();
    }

    /**
     * The ids of every active subject in these levels: what a survey answer may
     * legitimately contain.
     *
     * @param  list<string>  $levelKeys
     * @return list<int>
     */
    public static function subjectIdsForLevels(array $levelKeys): array
    {
        $levelIds = EducationLevel::query()
            ->whereIn('key', $levelKeys)
            ->pluck('id');

        if ($levelIds->isEmpty()) {
            return [];
        }

        return Subject::query()
            ->active()
            ->whereIn('education_level_id', $levelIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The compact label a narrow tab or a badge can carry: "O/L" instead of
     * "O/L (Ordinary Level)". Admin-added levels keep their full name.
     */
    private static function shortLevelName(EducationLevel $level): string
    {
        return match ((string) $level->key) {
            'ol' => __('O/L'),
            'al' => __('A/L'),
            default => (string) $level->name,
        };
    }

    /**
     * @param  iterable<Subject>  $subjects
     * @return list<array{id: int, name: string, icon: string}>
     */
    private static function subjectRows(iterable $subjects): array
    {
        return collect($subjects)
            ->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'icon' => $subject->icon ?? '📘',
            ])
            ->values()
            ->all();
    }
}
