<?php

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherProfile;

/**
 * The sync command renames placeholder lessons in place, so teacher links,
 * student interests, bookings and service offerings survive the curriculum
 * update.
 */
beforeEach(function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $this->grade = Grade::query()->where('education_level_id', $level->id)->where('number', 6)->firstOrFail();

    $this->subject = Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'mathematics',
        'education_level_id' => $level->id,
    ]);
});

test('placeholder lessons are renamed in place and the missing units are added', function () {
    $first = Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade->id, 'name' => 'Unit 01', 'slug' => 'unit-01', 'sort_order' => 1,
    ]);
    Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade->id, 'name' => 'Unit 02', 'slug' => 'unit-02', 'sort_order' => 2,
    ]);

    $teacher = TeacherProfile::factory()->create();
    $teacher->lessons()->attach([$first->id]);

    $this->artisan('studylikepro:sync-curriculum', ['--subject' => 'Mathematics', '--grade' => 6])
        ->assertSuccessful();

    $lessons = $this->subject->lessons()->where('grade_id', $this->grade->id)->ordered()->get();

    expect($lessons)->toHaveCount(24)
        ->and($lessons->first()->id)->toBe($first->id)
        ->and($lessons->first()->name)->toBe('Circle')
        ->and($lessons->first()->name_si)->toBe('වෘත්තය')
        ->and($lessons[1]->name)->toBe('Place Value')
        ->and($teacher->fresh()->lessons->pluck('id')->all())->toContain($first->id);
});

test('extra placeholder lessons are deactivated instead of deleted', function () {
    foreach (range(1, 30) as $position) {
        Lesson::factory()->for($this->subject)->create([
            'grade_id' => $this->grade->id,
            'name' => 'Unit '.str_pad((string) $position, 2, '0', STR_PAD_LEFT),
            'slug' => 'unit-'.$position,
            'sort_order' => $position,
        ]);
    }

    $this->artisan('studylikepro:sync-curriculum', ['--subject' => 'Mathematics', '--grade' => 6])
        ->assertSuccessful();

    expect($this->subject->lessons()->where('grade_id', $this->grade->id)->count())->toBe(30)
        ->and($this->subject->lessons()->where('grade_id', $this->grade->id)->where('is_active', true)->count())->toBe(24)
        ->and($this->subject->lessons()->where('grade_id', $this->grade->id)->where('is_active', false)->count())->toBe(6);
});

test('a dry run reports the change without writing', function () {
    $lesson = Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade->id, 'name' => 'Unit 01', 'slug' => 'unit-01', 'sort_order' => 1,
    ]);

    $this->artisan('studylikepro:sync-curriculum', ['--subject' => 'Mathematics', '--grade' => 6, '--dry-run' => true])
        ->expectsOutputToContain('dry run')
        ->assertSuccessful();

    expect($lesson->fresh()->name)->toBe('Unit 01')
        ->and($this->subject->lessons()->where('grade_id', $this->grade->id)->count())->toBe(1);
});

test('a lesson an admin renamed is kept unless force is passed', function () {
    $custom = Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade->id, 'name' => 'My own unit', 'slug' => 'my-own-unit', 'sort_order' => 1,
    ]);

    $this->artisan('studylikepro:sync-curriculum', ['--subject' => 'Mathematics', '--grade' => 6])
        ->assertSuccessful();

    expect($custom->fresh()->name)->toBe('My own unit');

    $this->artisan('studylikepro:sync-curriculum', ['--subject' => 'Mathematics', '--grade' => 6, '--force' => true])
        ->assertSuccessful();

    expect($custom->fresh()->name)->toBe('Circle');
});

test('running the command twice is a no-op', function () {
    $this->artisan('studylikepro:sync-curriculum', ['--subject' => 'Mathematics', '--grade' => 6])
        ->assertSuccessful();

    $before = $this->subject->lessons()->where('grade_id', $this->grade->id)->ordered()->get()->map->only(['id', 'name', 'sort_order'])->all();

    $this->artisan('studylikepro:sync-curriculum', ['--subject' => 'Mathematics', '--grade' => 6])
        ->assertSuccessful();

    $after = $this->subject->lessons()->where('grade_id', $this->grade->id)->ordered()->get()->map->only(['id', 'name', 'sort_order'])->all();

    expect($after)->toBe($before);
});
