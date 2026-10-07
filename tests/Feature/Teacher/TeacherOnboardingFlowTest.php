<?php

use App\Models\TeacherVerificationDocument;
use App\Models\User;

test('a teacher who only finished step 1 is sent back to verification', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.verification'))
        ->assertSessionHas('status', 'complete-your-verification');

    $this->actingAs($teacher)
        ->get(route('teacher.subjects.index'))
        ->assertRedirect(route('teacher.verification'));
});

test('the redirect explains that verification unlocks the teacher area', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->followingRedirects()
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Finish your verification to unlock your dashboard');
});

test('submitting verification unlocks the teacher area', function () {
    $teacher = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($teacher->teacherProfile)->create();

    $this->actingAs($teacher)
        ->post(route('teacher.verification.submit'), ['agree' => '1'])
        ->assertRedirect(route('teacher.verification'));

    $teacher->refresh()->unsetRelation('teacherProfile');

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk();
});

test('the verification step stays locked until the profile is saved', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('0 of 2 steps completed')
        ->assertSee('Step 2 · Locked', false)
        ->assertSee('Unlocks once you save your profile.');
});

test('the profile page shows both steps and links to the next one', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('Teacher setup')
        ->assertSee('1 of 2 steps completed')
        ->assertSee('Step 1 · Completed', false)
        ->assertSee('Step 2 · Up next', false)
        ->assertSee('Continue to verification')
        ->assertSee(route('teacher.verification'));
});

test('the verification page links back to the completed profile step', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.verification'))
        ->assertOk()
        ->assertSee('1 of 2 steps completed')
        ->assertSee('Step 1 · Completed', false)
        ->assertSee('Step 2 · In progress', false)
        ->assertSee(route('teacher.profile'));
});

test('submitted teachers see both steps completed and a dashboard link', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.verification'))
        ->assertOk()
        ->assertSee('2 of 2 steps completed')
        ->assertSee('Step 2 · Completed', false)
        ->assertSee('Go to dashboard');
});

test('the sidebar shows the two setup steps while onboarding', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('Teaching profile')
        ->assertSee('Verification')
        ->assertDontSee('Earnings');
});

test('the sidebar shows the full teacher menu once onboarding is complete', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('Earnings');
});
