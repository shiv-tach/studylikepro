<?php

use App\Models\ActivityLog;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\User;

/**
 * The grade matrix bulk tools for one O/L subject: copy between grades, bulk
 * activate/deactivate a grade, and reorder lessons.
 */
beforeEach(function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();

    $this->subject = Subject::factory()->create([
        'name' => 'Mathematics',
        'education_level_id' => $level->id,
    ]);

    $this->grade6 = Grade::query()->where('number', 6)->firstOrFail();
    $this->grade7 = Grade::query()->where('number', 7)->firstOrFail();
    $this->admin = User::factory()->admin()->create();
});

test('the same lesson slug may exist in two grades but not twice in one', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.store', $this->subject), [
            'name' => 'Unit 01',
            'grade_id' => $this->grade6->id,
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.store', $this->subject), [
            'name' => 'Unit 01',
            'grade_id' => $this->grade7->id,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.store', $this->subject), [
            'name' => 'Unit 01',
            'grade_id' => $this->grade6->id,
        ])
        ->assertSessionHasErrors('slug');

    expect($this->subject->lessons()->count())->toBe(2);
});

test('copy duplicates a grade into another grade and skips existing slugs', function () {
    $first = Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade6->id, 'name' => 'Unit 01', 'slug' => 'unit-01', 'sort_order' => 1,
    ]);
    Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade6->id, 'name' => 'Unit 02', 'slug' => 'unit-02', 'sort_order' => 2,
        'description' => 'Fractions',
    ]);
    $existing = Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade7->id, 'name' => 'Grade 7 Unit 01', 'slug' => 'unit-01', 'sort_order' => 1,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.copy', $this->subject), [
            'from_grade_id' => $this->grade6->id,
            'to_grade_id' => $this->grade7->id,
        ])
        ->assertRedirect(route('admin.subjects.edit', ['subject' => $this->subject, 'grade' => $this->grade7->id]))
        ->assertSessionHas('copied', 1);

    $grade7 = $this->subject->lessons()->where('grade_id', $this->grade7->id)->ordered()->get();

    expect($grade7)->toHaveCount(2)
        ->and($grade7->pluck('slug')->all())->toEqualCanonicalizing(['unit-01', 'unit-02'])
        ->and($grade7->firstWhere('slug', 'unit-01')->name)->toBe('Grade 7 Unit 01')
        ->and($grade7->firstWhere('slug', 'unit-02')->description)->toBe('Fractions')
        ->and($existing->fresh()->name)->toBe('Grade 7 Unit 01')
        ->and($first->fresh()->name)->toBe('Unit 01');
});

test('copying twice does not duplicate anything', function () {
    Lesson::factory()->for($this->subject)->count(3)->create(['grade_id' => $this->grade6->id]);

    foreach (range(1, 2) as $attempt) {
        $this->actingAs($this->admin)
            ->post(route('admin.subjects.lessons.copy', $this->subject), [
                'from_grade_id' => $this->grade6->id,
                'to_grade_id' => $this->grade7->id,
            ]);
    }

    expect($this->subject->lessons()->where('grade_id', $this->grade7->id)->count())->toBe(3);
});

test('copy rejects the same grade twice and grades outside the level', function () {
    $primaryGrade = Grade::query()->where('number', 3)->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.copy', $this->subject), [
            'from_grade_id' => $this->grade6->id,
            'to_grade_id' => $this->grade6->id,
        ])
        ->assertSessionHasErrors('to_grade_id');

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.copy', $this->subject), [
            'from_grade_id' => $this->grade6->id,
            'to_grade_id' => $primaryGrade->id,
        ])
        ->assertSessionHasErrors('to_grade_id');
});

test('bulk activate flips one grade without touching the others', function () {
    $grade6Lessons = Lesson::factory()->for($this->subject)->count(2)->create([
        'grade_id' => $this->grade6->id, 'is_active' => false,
    ]);
    $grade7Lesson = Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade7->id, 'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.bulk-active', $this->subject), [
            'grade_id' => $this->grade6->id,
            'is_active' => '1',
        ])
        ->assertSessionHas('updated', 2);

    expect($grade6Lessons->fresh()->every->is_active)->toBeTrue()
        ->and($grade7Lesson->fresh()->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.bulk-active', $this->subject), [
            'grade_id' => $this->grade6->id,
            'is_active' => '0',
        ]);

    expect($grade6Lessons->fresh()->every->is_active)->toBeFalse()
        ->and($grade7Lesson->fresh()->is_active)->toBeTrue();
});

