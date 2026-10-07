<?php

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\User;

test('the subjects page requires an onboarded teacher', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.subjects.index'))
        ->assertRedirect(route('teacher.profile'));
});

test('teachers can select their subjects', function () {
    $math = Subject::factory()->create(['name' => 'Mathematics']);
    $physics = Subject::factory()->create(['name' => 'Physics']);
    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => [$math->id, $physics->id]])
        ->assertRedirect(route('teacher.subjects.index'));

    expect($user->teacherProfile->subjects()->pluck('subjects.id')->all())
        ->toEqualCanonicalizing([$math->id, $physics->id]);
});

test('removing a subject also removes its lessons', function () {
    $math = Subject::factory()->create();
    $lesson = Lesson::factory()->for($math)->create();
    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;

    $profile->subjects()->sync([$math->id]);
    $profile->lessons()->sync([$lesson->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => []])
        ->assertRedirect();

    expect($profile->fresh()->subjects()->count())->toBe(0)
        ->and($profile->fresh()->lessons()->count())->toBe(0);
});

test('teachers can configure lessons, grades and a rate override per subject', function () {
    $math = Subject::factory()->create(['name' => 'Mathematics']);
    $grade = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    [$algebra, $geometry] = Lesson::factory()->for($math)->count(2)->create(['grade_id' => $grade->id]);
    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;

    $profile->subjects()->sync([$math->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'lessons' => [$algebra->id, $geometry->id],
            'grades' => [$grade->id],
            'rate_per_hour' => 750,
        ])
        ->assertRedirect(route('teacher.subjects.index'));

    $profile->refresh();

    expect($profile->lessons()->pluck('lessons.id')->all())->toEqualCanonicalizing([$algebra->id, $geometry->id]);

    $pivot = $profile->subjects()->where('subjects.id', $math->id)->first()->pivot;

    expect($pivot->grade_levels)->toBe([(string) $grade->id])
        ->and($pivot->rate_per_hour_minor)->toBe(75000);
});

test('a grade from another level is rejected', function () {
    $math = Subject::factory()->create();
    $otherLevelGrade = Grade::factory()->create();
    $user = User::factory()->teacher()->onboarded()->create();

    $user->teacherProfile->subjects()->sync([$math->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'grades' => [$otherLevelGrade->id],
        ])
        ->assertSessionHasErrors('grades.0');
});

test('updating one subject keeps lessons of other subjects', function () {
    $math = Subject::factory()->create();
    $physics = Subject::factory()->create();
    $algebra = Lesson::factory()->for($math)->create();
    $mechanics = Lesson::factory()->for($physics)->create();
    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;

    $profile->subjects()->sync([$math->id, $physics->id]);
    $profile->lessons()->sync([$algebra->id, $mechanics->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $physics), ['lessons' => [$mechanics->id]])
        ->assertRedirect();

    expect($profile->fresh()->lessons()->pluck('lessons.id')->all())
        ->toEqualCanonicalizing([$algebra->id, $mechanics->id]);
});

test('lessons from another subject are rejected', function () {
    $math = Subject::factory()->create();
    $english = Subject::factory()->create();
    $englishLesson = Lesson::factory()->for($english)->create();
    $user = User::factory()->teacher()->onboarded()->create();

    $user->teacherProfile->subjects()->sync([$math->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), ['lessons' => [$englishLesson->id]])
        ->assertSessionHasErrors('lessons.0');
});

test('updating a subject targets it by slug even when another level shares the name', function () {
    $primary = EducationLevel::query()->where('key', 'primary')->firstOrFail();
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();

    Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'mathematics',
        'education_level_id' => $primary->id,
    ]);
    $olMath = Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'ol-mathematics',
        'education_level_id' => $ol->id,
    ]);

    $grade = Grade::factory()->create(['education_level_id' => $ol->id]);
    $lesson = Lesson::factory()->for($olMath)->create(['grade_id' => $grade->id]);

    $user = User::factory()->teacher()->onboarded()->create();
    $user->teacherProfile->subjects()->sync([$olMath->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $olMath), [
            'lessons' => [$lesson->id],
            'grades' => [$grade->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teacher.subjects.index'));

    expect($user->teacherProfile->fresh()->lessons()->pluck('lessons.id')->all())->toBe([$lesson->id]);

    $pivot = $user->teacherProfile->fresh()->subjects()->where('subjects.id', $olMath->id)->first()->pivot;

    expect($pivot->grade_levels)->toBe([(string) $grade->id]);
});

test('teachers cannot configure a subject they do not teach', function () {
    $math = Subject::factory()->create();
    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [])
        ->assertForbidden();
});
