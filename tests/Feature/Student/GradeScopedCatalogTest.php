<?php

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TutoringRequest;
use App\Models\User;

/**
 * A Grade 11 (O/L) student: the catalog they see must stay inside O/L and
 * inside Grade 11.
 *
 * @return array{0: User, 1: Grade, 2: Subject, 3: Lesson}
 */
function gradeScopedStudent(): array
{
    $user = User::factory()->student()->onboarded()->create();
    $grade = Grade::query()->whereKey(gradeId(11))->firstOrFail();
    $user->studentProfile->update(['grade_id' => $grade->id]);

    $subject = Subject::factory()->create([
        'name' => 'Real O/L Mathematics',
        'education_level_id' => $grade->education_level_id,
    ]);
    $lesson = Lesson::factory()->for($subject)->create([
        'grade_id' => $grade->id,
        'name' => 'Grade eleven algebra',
    ]);

    return [$user->fresh(), $grade, $subject, $lesson];
}

test('the interests page only offers the student level and grade lessons', function () {
    [$student, $grade, $subject, $lesson] = gradeScopedStudent();

    $al = EducationLevel::query()->where('key', 'al')->firstOrFail();
    $alSubject = Subject::factory()->create(['name' => 'Real A/L Physics', 'education_level_id' => $al->id]);

    $otherGrade = Grade::factory()->create(['education_level_id' => $grade->education_level_id]);
    $otherGradeLesson = Lesson::factory()->for($subject)->create([
        'grade_id' => $otherGrade->id,
        'name' => 'Grade nine geometry',
    ]);

    $this->actingAs($student)
        ->get(route('student.interests.edit'))
        ->assertOk()
        ->assertSee($subject->name)
        ->assertSee($lesson->name)
        ->assertDontSee($alSubject->name)
        ->assertDontSee($otherGradeLesson->name);
});

test('the request page only offers the request grade subjects and lessons', function () {
    [$student, $grade, $subject, $lesson] = gradeScopedStudent();

    $al = EducationLevel::query()->where('key', 'al')->firstOrFail();
    $alSubject = Subject::factory()->create(['name' => 'Real A/L Physics', 'education_level_id' => $al->id]);

    $otherGrade = Grade::factory()->create(['education_level_id' => $grade->education_level_id]);
    $otherGradeLesson = Lesson::factory()->for($subject)->create([
        'grade_id' => $otherGrade->id,
        'name' => 'Grade nine geometry',
    ]);

    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $grade->id,
        'subject_id' => null,
        'lesson_id' => null,
    ]);

    $this->actingAs($student)
        ->get(route('student.requests.show', $request))
        ->assertOk()
        ->assertSee($subject->name)
        ->assertSee($lesson->name)
        ->assertSee('subjects and Grade 11 lessons only')
        ->assertDontSee($alSubject->name)
        ->assertDontSee($otherGradeLesson->name);
});

test('a request lesson from another grade is rejected', function () {
    [$student, $grade, $subject, $lesson] = gradeScopedStudent();

    $otherGrade = Grade::factory()->create(['education_level_id' => $grade->education_level_id]);
    $otherGradeLesson = Lesson::factory()->for($subject)->create([
        'grade_id' => $otherGrade->id,
        'name' => 'Grade nine geometry',
    ]);

    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $grade->id,
        'subject_id' => null,
        'lesson_id' => null,
    ]);

    $this->actingAs($student)
        ->put(route('student.requests.lesson.update', $request), [
            'subject_id' => $subject->id,
            'lesson_id' => $otherGradeLesson->id,
        ])
        ->assertSessionHasErrors('lesson_id');

    expect($request->fresh()->lesson_id)->toBeNull();

    $this->actingAs($student)
        ->put(route('student.requests.lesson.update', $request), [
            'subject_id' => $subject->id,
            'lesson_id' => $lesson->id,
        ])
        ->assertSessionHasNoErrors();

    expect($request->fresh()->lesson_id)->toBe($lesson->id)
        ->and($request->fresh()->grade_id)->toBe($grade->id);
});
