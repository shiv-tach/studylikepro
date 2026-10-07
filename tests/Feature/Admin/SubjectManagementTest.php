<?php

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

test('admins can update a subject and deactivate it', function () {
    $subject = Subject::factory()->create(['name' => 'Old name', 'is_active' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.subjects.update', $subject), ['name' => 'New name'])
        ->assertRedirect();

    $subject->refresh();

    expect($subject->name)->toBe('New name')
        ->and($subject->is_active)->toBeFalse();
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
