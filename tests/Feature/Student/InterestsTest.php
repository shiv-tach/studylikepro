<?php

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\User;

/**
 * An onboarded student plus a subject and a lesson that both live in the
 * student's level and grade.
 *
 * @return array{0: User, 1: Subject, 2: Lesson}
 */
function studentWithGradeSubjectAndLesson(): array
{
    $user = User::factory()->student()->onboarded()->create();
    $grade = $user->studentProfile->grade;

    $subject = Subject::factory()->create(['education_level_id' => $grade->education_level_id]);
    $lesson = Lesson::factory()->for($subject)->create(['grade_id' => $grade->id]);

    return [$user, $subject, $lesson];
}

test('the interests page requires onboarding', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.interests.edit'))
        ->assertRedirect(route('student.onboarding.show'));
});

test('students can save subject and lesson interests', function () {
    [$user, $subject, $lesson] = studentWithGradeSubjectAndLesson();

    $this->actingAs($user)
        ->put(route('student.interests.update'), [
            'subjects' => [$subject->id],
            'lessons' => [$lesson->id],
        ])
        ->assertRedirect(route('student.interests.edit'));

    expect($user->fresh()->interestedSubjects()->pluck('subjects.id')->all())->toBe([$subject->id])
        ->and($user->fresh()->interestedLessons()->pluck('lessons.id')->all())->toBe([$lesson->id]);
});

test('lessons from unselected subjects are dropped on save', function () {
    [$user, $subject] = studentWithGradeSubjectAndLesson();
    $grade = $user->studentProfile->grade;

    $otherSubject = Subject::factory()->create(['education_level_id' => $grade->education_level_id]);
    $otherLesson = Lesson::factory()->for($otherSubject)->create(['grade_id' => $grade->id]);

    $this->actingAs($user)
        ->put(route('student.interests.update'), [
            'subjects' => [$subject->id],
            'lessons' => [$otherLesson->id],
        ])
        ->assertRedirect();

    expect($user->fresh()->interestedLessons()->count())->toBe(0);
});

test('lessons from another grade are dropped on save', function () {
    [$user, $subject] = studentWithGradeSubjectAndLesson();
    $grade = $user->studentProfile->grade;

    $otherGrade = Grade::factory()->create(['education_level_id' => $grade->education_level_id]);
    $otherLesson = Lesson::factory()->for($subject)->create(['grade_id' => $otherGrade->id]);

    $this->actingAs($user)
        ->put(route('student.interests.update'), [
            'subjects' => [$subject->id],
            'lessons' => [$otherLesson->id],
        ])
        ->assertRedirect();

    expect($user->fresh()->interestedLessons()->count())->toBe(0);
});

test('subjects from another level are rejected', function () {
    [$user] = studentWithGradeSubjectAndLesson();
    $foreignSubject = Subject::factory()->create();

    $this->actingAs($user)
        ->put(route('student.interests.update'), ['subjects' => [$foreignSubject->id]])
        ->assertSessionHasErrors('subjects.0');
});

test('interests reject inactive subjects', function () {
    $inactive = Subject::factory()->inactive()->create();
    $user = User::factory()->student()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('student.interests.update'), ['subjects' => [$inactive->id]])
        ->assertSessionHasErrors('subjects.0');
});

test('teachers cannot update student interests', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->put(route('student.interests.update'), ['subjects' => []])
        ->assertForbidden();
});
