<?php

use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use App\Models\User;

test('a teacher who saved the profile but not the week is sent to the availability step', function () {
    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->for($teacher)->create();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.availability.setup'))
        ->assertSessionHas('status', 'complete-your-availability');
});

test('a teacher who finished steps 1 and 2 is sent back to verification', function () {
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
        ->assertSee('0 of 3 steps completed')
        ->assertSee('Step 3 · Locked', false)
        ->assertSee('Unlocks once you save your profile.');
});

test('the profile page marks the first two steps done and links to verification', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('Teacher setup')
        ->assertSee('2 of 3 steps completed')
        ->assertSee('Step 1 · Completed', false)
        ->assertSee('Step 2 · Completed', false)
        ->assertSee('Step 3 · Up next', false)
        ->assertSee('Continue to verification')
        ->assertSee(route('teacher.verification'));
});

test('the verification page links back to the completed onboarding steps', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.verification'))
        ->assertOk()
        ->assertSee('2 of 3 steps completed')
        ->assertSee('Step 1 · Completed', false)
        ->assertSee('Step 2 · Completed', false)
        ->assertSee('Step 3 · In progress', false)
        ->assertSee(route('teacher.profile'))
        ->assertSee(route('teacher.availability.setup'));
});

test('submitted teachers see every step completed and a dashboard link', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $teacher->teacherProfile->availabilitySlots()->create([
        'teacher_profile_id' => $teacher->teacherProfile->id,
        'day_of_week' => 1,
        'start_minute' => 1080,
        'end_minute' => 1260,
    ]);

    $this->actingAs($teacher)
        ->get(route('teacher.verification'))
        ->assertOk()
        ->assertSee('3 of 3 steps completed')
        ->assertSee('Step 3 · Completed', false)
        ->assertSee('Go to dashboard');
});

test('the sidebar shows the three setup steps while onboarding', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('Teaching profile')
        ->assertSee('Weekly availability')
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
