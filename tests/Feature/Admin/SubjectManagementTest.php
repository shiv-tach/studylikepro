<?php

use App\Enums\GradeBand;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;

test('guests are redirected from admin catalog routes', function () {
    $this->get(route('admin.subjects.index'))->assertRedirect('/login');
});

test('non admins cannot manage the catalog', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('admin.subjects.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->student()->create())
        ->post(route('admin.subjects.store'), ['name' => 'Hacked'])
        ->assertForbidden();

    $this->actingAs(User::factory()->student()->create())
        ->delete(route('admin.subjects.destroy', Subject::factory()->create()))
        ->assertForbidden();
});

test('admins can list subjects with usage counts', function () {
    $subject = Subject::factory()->create(['name' => 'Mathematics', 'icon' => '🔢']);
    Lesson::factory()->for($subject)->count(2)->create();
    Subject::factory()->inactive()->create(['name' => 'Ancient History']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.subjects.index'))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('2 lessons')
        ->assertSee('Ancient History')
        ->assertSee('Inactive');
});

test('admins can create a subject with an auto generated slug', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.subjects.store'), [
            'education_level_id' => $level->id,
            'name' => 'Computer Science',
            'icon' => '💻',
            'sort_order' => 3,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $subject = Subject::query()->where('name', 'Computer Science')->first();

    expect($subject)->not->toBeNull()
        ->and($subject->slug)->toBe('computer-science')
        ->and($subject->education_level_id)->toBe($level->id)
        ->and($subject->is_active)->toBeTrue()
        ->and($subject->sort_order)->toBe(3);
});

test('creating a subject requires an education level', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.subjects.store'), ['name' => 'Computer Science'])
        ->assertSessionHasErrors('education_level_id');
});

test('admins can filter the catalog by level', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $al = EducationLevel::query()->where('key', 'al')->firstOrFail();

    Subject::factory()->create(['name' => 'O/L Mathematics', 'education_level_id' => $ol->id]);
    Subject::factory()->create(['name' => 'Combined Mathematics', 'education_level_id' => $al->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.subjects.index', ['level' => 'ol']))
        ->assertOk()
        ->assertSee('O/L Mathematics')
        ->assertDontSee('Combined Mathematics');

    $this->get(route('admin.subjects.index', ['level' => 'not-a-level']))
        ->assertSessionHasErrors('level');
});

test('a duplicate subject name in the same level fails validation, not the database', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'education_level_id' => $ol->id,
            'name' => 'Mathematics',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'education_level_id' => $ol->id,
            'name' => 'Mathematics',
        ])
        ->assertSessionHasErrors('slug');

    expect(Subject::query()->where('slug', 'mathematics')->count())->toBe(1);
});

test('the same subject name in another level gets a level-prefixed slug', function () {
    $primary = EducationLevel::query()->where('key', 'primary')->firstOrFail();
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'education_level_id' => $primary->id,
            'name' => 'Mathematics',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'education_level_id' => $ol->id,
            'name' => 'Mathematics',
        ])
        ->assertRedirect();

    expect(Subject::query()->pluck('slug')->sort()->values()->all())
        ->toBe(['mathematics', 'ol-mathematics']);
});

test('an explicit slug that another level owns is rejected', function () {
    $primary = EducationLevel::query()->where('key', 'primary')->firstOrFail();
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();

    Subject::factory()->create(['slug' => 'mathematics', 'education_level_id' => $primary->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.subjects.store'), [
            'education_level_id' => $ol->id,
            'name' => 'O/L Mathematics',
            'slug' => 'mathematics',
        ])
        ->assertSessionHasErrors('slug');

    expect(Subject::query()->where('slug', 'mathematics')->count())->toBe(1);
});

test('subject slugs are globally unique in the database', function () {
    $primary = EducationLevel::query()->where('key', 'primary')->firstOrFail();
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();

    Subject::factory()->create(['slug' => 'mathematics', 'education_level_id' => $primary->id]);

    expect(fn () => Subject::factory()->create(['slug' => 'mathematics', 'education_level_id' => $ol->id]))
        ->toThrow(QueryException::class);
});

test('creating a subject validates its input', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.subjects.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('admins can limit a subject to one part of its level', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'education_level_id' => $level->id,
            'name' => 'Junior Science',
            'grade_band' => GradeBand::Junior->value,
        ])
        ->assertRedirect();

    $subject = Subject::query()->where('name', 'Junior Science')->firstOrFail();

    expect($subject->gradeBand())->toBe(GradeBand::Junior);

    // The default is every grade of the level.
    $this->actingAs($admin)
        ->post(route('admin.subjects.store'), [
            'education_level_id' => $level->id,
            'name' => 'All Grade Science',
        ])
        ->assertRedirect();

    expect(Subject::query()->where('name', 'All Grade Science')->firstOrFail()->gradeBand())->toBe(GradeBand::All);

    $this->actingAs($admin)
        ->put(route('admin.subjects.update', $subject), [
            'name' => 'Junior Science',
            'grade_band' => GradeBand::Senior->value,
        ])
        ->assertRedirect();

    expect($subject->fresh()->gradeBand())->toBe(GradeBand::Senior);

    $this->actingAs($admin)
        ->put(route('admin.subjects.update', $subject), [
            'name' => 'Junior Science',
            'grade_band' => 'middle',
        ])
        ->assertSessionHasErrors('grade_band');
});

