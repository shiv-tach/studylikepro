<?php

use App\Enums\ApprovalStatus;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\User;

/**
 * An approved O/L subject with two priced grades and two selected lessons, the
 * shape the workspace island is built from.
 *
 * @return array<string, mixed>
 */
function subjectsPageScenario(): array
{
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade6 = Grade::query()->where('education_level_id', $ol->id)->where('number', 6)->firstOrFail();
    $grade10 = Grade::query()->where('education_level_id', $ol->id)->where('number', 10)->firstOrFail();

    $subject = Subject::factory()->create([
        'name' => 'Subjects Page Mathematics',
        'slug' => 'subjects-page-mathematics',
        'education_level_id' => $ol->id,
        'icon' => '📐',
    ]);

    $lesson6 = Lesson::factory()->for($subject)->create([
        'grade_id' => $grade6->id,
        'name' => 'Fractions',
    ]);
    $lesson10 = Lesson::factory()->for($subject)->create([
        'grade_id' => $grade10->id,
        'name' => 'Quadratics',
    ]);

    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;
    $profile->update(['hourly_rate_minor' => 100000]);

    $profile->subjects()->attach($subject->id, [
        'status' => ApprovalStatus::Approved,
        'grade_levels' => [(string) $grade6->id, (string) $grade10->id],
        'rate_per_hour_minor' => 120000,
        'grade_rates' => [$grade6->id => 150000, $grade10->id => 250000],
    ]);

    $profile->lessons()->sync([$lesson6->id, $lesson10->id]);

    return compact('user', 'profile', 'subject', 'grade6', 'grade10', 'lesson6', 'lesson10', 'ol');
}

test('the subjects page renders the workspace island and mount point', function () {
    ['user' => $user] = subjectsPageScenario();

    $this->actingAs($user)
        ->get(route('teacher.subjects.index'))
        ->assertOk()
        ->assertSee('id="teacher-subjects-config"', false)
        ->assertSee('id="teacher-subjects-app"', false)
        ->assertSee('teacher-subjects/main.js', false);
});

test('the island carries the approved subject setup', function () {
    ['user' => $user, 'subject' => $subject, 'grade6' => $grade6, 'grade10' => $grade10, 'lesson6' => $lesson6] = subjectsPageScenario();

    $config = teacherSubjectsConfig(
        $this->actingAs($user)->get(route('teacher.subjects.index'))
    );

    $payload = $config['subjects'][0];
    $grades = collect($payload['grades'])->keyBy('id');
    $grade6Lessons = collect($grades[$grade6->id]['lessons'])->keyBy('id');

    expect($payload['name'])->toBe($subject->name)
        ->and($payload['updateUrl'])->toBe(route('teacher.subjects.update', $subject))
        ->and($payload['defaultRate'])->toBe(1200)
        ->and($payload['counts']['selectedGrades'])->toBe(2)
        ->and($payload['counts']['selectedLessons'])->toBe(2)
        ->and($payload['counts']['totalLessons'])->toBe(2)
        ->and($grades[$grade6->id]['rate'])->toBe(1500)
        ->and($grades[$grade6->id]['selected'])->toBeTrue()
        ->and($grades[$grade10->id]['rate'])->toBe(2500)
        ->and($grades[$grade10->id]['selected'])->toBeTrue()
        ->and($grade6Lessons[$lesson6->id]['selected'])->toBeTrue()
        ->and($config['teacher']['baseRateLabel'])->toContain('1,000')
        ->and($config['initialTab'])->toBe('my');
});

