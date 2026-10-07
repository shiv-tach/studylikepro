<?php

use App\Models\EducationLevel;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use App\Models\User;
use App\Services\RequestMatcher;
use Carbon\CarbonImmutable;

/**
 * O/L Mathematics with a Grade 6 lesson, plus an evening window two days out
 * that the teachers below are available in.
 *
 * @return array<string, mixed>
 */
function gradeMatchScenario(): array
{
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();

    $subject = Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'ol-mathematics',
        'education_level_id' => $level->id,
    ]);

    $lesson = Lesson::factory()->create([
        'subject_id' => $subject->id,
        'grade_id' => gradeId(6),
        'name' => 'Numbers',
        'slug' => 'numbers',
    ]);

    $windowStart = CarbonImmutable::now('UTC')->addDays(2)->setTime(18, 0);

    return compact('level', 'subject', 'lesson', 'windowStart');
}

/**
 * An approved teacher of the scenario lesson, scoped to the given grade numbers.
 *
 * @param  array<string, mixed>  $scenario
 * @param  list<int>  $gradeNumbers
 */
function gradeScopedTeacher(array $scenario, array $gradeNumbers, string $name): TeacherProfile
{
    $user = User::factory()->teacher()->create(['name' => $name]);

    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $user->id,
        'timezone' => 'UTC',
    ]);

    $teacher->subjects()->attach($scenario['subject']->id, [
        'grade_levels' => array_map(fn (int $number) => (string) gradeId($number), $gradeNumbers),
    ]);
    $teacher->lessons()->attach($scenario['lesson']->id);

    TeacherAvailabilitySlot::factory()
        ->on($scenario['windowStart']->dayOfWeek, '18:00', '21:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    return $teacher;
}

/**
 * A request for the scenario lesson in the given grade (null = legacy request).
 *
 * @param  array<string, mixed>  $scenario
 */
function gradeMatchedRequest(array $scenario, ?int $gradeNumber): TutoringRequest
{
    $windowStart = $scenario['windowStart'];

    return TutoringRequest::factory()->create([
        'subject_id' => $scenario['subject']->id,
        'lesson_id' => $scenario['lesson']->id,
        'grade_id' => $gradeNumber === null ? null : gradeId($gradeNumber),
        'preferred_windows' => [[
            'starts_at' => $windowStart->toIso8601String(),
            'ends_at' => $windowStart->setTime(20, 0)->toIso8601String(),
        ]],
    ]);
}

it('matches only the teachers whose subject scope covers the request grade', function () {
    $scenario = gradeMatchScenario();

    $inScope = gradeScopedTeacher($scenario, [6], 'Grade 6 Teacher');
    gradeScopedTeacher($scenario, [7], 'Grade 7 Teacher');

    $matches = app(RequestMatcher::class)->teachersFor(gradeMatchedRequest($scenario, 6));

    expect($matches->pluck('id')->all())->toBe([$inScope->id]);
});

it('matches any teacher of the lesson when the request has no grade', function () {
    $scenario = gradeMatchScenario();

    $gradeSix = gradeScopedTeacher($scenario, [6], 'Grade 6 Teacher');
    $gradeSeven = gradeScopedTeacher($scenario, [7], 'Grade 7 Teacher');

    $matches = app(RequestMatcher::class)->teachersFor(gradeMatchedRequest($scenario, null));

    expect($matches->pluck('id')->all())->toBe([$gradeSix->id, $gradeSeven->id]);
});

it('keeps a teacher out of the inbox for a grade they do not teach', function () {
    $scenario = gradeMatchScenario();

    $teacher = gradeScopedTeacher($scenario, [6], 'Grade 6 Teacher');

    $matching = gradeMatchedRequest($scenario, 6);
    $matching->update(['description' => 'Grade six numbers question.']);

    $other = gradeMatchedRequest($scenario, 7);
    $other->update(['description' => 'Grade seven numbers question.']);

    $inbox = app(RequestMatcher::class)->inboxFor($teacher);

    expect($inbox->pluck('id')->all())->toBe([$matching->id]);
});

it('keeps the inbox open to legacy requests without a grade', function () {
    $scenario = gradeMatchScenario();

    $teacher = gradeScopedTeacher($scenario, [6], 'Grade 6 Teacher');

    $legacy = gradeMatchedRequest($scenario, null);

    $inbox = app(RequestMatcher::class)->inboxFor($teacher);

    expect($inbox->pluck('id')->all())->toBe([$legacy->id]);
});
