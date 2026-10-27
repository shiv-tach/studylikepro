<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The official Sri Lankan syllabus units behind the catalog: one lesson per
 * textbook unit, English title plus Sinhala subtitle. The lists live in
 * database/data/curriculum.php so both the seeder and the sync command read
 * exactly the same source.
 *
 * Subjects without a list fall back to the seeder's placeholder lessons, so
 * electives keep working until their units are entered.
 */
final class CurriculumSyllabus
{
    /**
     * @var array<string, array<string, array<int, list<array{name: string, name_si: ?string}>>>>|null
     */
    private static ?array $data = null;

    /**
     * @return array<string, array<string, array<int, list<array{name: string, name_si: ?string}>>>>
     */
    public static function data(): array
    {
        return self::$data ??= require database_path('data/curriculum.php');
    }

    /**
     * Drop the memoised copy (used by tests that swap the data file).
     */
    public static function flush(): void
    {
        self::$data = null;
    }

    /**
     * Raw units for one subject of one grade. Entries may be either
     * `['name' => ..., 'name_si' => ...]` arrays or the compact
     * `"English title|සිංහල මාතෘකාව"` string (the subtitle is optional when a
     * subject has no English/Sinhala counterpart, e.g. the Sinhala or Tamil
     * language subjects).
     *
     * @return list<array{name: string, name_si: ?string}>
     */
    public static function units(string $levelKey, string $subjectName, int $gradeNumber): array
    {
        $raw = self::data()[$levelKey][$subjectName][$gradeNumber] ?? [];

        return array_values(array_map(static function (array|string $unit): array {
            if (is_string($unit)) {
                [$name, $nameSi] = array_pad(explode('|', $unit, 2), 2, null);

                return ['name' => trim($name), 'name_si' => $nameSi !== null ? trim($nameSi) : null];
            }

            return [
                'name' => trim((string) ($unit['name'] ?? '')),
                'name_si' => isset($unit['name_si']) ? trim((string) $unit['name_si']) : null,
            ];
        }, array_values($raw)));
    }

    public static function covers(string $levelKey, string $subjectName, int $gradeNumber): bool
    {
        return self::units($levelKey, $subjectName, $gradeNumber) !== [];
    }

    /**
     * The subject names that carry official units in one level.
     *
     * @return list<string>
     */
    public static function subjects(string $levelKey): array
    {
        return array_keys(self::data()[$levelKey] ?? []);
    }

    /**
     * The grade numbers a subject has official units for.
     *
     * @return list<int>
     */
    public static function grades(string $levelKey, string $subjectName): array
    {
        return array_keys(self::data()[$levelKey][$subjectName] ?? []);
    }

    /**
     * The unit list ready to seed or sync: names, subtitles, a slug that is
     * unique inside the subject+grade and the position as sort order.
     *
     * @return list<array{name: string, name_si: ?string, slug: string, sort_order: int}>
     */
    public static function lessons(string $levelKey, string $subjectName, int $gradeNumber): array
    {
        $lessons = [];
        $seen = [];

        foreach (array_values(self::units($levelKey, $subjectName, $gradeNumber)) as $index => $unit) {
            $slug = Str::slug($unit['name']) ?: 'unit-'.($index + 1);
            $base = $slug;
            $suffix = 2;

            while (in_array($slug, $seen, true)) {
                $slug = $base.'-'.$suffix++;
            }

            $seen[] = $slug;

            $lessons[] = [
                'name' => $unit['name'],
                'name_si' => $unit['name_si'] ?? null,
                'slug' => $slug,
                'sort_order' => $index + 1,
            ];
        }

        return $lessons;
    }
}
