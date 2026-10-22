<?php

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
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

test('the interests page shows only the student level and grade subjects as cards', function () {
    [$student, $grade, $subject, $lesson] = gradeScopedStudent();

    $al = EducationLevel::query()->where('key', 'al')->firstOrFail();
    $alSubject = Subject::factory()->create(['name' => 'Real A/L Physics', 'education_level_id' => $al->id]);

    $otherGrade = Grade::factory()->create(['education_level_id' => $grade->education_level_id]);
    Lesson::factory()->for($subject)->create([
        'grade_id' => $otherGrade->id,
        'name' => 'Grade nine geometry',
    ]);

    $this->actingAs($student)
        ->get(route('student.interests.edit'))
        ->assertOk()
        ->assertSee($subject->name)
        // The card opens the dedicated student subject page scoped to the grade.
        ->assertSee(route('student.subjects.show', ['subject' => $subject->slug, 'grade' => $grade->id]))
        ->assertDontSee($alSubject->name)
        // Lesson names live on the subject page now, not on the interests page.
        ->assertDontSee($lesson->name)
        ->assertDontSee('Grade nine geometry');
});
