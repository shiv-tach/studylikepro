<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the starter Sri Lankan curriculum used by search, matching and AI
 * classification: the four levels (via EducationLevelSeeder), a subject list
 * per level, and a per-grade lesson list for every subject.
 *
 * Lesson names are intentionally generic placeholders ("Unit 01" ...) until an
 * admin renames them to the official syllabus lessons in the curriculum
 * matrix; the lesson counts (e.g. O/L Mathematics: 12 in Grade 6, 10 in Grade
 * 7) follow the real structure so the grade-by-grade shape is right from day
 * one.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EducationLevelSeeder::class);

        $levels = EducationLevel::query()->get()->keyBy('key');

        foreach ($this->catalog() as $levelKey => $subjects) {
            $level = $levels->get($levelKey);

            if ($level === null) {
                continue;
            }

            $grades = Grade::query()
                ->where('education_level_id', $level->id)
                ->get()
                ->keyBy(fn (Grade $grade) => $grade->number ?? 'other');

            foreach ($subjects as $sort => $data) {
                // Slugs are globally unique (route binding resolves by slug
                // alone), so a name another level already owns gets the level
                // key prefixed: "Mathematics" → "ol-mathematics".
                $slug = Str::slug($data['name']);

                if (Subject::query()->where('slug', $slug)->where('education_level_id', '!=', $level->id)->exists()) {
                    $slug = $level->key.'-'.$slug;
                }

                $subject = Subject::query()->firstOrCreate(
                    ['education_level_id' => $level->id, 'slug' => $slug],
                    [
                        'name' => $data['name'],
                        'icon' => $data['icon'] ?? null,
                        'is_active' => true,
                        'sort_order' => $sort,
                    ],
                );

                foreach ($data['grades'] as $gradeNumber) {
                    $grade = $grades->get($gradeNumber);

                    if ($grade === null) {
                        continue;
                    }

                    $lessonCount = $data['lessons_by_grade'][$gradeNumber] ?? $data['lessons'];

                    for ($index = 1; $index <= $lessonCount; $index++) {
                        $name = sprintf('Unit %02d', $index);

                        Lesson::query()->firstOrCreate(
                            ['subject_id' => $subject->id, 'grade_id' => $grade->id, 'slug' => Str::slug($name)],
                            ['name' => $name, 'is_active' => true, 'sort_order' => $index],
                        );
                    }
                }
            }
        }
    }

    /**
     * Subjects per level. "grades" lists the grade numbers a subject runs in
     * ("other" is the level's single unnumbered grade); "lessons" is the
     * placeholder lesson count per grade, overridable per grade.
     *
     * @return array<string, list<array{name: string, icon: string, grades: list<int|string>, lessons: int, lessons_by_grade?: array<int, int>}>>
     */
    private function catalog(): array
    {
        return [
            'primary' => [
                ['name' => 'Mathematics', 'icon' => '🔢', 'grades' => [1, 2, 3, 4, 5], 'lessons' => 4],
                ['name' => 'English', 'icon' => '📖', 'grades' => [1, 2, 3, 4, 5], 'lessons' => 4],
                ['name' => 'Sinhala', 'icon' => '📜', 'grades' => [1, 2, 3, 4, 5], 'lessons' => 4],
                ['name' => 'Tamil', 'icon' => '📜', 'grades' => [1, 2, 3, 4, 5], 'lessons' => 4],
                ['name' => 'Environmental Studies', 'icon' => '🌱', 'grades' => [1, 2, 3, 4, 5], 'lessons' => 4],
            ],
            'ol' => [
                ['name' => 'Mathematics', 'icon' => '🔢', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 10, 'lessons_by_grade' => [6 => 12, 7 => 10]],
                ['name' => 'Science', 'icon' => '🔬', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 10],
                ['name' => 'English', 'icon' => '📖', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 8],
                ['name' => 'Sinhala', 'icon' => '📜', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 8],
                ['name' => 'Tamil', 'icon' => '📜', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 8],
                ['name' => 'History', 'icon' => '🏛️', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 6],
                ['name' => 'Buddhism', 'icon' => '☸️', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 6],
                ['name' => 'Geography', 'icon' => '🌍', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 6],
                ['name' => 'ICT', 'icon' => '💻', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 6],
                ['name' => 'Commerce', 'icon' => '💼', 'grades' => [10, 11], 'lessons' => 8],
                ['name' => 'Health & Physical Education', 'icon' => '🏃', 'grades' => [6, 7, 8, 9, 10, 11], 'lessons' => 4],
            ],
            'al' => [
                ['name' => 'Combined Mathematics', 'icon' => '🔢', 'grades' => [12, 13], 'lessons' => 12],
                ['name' => 'Physics', 'icon' => '⚛️', 'grades' => [12, 13], 'lessons' => 12],
                ['name' => 'Chemistry', 'icon' => '🧪', 'grades' => [12, 13], 'lessons' => 12],
                ['name' => 'Biology', 'icon' => '🧬', 'grades' => [12, 13], 'lessons' => 12],
                ['name' => 'Accounting', 'icon' => '📊', 'grades' => [12, 13], 'lessons' => 10],
                ['name' => 'Economics', 'icon' => '📈', 'grades' => [12, 13], 'lessons' => 10],
                ['name' => 'Business Studies', 'icon' => '💼', 'grades' => [12, 13], 'lessons' => 10],
                ['name' => 'ICT', 'icon' => '💻', 'grades' => [12, 13], 'lessons' => 10],
            ],
            'other' => [
                ['name' => 'English Conversation', 'icon' => '🗣️', 'grades' => ['other'], 'lessons' => 8],
                ['name' => 'Adult Mathematics', 'icon' => '🔢', 'grades' => ['other'], 'lessons' => 6],
                ['name' => 'Computer Coding', 'icon' => '💻', 'grades' => ['other'], 'lessons' => 8],
            ],
        ];
    }
}
