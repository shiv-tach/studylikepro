<?php

use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use App\Models\User;

test('a new teacher lands on the dashboard, not a wizard', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Teacher Dashboard');
});

test('a teacher without a profile is sent to the profile form', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.subjects.index'))
        ->assertRedirect(route('teacher.profile'))
        ->assertSessionHas('status', 'complete-your-profile');
});

test('a teacher with a profile is sent to verification', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.subjects.index'))
        ->assertRedirect(route('teacher.verification'))
        ->assertSessionHas('status', 'complete-your-verification');
});

test('verification is open before any weekly range is saved', function () {
    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->for($teacher)->create();

    $this->actingAs($teacher)
        ->get(route('teacher.verification'))
        ->assertOk()
        ->assertSee('id="teacher-verification-app"', false);
});

test('submitting moves the teacher to the approval status page', function () {
    $teacher = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($teacher->teacherProfile)->create();

    $this->actingAs($teacher)
        ->post(route('teacher.verification.submit'), ['agree' => '1'])
        ->assertRedirect(route('teacher.approval.status'))
        ->assertSessionHas('status', 'submitted-for-review');

    $teacher->refresh()->unsetRelation('teacherProfile');

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.approval.status'))
        ->assertSessionHas('status', 'approval-pending');
});

test('an approved teacher reaches the teacher workspace', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk();
});

test('the sidebar shows the setup menu before approval and the full menu after', function () {
    $pending = onboardingTeacher();
    $approved = User::factory()->teacher()->onboarded()->create();

    $pendingNav = collect(
        shellConfig($this->actingAs($pending)->get(route('teacher.dashboard'))->assertOk())['navItems']
    )->pluck('label')->all();

    $approvedNav = collect(
        shellConfig($this->actingAs($approved)->get(route('teacher.dashboard'))->assertOk())['navItems']
    )->pluck('label')->all();

    expect($pendingNav)->toContain('Finish setup', 'Teaching profile', 'Availability')
        ->not->toContain('Earnings')
        ->and($approvedNav)->toContain('Earnings', 'Services & pricing')
        ->not->toContain('Finish setup');
});

test('the removed onboarding URLs are gone', function () {
    $teacher = onboardingTeacher();

    foreach (['/teacher/onboarding', '/teacher/onboarding/profile', '/teacher/onboarding/verification'] as $url) {
        $this->actingAs($teacher)->get($url)->assertNotFound();
    }

    $this->actingAs($teacher)
        ->patchJson('/teacher/onboarding/draft', [])
        ->assertNotFound();
});
