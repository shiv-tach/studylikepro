<?php

use App\Enums\ApprovalStatus;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;

/**
 * An approved subject row with the given grade scope, plus one lesson per
 * grade so the command's defaults have something to attach.
 *
 * @param  array<int, string>  $gradeLevels
 * @return array{profile: TeacherProfile, subject: Subject, lesson11: Lesson, lesson6: Lesson, ol: EducationLevel}
 */
function scopeBackfillScenario(array $gradeLevels): array
{
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade11 = Grade::query()->where('education_level_id', $ol->id)->where('number', 11)->firstOrFail();
    $grade6 = Grade::query()->where('education_level_id', $ol->id)->where('number', 6)->firstOrFail();

    $subject = Subject::factory()->create([
        'name' => 'Backfill Mathematics',
        'slug' => 'backfill-mathematics',
        'education_level_id' => $ol->id,
    ]);

    $lesson11 = Lesson::factory()->for($subject)->create(['grade_id' => $grade11->id, 'name' => 'Grade 11 Algebra']);
    $lesson6 = Lesson::factory()->for($subject)->create(['grade_id' => $grade6->id, 'name' => 'Grade 6 Fractions']);

    $profile = User::factory()->teacher()->onboarded()->create()->teacherProfile;
    $profile->subjects()->attach($subject->id, [
        'status' => ApprovalStatus::Approved,
        'grade_levels' => $gradeLevels,
    ]);

    return compact('profile', 'subject', 'lesson11', 'lesson6', 'ol');
}

test('the backfill gives an approved subject without a scope its level grades and lessons', function () {
    ['profile' => $profile, 'subject' => $subject, 'lesson11' => $lesson11, 'lesson6' => $lesson6, 'ol' => $ol] =
        scopeBackfillScenario([]);

    $this->artisan('studylikepro:backfill-subject-scopes')
        ->expectsOutputToContain('Repaired 1 approved subject row(s).')
        ->assertSuccessful();

    $pivot = $profile->subjects()->where('subjects.id', $subject->id)->firstOrFail()->pivot;

    $levelGradeIds = Grade::query()
        ->where('education_level_id', $ol->id)
        ->where('is_active', true)
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect(array_map('intval', $pivot->grade_levels))->toEqualCanonicalizing($levelGradeIds)
        ->and($profile->lessons()->pluck('lessons.id')->all())->toEqualCanonicalizing([$lesson11->id, $lesson6->id]);
});

test('the dry run reports the rows without writing them', function () {
    ['profile' => $profile, 'subject' => $subject] = scopeBackfillScenario([]);

    $this->artisan('studylikepro:backfill-subject-scopes', ['--dry-run' => true])
        ->expectsOutputToContain('Would repair')
        ->expectsOutputToContain('1 approved subject row(s) have no grade scope.')
        ->assertSuccessful();

    $pivot = $profile->subjects()->where('subjects.id', $subject->id)->firstOrFail()->pivot;

    expect(array_map('intval', (array) $pivot->grade_levels))->toBe([])
        ->and($profile->lessons()->count())->toBe(0);
});

test('a subject that already carries a scope is left alone', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade11 = Grade::query()->where('education_level_id', $ol->id)->where('number', 11)->firstOrFail();

    ['profile' => $profile, 'subject' => $subject, 'lesson11' => $lesson11, 'lesson6' => $lesson6] =
        scopeBackfillScenario([(string) $grade11->id]);

    // The teacher pruned their picks: only the grade 11 lesson is kept.
    $profile->lessons()->sync([$lesson11->id]);

    $this->artisan('studylikepro:backfill-subject-scopes')
        ->expectsOutputToContain('Repaired 0 approved subject row(s).')
        ->assertSuccessful();

    $pivot = $profile->subjects()->where('subjects.id', $subject->id)->firstOrFail()->pivot;

    expect(array_map('intval', $pivot->grade_levels))->toBe([$grade11->id])
        ->and($profile->lessons()->pluck('lessons.id')->all())->toBe([$lesson11->id])
        ->and($profile->lessons()->pluck('lessons.id')->all())->not->toContain($lesson6->id);
});