test('the catalog offers rejected subjects again but not claimed ones', function () {
    ['user' => $user, 'profile' => $profile, 'subject' => $approved, 'ol' => $ol] = subjectsPageScenario();

    $pendingSubject = Subject::factory()->create([
        'name' => 'Pending Physics',
        'education_level_id' => $ol->id,
    ]);
    $rejectedSubject = Subject::factory()->create([
        'name' => 'Rejected Chemistry',
        'education_level_id' => $ol->id,
    ]);

    $profile->subjects()->attach($pendingSubject->id, ['status' => ApprovalStatus::Pending]);
    $profile->subjects()->attach($rejectedSubject->id, ['status' => ApprovalStatus::Rejected]);

    $config = teacherSubjectsConfig(
        $this->actingAs($user)->get(route('teacher.subjects.index'))
    );

    $availableIds = collect($config['catalog']['levels'])
        ->flatMap(fn (array $level) => collect($level['subjects'])->pluck('id'))
        ->all();

    expect($availableIds)->toContain($rejectedSubject->id)
        ->and($availableIds)->not->toContain($approved->id)
        ->and($availableIds)->not->toContain($pendingSubject->id)
        ->and($config['rejected'][0]['name'])->toBe('Rejected Chemistry')
        ->and($config['pending'][0]['name'])->toBe('Pending Physics');
});

test('pending requests carry a cancel url and rejected requests keep the admin note', function () {
    ['user' => $user, 'profile' => $profile, 'ol' => $ol] = subjectsPageScenario();

    $catalogSubject = Subject::factory()->create([
        'name' => 'Catalog Biology',
        'education_level_id' => $ol->id,
    ]);

    $pending = $profile->subjectRequests()->create([
        'subject_id' => $catalogSubject->id,
        'status' => ApprovalStatus::Pending,
    ]);

    $profile->subjectRequests()->create([
        'proposed_name' => 'Astronomy',
        'education_level_id' => $ol->id,
        'admin_note' => 'Not in the syllabus yet.',
        'status' => ApprovalStatus::Rejected,
    ]);

    $config = teacherSubjectsConfig(
        $this->actingAs($user)->get(route('teacher.subjects.index'))
    );

    $requestRow = collect($config['pending'])->firstWhere('kind', 'request');

    expect($requestRow['name'])->toBe('Catalog Biology')
        ->and($requestRow['cancelUrl'])->toBe(route('teacher.subjects.request.cancel', $pending))
        ->and($config['rejected'][0]['name'])->toBe('Astronomy')
        ->and($config['rejected'][0]['note'])->toBe('Not in the syllabus yet.')
        ->and($config['rejected'][0]['reapplySection'])->toBe('propose');
});

test('a rejected setup save reopens the same subject with its submitted values', function () {
    ['user' => $user, 'subject' => $subject, 'grade6' => $grade6, 'lesson6' => $lesson6] = subjectsPageScenario();

    $alGrade = Grade::query()->where('number', 12)->firstOrFail();

    $this->actingAs($user)
        ->from(route('teacher.subjects.index'))
        ->post(route('teacher.subjects.update', $subject), [
            'sync_grades' => '1',
            'editing_subject' => $subject->id,
            'grades' => [$grade6->id],
            'grade_rates' => [
                $grade6->id => 1700,
                $alGrade->id => 2600,
            ],
            'lessons' => [$lesson6->id],
            'rate_per_hour' => 1300,
        ])
        ->assertSessionHasErrors('grade_rates');

    $config = teacherSubjectsConfig(
        $this->actingAs($user)->get(route('teacher.subjects.index'))
    );

    $payload = $config['subjects'][0];
    $replayedGrade = collect($payload['grades'])->firstWhere('id', $grade6->id);

    expect($config['editingSubjectId'])->toBe($subject->id)
        ->and($config['initialTab'])->toBe('my')
        ->and($payload['errors']['gradeRates'])->not->toBeEmpty()
        ->and($payload['defaultRate'])->toBe(1300)
        ->and($replayedGrade['rate'])->toBe(1700)
        ->and($replayedGrade['selected'])->toBeTrue()
        ->and(collect($payload['grades'])->where('selected', true))->toHaveCount(1)
        ->and($replayedGrade['lessons'][0]['selected'])->toBeTrue();
});

