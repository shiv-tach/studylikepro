<?php

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Subject;
use App\Support\CurriculumSyllabus;
use Database\Seeders\CatalogSeeder;

/**
 * The official syllabus lists behind the catalog lessons. The counts are the
 * textbook unit counts, not guesses: they are what the subject pages display.
 */
test('mathematics is seeded with the official unit counts', function () {
    $this->seed(CatalogSeeder::class);

    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $mathematics = Subject::query()
        ->where('education_level_id', $level->id)
        ->where('name', 'Mathematics')
        ->firstOrFail();

    $expected = [6 => 24, 7 => 29, 8 => 30, 9 => 28, 10 => 32, 11 => 25];

    foreach ($expected as $number => $count) {
        $grade = Grade::query()
            ->where('education_level_id', $level->id)
            ->where('number', $number)
            ->firstOrFail();

        $lessons = $mathematics->lessons()->where('grade_id', $grade->id)->get();

        expect($lessons)->toHaveCount($count)
            ->and($lessons->whereNull('name_si'))->toHaveCount(0)
            ->and($lessons->pluck('name')->unique())->toHaveCount($count);
    }
});

test('grade 7 mathematics runs from symmetry to probability with sinhala subtitles', function () {
    $this->seed(CatalogSeeder::class);

    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade = Grade::query()->where('education_level_id', $level->id)->where('number', 7)->firstOrFail();
    $mathematics = Subject::query()
        ->where('education_level_id', $level->id)
        ->where('name', 'Mathematics')
        ->firstOrFail();

    $names = $mathematics->lessons()->where('grade_id', $grade->id)->ordered()->pluck('name')->all();

    expect($names[0])->toBe('Symmetry')
        ->and($names[1])->toBe('Sets')
        ->and($names[28])->toBe('Likelihood of an Event Occurring');

    $symmetry = $mathematics->lessons()
        ->where('grade_id', $grade->id)
        ->where('name', 'Symmetry')
        ->sole();

    expect($symmetry->name_si)->toBe('සමමිතිය')
        ->and($symmetry->sort_order)->toBe(1);
});

test('every syllabus list is well formed', function () {
    foreach (CurriculumSyllabus::data() as $levelKey => $subjects) {
        expect($subjects)->not->toBeEmpty("{$levelKey} has no subjects");

        foreach ($subjects as $subjectName => $grades) {
            foreach ($grades as $gradeNumber => $units) {
                $label = "{$levelKey} · {$subjectName} · Grade {$gradeNumber}";

                expect($units)->not->toBeEmpty("{$label} has no units");

                $names = array_map(fn (array $unit) => $unit['name'], CurriculumSyllabus::units($levelKey, $subjectName, $gradeNumber));

                expect(array_unique($names))->toHaveCount(count($units), "{$label} repeats a title")
                    ->and(array_filter($names, fn (string $name) => trim($name) === ''))->toBeEmpty("{$label} has a blank title");

                foreach (CurriculumSyllabus::units($levelKey, $subjectName, $gradeNumber) as $unit) {
                    // The Sinhala subtitle is optional (language subjects have no
                    // official English/Sinhala counterpart), but never blank.
                    expect($unit['name_si'] === null || trim($unit['name_si']) !== '')->toBeTrue("{$label} has a blank Sinhala title");
                }

                $lessons = CurriculumSyllabus::lessons($levelKey, $subjectName, $gradeNumber);

                expect(array_column($lessons, 'sort_order'))->toBe(range(1, count($lessons)), "{$label} has a broken order")
                    ->and(array_unique(array_column($lessons, 'slug')))->toHaveCount(count($lessons), "{$label} repeats a slug");
            }
        }
    }
});
