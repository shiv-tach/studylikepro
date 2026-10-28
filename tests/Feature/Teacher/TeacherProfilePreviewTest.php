<?php

use App\Enums\ApprovalStatus;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * @return array{teacher: User, subject: Subject}
 */
function previewTeacherWithSubject(ApprovalStatus $status): array
{
    $teacher = User::factory()->teacher()->awaitingApproval()->create();

    // The public page lists subjects through their grades, so the subject needs
    // an education level that actually has grades.
    $level = \App\Models\EducationLevel::query()->where('key', 'ol')->first()
        ?? \App\Models\EducationLevel::factory()->create(['key' => 'ol']);

    $subject = Subject::factory()->create([
        'is_active' => true,
        'education_level_id' => $level->id,
    ]);

    $teacher->teacherProfile->subjects()->attach($subject->id, [
        'status' => $status,
        'grade_levels' => [],
    ]);

    return ['teacher' => $teacher, 'subject' => $subject];
}

/**
 * @return array<string, mixed>
 */
function publicTeacherConfig(TestResponse $response): array
{
    preg_match('#<script type="application/json" id="public-teacher-config">(.*?)</script>#s', $response->getContent(), $matches);

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

test('a pending teacher can preview the public page, watermarked and unbookable', function () {
    ['teacher' => $teacher, 'subject' => $subject] = previewTeacherWithSubject(ApprovalStatus::Pending);

    $response = $this->actingAs($teacher)
        ->get(route('teacher.profile.preview'))
        ->assertOk()
        ->assertSee('Preview only — your profile is not public yet')
        ->assertSee('noindex,nofollow', false);

    $config = publicTeacherConfig($response);

    expect($config['preview'])->toBeTrue()
        ->and($config['booking']['canBook'])->toBeFalse()
        ->and($config['booking']['action']['label'])->toBe('Not bookable yet')
        ->and($config['teacher']['verifiedLabel'])->toBe('Awaiting approval')
        ->and($config['grades']['count'])->toBeGreaterThan(0)
        ->and($response->getContent())->toContain($subject->name);
});

test('rejected subjects are left out of the preview', function () {
    ['teacher' => $teacher] = previewTeacherWithSubject(ApprovalStatus::Rejected);

    $response = $this->actingAs($teacher)
        ->get(route('teacher.profile.preview'))
        ->assertOk();

    expect(publicTeacherConfig($response)['grades']['count'])->toBe(0);
});

test('an approved teacher keeps the same preview route', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.profile.preview'))
        ->assertOk()
        ->assertSee('Preview only — your profile is not public yet');
});

test('the real public page still hides a teacher who is not approved', function () {
    ['teacher' => $teacher] = previewTeacherWithSubject(ApprovalStatus::Pending);

    $this->get(route('teachers.show', $teacher->teacherProfile))->assertNotFound();
});

test('guests cannot reach the preview route', function () {
    $this->get(route('teacher.profile.preview'))->assertRedirect(route('login'));
});

test('a teacher without a profile is sent back to setup', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.profile.preview'))
        ->assertRedirect(route('teacher.onboarding.show'));
});
