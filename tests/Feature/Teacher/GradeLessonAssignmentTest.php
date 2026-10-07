<?php

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\User;

test('selecting a subject auto-assigns every active grade and lesson of its level', function () {
    $math = Subject::factory()->create();
    $grade6 = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    $grade7 = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    $inactiveGrade = Grade::factory()->create([
        'education_level_id' => $math->education_level_id,
        'is_active' => false,
    ]);

    $grade6Lesson = Lesson::factory()->for($math)->create(['grade_id' => $grade6->id]);
    $grade7Lesson = Lesson::factory()->for($math)->create(['grade_id' => $grade7->id]);
    Lesson::factory()->for($math)->create(['grade_id' => $grade6->id, 'is_active' => false]);
    Lesson::factory()->for($math)->create(['grade_id' => $inactiveGrade->id]);

    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => [$math->id]])
        ->assertRedirect(route('teacher.subjects.index'));

    $profile = $user->teacherProfile->refresh();

    expect($profile->lessons()->pluck('lessons.id')->all())
        ->toEqualCanonicalizing([$grade6Lesson->id, $grade7Lesson->id]);

    $pivot = $profile->subjects()->where('subjects.id', $math->id)->first()->pivot;

    expect($pivot->grade_levels)->toEqualCanonicalizing([
        (string) $grade6->id,
        (string) $grade7->id,
    ]);
});

test('saving the same subject selection keeps a pruned lesson selection', function () {
    $math = Subject::factory()->create();
    $grade = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    [$kept, $pruned] = Lesson::factory()->for($math)->count(2)->create(['grade_id' => $grade->id])->all();

    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => [$math->id]]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'lessons' => [$kept->id],
            'grades' => [$grade->id],
        ]);

    // Re-saving step 1 must not re-assign the lesson the teacher pruned.
    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => [$math->id]])
        ->assertRedirect(route('teacher.subjects.index'));

    expect($user->teacherProfile->fresh()->lessons()->pluck('lessons.id')->all())->toBe([$kept->id]);
});

test('pruning a grade detaches its lessons when saving', function () {
    $math = Subject::factory()->create();
    $grade6 = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    $grade7 = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    $kept = Lesson::factory()->for($math)->create(['grade_id' => $grade6->id]);
    $dropped = Lesson::factory()->for($math)->create(['grade_id' => $grade7->id]);

    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;

    $profile->subjects()->sync([$math->id]);
    $profile->lessons()->sync([$kept->id, $dropped->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'lessons' => [$kept->id, $dropped->id],
            'grades' => [$grade6->id],
        ])
        ->assertRedirect(route('teacher.subjects.index'));

    $profile->refresh();

    expect($profile->lessons()->pluck('lessons.id')->all())->toBe([$kept->id]);

    $pivot = $profile->subjects()->where('subjects.id', $math->id)->first()->pivot;

    expect($pivot->grade_levels)->toBe([(string) $grade6->id]);
});

test('unchecking every grade detaches all lessons', function () {
    $math = Subject::factory()->create();
    $grade = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    $lesson = Lesson::factory()->for($math)->create(['grade_id' => $grade->id]);

    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;

    $profile->subjects()->sync([$math->id]);
    $profile->lessons()->sync([$lesson->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'sync_grades' => '1',
            'grades' => [],
            'lessons' => [$lesson->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teacher.subjects.index'));

    $profile->refresh();

    expect($profile->lessons()->count())->toBe(0);

    $pivot = $profile->subjects()->where('subjects.id', $math->id)->first()->pivot;

    expect($pivot->grade_levels)->toBe([]);
});

test('a lesson from another level is rejected', function () {
    $olMath = Subject::factory()->create();
    $alPhysics = Subject::factory()->create();
    $alLesson = Lesson::factory()->for($alPhysics)->create();

    $user = User::factory()->teacher()->onboarded()->create();

    $user->teacherProfile->subjects()->sync([$olMath->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $olMath), ['lessons' => [$alLesson->id]])
        ->assertSessionHasErrors('lessons.0');
});

test('a partial update without grades leaves lessons and grade scope alone', function () {
    $math = Subject::factory()->create();
    $grade = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    $lesson = Lesson::factory()->for($math)->create(['grade_id' => $grade->id]);

    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;

    $profile->subjects()->sync([$math->id => ['grade_levels' => [(string) $grade->id]]]);
    $profile->lessons()->sync([$lesson->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'lessons' => [$lesson->id],
            'rate_per_hour' => 900,
        ])
        ->assertRedirect(route('teacher.subjects.index'));

    $profile->refresh();

    expect($profile->lessons()->pluck('lessons.id')->all())->toBe([$lesson->id]);

    $pivot = $profile->subjects()->where('subjects.id', $math->id)->first()->pivot;

    expect($pivot->grade_levels)->toBe([(string) $grade->id])
        ->and($pivot->rate_per_hour_minor)->toBe(90000);
});

test('re-adding a subject re-assigns its grade and lesson defaults', function () {
    $math = Subject::factory()->create();
    $grade6 = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    $grade7 = Grade::factory()->create(['education_level_id' => $math->education_level_id]);
    $grade6Lesson = Lesson::factory()->for($math)->create(['grade_id' => $grade6->id]);
    $grade7Lesson = Lesson::factory()->for($math)->create(['grade_id' => $grade7->id]);

    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => [$math->id]]);

    // Prune to a single grade-lesson pair, then drop the subject entirely.
    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'lessons' => [$grade6Lesson->id],
            'grades' => [$grade6->id],
        ]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => []]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => [$math->id]])
        ->assertRedirect(route('teacher.subjects.index'));

    $profile = $user->teacherProfile->refresh();

    expect($profile->lessons()->pluck('lessons.id')->all())
        ->toEqualCanonicalizing([$grade6Lesson->id, $grade7Lesson->id]);

    $pivot = $profile->subjects()->where('subjects.id', $math->id)->first()->pivot;

    expect($pivot->grade_levels)->toEqualCanonicalizing([
        (string) $grade6->id,
        (string) $grade7->id,
    ]);
});
