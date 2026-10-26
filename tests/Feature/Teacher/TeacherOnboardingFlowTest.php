<?php

use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use App\Models\User;

test('a teacher who saved the profile but not the week is sent to the availability step', function () {
    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->for($teacher)->create();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.onboarding.availability'))
        ->assertSessionHas('status', 'complete-your-availability');
});

test('a teacher who finished the profile and week is sent to the verification step', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.onboarding.verification'))
        ->assertSessionHas('status', 'complete-your-verification');

    $this->actingAs($teacher)
        ->get(route('teacher.subjects.index'))
        ->assertRedirect(route('teacher.onboarding.verification'));
});

test('the verification step explains what is left before the teacher area unlocks', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->followingRedirects()
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('Two things and you are in');
});

test('submitting verification unlocks the teacher area', function () {
    $teacher = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($teacher->teacherProfile)->create();

    $this->actingAs($teacher)
        ->post(route('teacher.verification.submit'), ['agree' => '1', 'return' => 'onboarding'])
        ->assertRedirect(route('teacher.onboarding.submitted'))
        ->assertSessionHas('status', 'submitted-for-review');

    $teacher->refresh()->unsetRelation('teacherProfile');

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk();
});

test('the welcome screen shows the resume point and the remaining steps', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.onboarding.show'))
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('Continue where you left off: Verification')
        ->assertSee('3 of 4 steps completed');
});

test('the onboarding layout replaces the dashboard chrome', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.onboarding.show'))
        ->assertOk()
        ->assertSee('Teacher setup')
        ->assertSee('Save &amp; exit', false)
        ->assertDontSee('id="studylikepro-shell"', false);
});

test('every wizard step uses the onboarding chrome instead of the dashboard', function () {
    $teacher = onboardingTeacher();

    foreach ([
        route('teacher.onboarding.show'),
        route('teacher.onboarding.preferences'),
        route('teacher.onboarding.availability'),
        route('teacher.onboarding.verification'),
    ] as $url) {
        $this->actingAs($teacher)
            ->get($url)
            ->assertOk()
            ->assertDontSee('id="studylikepro-shell"', false);
    }
});

test('the sidebar shows the full teacher menu once onboarding is complete', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('Earnings');
});

test('a finished teacher is sent out of the wizard', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.onboarding.show'))
        ->assertRedirect(route('teacher.dashboard'));

    $this->actingAs($teacher)
        ->get(route('teacher.onboarding.profile'))
        ->assertRedirect(route('teacher.profile'));
});