test('a saved subject reopens its card after the redirect', function () {
    ['user' => $user, 'subject' => $subject, 'grade6' => $grade6] = subjectsPageScenario();

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $subject), [
            'sync_grades' => '1',
            'editing_subject' => $subject->id,
            'grades' => [$grade6->id],
            'rate_per_hour' => 1200,
        ])
        ->assertRedirect(route('teacher.subjects.index'));

    $config = teacherSubjectsConfig(
        $this->actingAs($user)->get(route('teacher.subjects.index'))
    );

    expect($config['openSubjectId'])->toBe($subject->id)
        ->and($config['initialTab'])->toBe('my');
});

test('a rejected subject can be requested again', function () {
    ['user' => $user, 'profile' => $profile, 'ol' => $ol] = subjectsPageScenario();

    $rejectedSubject = Subject::factory()->create([
        'name' => 'Rejected Geography',
        'education_level_id' => $ol->id,
    ]);

    $profile->subjects()->attach($rejectedSubject->id, ['status' => ApprovalStatus::Rejected]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.add'), ['subject_ids' => [$rejectedSubject->id]])
        ->assertRedirect(route('teacher.subjects.index'))
        ->assertSessionHas('status', 'subjects-requested');

    expect($profile->subjectRequests()
        ->where('subject_id', $rejectedSubject->id)
        ->where('status', ApprovalStatus::Pending)
        ->exists())->toBeTrue();
});

test('a duplicate proposal comes back to the propose form with its values', function () {
    ['user' => $user, 'ol' => $ol] = subjectsPageScenario();

    Subject::factory()->create([
        'name' => 'Advanced ICT',
        'education_level_id' => $ol->id,
    ]);

    $this->actingAs($user)
        ->from(route('teacher.subjects.index'))
        ->post(route('teacher.subjects.request'), [
            'proposed_name' => 'Advanced ICT',
            'education_level_id' => $ol->id,
            'note' => 'I teach this at school.',
        ])
        ->assertSessionHasErrors('proposed_name');

    $config = teacherSubjectsConfig(
        $this->actingAs($user)->get(route('teacher.subjects.index'))
    );

    expect($config['initialTab'])->toBe('add')
        ->and($config['addSection'])->toBe('propose')
        ->and($config['propose']['values']['name'])->toBe('Advanced ICT')
        ->and($config['propose']['values']['levelId'])->toBe($ol->id)
        ->and($config['propose']['values']['note'])->toBe('I teach this at school.')
        ->and($config['propose']['errors']['name'])->not->toBeEmpty();
});

test('a flash status becomes a toast notice on the island', function () {
    ['user' => $user] = subjectsPageScenario();

    $config = teacherSubjectsConfig(
        $this->actingAs($user)
            ->withSession(['status' => 'subject-setup-updated'])
            ->get(route('teacher.subjects.index'))
    );

    expect($config['notices'])->toHaveCount(1)
        ->and($config['notices'][0]['tone'])->toBe('emerald')
        ->and($config['notices'][0]['message'])->toBe('Teaching preferences saved.');
});

test('an approved subject without a saved scope opens with its level grades', function () {
    ['user' => $user, 'profile' => $profile, 'subject' => $subject, 'ol' => $ol] = subjectsPageScenario();

    // A legacy approval: approved pivot, no grade scope, no lessons picked.
    $profile->subjects()->updateExistingPivot($subject->id, ['grade_levels' => null]);
    $profile->lessons()->sync([]);

    $config = teacherSubjectsConfig(
        $this->actingAs($user)->get(route('teacher.subjects.index'))
    );

    $levelGradeIds = Grade::query()
        ->where('education_level_id', $ol->id)
        ->where('is_active', true)
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    $payload = collect($config['subjects'])->firstWhere('id', $subject->id);

    expect(array_column($payload['grades'], 'id'))->toEqualCanonicalizing($levelGradeIds)
        ->and(collect($payload['grades'])->where('selected', true))->toHaveCount(count($levelGradeIds))
        ->and($payload['counts']['selectedGrades'])->toBe(count($levelGradeIds));
});