test('the add-lesson form offers an Active toggle that is on by default', function () {
    // A lesson added while this toggle is missing would silently be created
    // inactive and never reach the public catalog or the pickers.
    $this->actingAs($this->admin)
        ->get(route('admin.subjects.edit', $this->subject))
        ->assertOk()
        ->assertSeeInOrder([
            'New lesson in the selected grade',
            'name="is_active" value="1" checked',
            'Add lesson',
        ], false);
});

test('bulk activating a grade refreshes the public catalog', function () {
    Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade6->id, 'name' => 'Fractions', 'slug' => 'fractions', 'is_active' => false,
    ]);

    // Prime the cached catalog: inactive lessons keep the subject hidden.
    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $this->grade6->id]))
        ->assertOk()
        ->assertDontSee('Mathematics');

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.bulk-active', $this->subject), [
            'grade_id' => $this->grade6->id,
            'is_active' => '1',
        ])
        ->assertSessionHas('updated', 1);

    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $this->grade6->id]))
        ->assertOk()
        ->assertSee('Mathematics');
});

test('bulk activate rejects a grade outside the subject level', function () {
    $primaryGrade = Grade::query()->where('number', 3)->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.bulk-active', $this->subject), [
            'grade_id' => $primaryGrade->id,
            'is_active' => '1',
        ])
        ->assertSessionHasErrors('grade_id');
});

test('move swaps a lesson with its neighbour inside the grade', function () {
    $a = Lesson::factory()->for($this->subject)->create(['grade_id' => $this->grade6->id, 'name' => 'A', 'slug' => 'a', 'sort_order' => 1]);
    $b = Lesson::factory()->for($this->subject)->create(['grade_id' => $this->grade6->id, 'name' => 'B', 'slug' => 'b', 'sort_order' => 2]);
    $c = Lesson::factory()->for($this->subject)->create(['grade_id' => $this->grade6->id, 'name' => 'C', 'slug' => 'c', 'sort_order' => 3]);
    $other = Lesson::factory()->for($this->subject)->create(['grade_id' => $this->grade7->id, 'name' => 'D', 'slug' => 'd', 'sort_order' => 1]);

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.move', [$this->subject, $b]), ['direction' => 'up'])
        ->assertRedirect();

    $ordered = $this->subject->lessons()->where('grade_id', $this->grade6->id)->ordered()->pluck('name')->all();

    expect($ordered)->toBe(['B', 'A', 'C'])
        ->and($a->fresh()->sort_order)->toBe(2)
        ->and($b->fresh()->sort_order)->toBe(1)
        ->and($other->fresh()->sort_order)->toBe(1);

    // Already at the top: a no-op.
    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.move', [$this->subject, $b]), ['direction' => 'up'])
        ->assertRedirect();

    expect($this->subject->lessons()->where('grade_id', $this->grade6->id)->ordered()->pluck('name')->all())->toBe(['B', 'A', 'C']);

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.lessons.move', [$this->subject, $c]), ['direction' => 'down'])
        ->assertRedirect();

    expect($this->subject->lessons()->where('grade_id', $this->grade6->id)->ordered()->pluck('name')->all())->toBe(['B', 'A', 'C']);
});

test('the grade matrix shows tab counts and the lessons of each grade', function () {
    Lesson::factory()->for($this->subject)->create([
        'grade_id' => $this->grade6->id, 'name' => 'Fractions', 'slug' => 'fractions',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.subjects.edit', $this->subject))
        ->assertOk()
        ->assertSee('Lessons by grade')
        ->assertSee('Grade 6')
        ->assertSee('Grade 7')
        ->assertSee('Fractions')
        ->assertSee('Copy lessons from');

    $this->get(route('admin.subjects.edit', ['subject' => $this->subject, 'grade' => $this->grade7->id]))
        ->assertOk();
});

test('the matrix explains when a subject has no education level', function () {
    $orphan = Subject::factory()->create(['education_level_id' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.subjects.edit', $orphan))
        ->assertOk()
        ->assertSee('no education level');
});

test('the bulk tools are written to the activity log', function () {
    Lesson::factory()->for($this->subject)->create(['grade_id' => $this->grade6->id]);

    $this->actingAs($this->admin)->post(route('admin.subjects.lessons.copy', $this->subject), [
        'from_grade_id' => $this->grade6->id,
        'to_grade_id' => $this->grade7->id,
    ]);

    $this->actingAs($this->admin)->post(route('admin.subjects.lessons.bulk-active', $this->subject), [
        'grade_id' => $this->grade7->id,
        'is_active' => '0',
    ]);

    $actions = ActivityLog::query()->pluck('action');

    expect($actions)->toContain('admin.subjects.lessons.copy')
        ->and($actions)->toContain('admin.subjects.lessons.bulk-active');
});