test('the subject forms offer every grade band, labelled for the level', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $subject = Subject::factory()->create([
        'name' => 'Grade Band Subject',
        'education_level_id' => $ol->id,
    ]);
    $admin = User::factory()->admin()->create();

    // The labels follow the level: the O/L level starts at Grade 6, so its
    // junior band is Grades 6-9 rather than Grades 1-9.
    $this->actingAs($admin)
        ->get(route('admin.subjects.index', ['level' => 'ol']))
        ->assertOk()
        ->assertSee('All grades')
        ->assertSee('Grades 6-9')
        ->assertSee('Grades 10-11');

    $this->actingAs($admin)
        ->get(route('admin.subjects.edit', $subject))
        ->assertOk()
        ->assertSee('name="grade_band"', false)
        ->assertSee('All grades')
        ->assertSee('Grades 6-9')
        ->assertSee('Grades 10-11');
});

test('the lesson matrix only offers the grades the subject band covers', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $subject = Subject::factory()->create([
        'name' => 'Junior Civic',
        'education_level_id' => $ol->id,
        'grade_band' => GradeBand::Junior,
    ]);
    Lesson::factory()->for($subject)->create([
        'name' => 'Senior only lesson',
        'grade_id' => gradeId(10),
    ]);
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.subjects.edit', $subject))
        ->assertOk()
        ->assertSee('Grade 6')
        ->assertSee('Grade 9')
        ->assertDontSee('Grade 10')
        ->assertDontSee('Grade 11');

    expect($response->viewData('grades')->pluck('number')->all())->toBe([6, 7, 8, 9]);

    // A lesson parked in a grade the band does not cover stays out of the matrix.
    $response->assertDontSee('Senior only lesson')
        ->assertSee('0 total');

    // Widening the band brings those grades — and their lessons — back.
    $subject->update(['grade_band' => GradeBand::All]);

    $this->actingAs($admin)
        ->get(route('admin.subjects.edit', $subject))
        ->assertOk()
        ->assertSee('Grade 10')
        ->assertSee('Grade 11')
        ->assertSee('Senior only lesson')
        ->assertSee('1 total');
});

test('admins can update a subject and deactivate it', function () {
    $subject = Subject::factory()->create(['name' => 'Old name', 'is_active' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.subjects.update', $subject), ['name' => 'New name'])
        ->assertRedirect();

    $subject->refresh();

    expect($subject->name)->toBe('New name')
        ->and($subject->is_active)->toBeFalse();
});

test('admins can delete a subject and its lessons', function () {
    $subject = Subject::factory()->create(['name' => 'Obsolete Subject']);
    Lesson::factory()->for($subject)->count(2)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.subjects.destroy', $subject))
        ->assertRedirect(route('admin.subjects.index'))
        ->assertSessionHas('status', 'subject-deleted');

    expect(Subject::find($subject->id))->toBeNull()
        ->and(Lesson::query()->where('subject_id', $subject->id)->count())->toBe(0);
});

test('admins can add, update and delete lessons', function () {
    $subject = Subject::factory()->create();
    $grade = Grade::factory()->create(['education_level_id' => $subject->education_level_id]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.subjects.lessons.store', $subject), ['name' => 'Algebra', 'grade_id' => $grade->id])
        ->assertRedirect();

    $lesson = $subject->lessons()->first();
    expect($lesson->slug)->toBe('algebra')
        ->and($lesson->grade_id)->toBe($grade->id);

    $this->actingAs($admin)
        ->put(route('admin.subjects.lessons.update', [$subject, $lesson]), [
            'name' => 'Linear Algebra',
            'grade_id' => $grade->id,
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect($lesson->fresh()->name)->toBe('Linear Algebra');

    $this->actingAs($admin)
        ->delete(route('admin.subjects.lessons.destroy', [$subject, $lesson]))
        ->assertRedirect();

    expect(Lesson::find($lesson->id))->toBeNull();
});

test('a lesson grade outside the subject level is rejected', function () {
    $subject = Subject::factory()->create();
    $otherLevelGrade = Grade::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.subjects.lessons.store', $subject), [
            'name' => 'Algebra',
            'grade_id' => $otherLevelGrade->id,
        ])
        ->assertSessionHasErrors('grade_id');

    expect($subject->lessons()->count())->toBe(0);
});

test('a lesson cannot be accessed through a different subject', function () {
    $subjectA = Subject::factory()->create();
    $subjectB = Subject::factory()->create();
    $lesson = Lesson::factory()->for($subjectA)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.subjects.lessons.update', [$subjectB, $lesson]), ['name' => 'Moved'])
        ->assertNotFound();
});
