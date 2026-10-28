<?php

use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use App\Models\User;

/**
 * The dedicated onboarding section: welcome -> about -> preferences ->
 * availability -> verification -> confirmation.
 */
test('a new teacher starts on the welcome screen', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.onboarding.show'))
        ->assertOk()
        ->assertSee('Teacher setup')
        ->assertSee('Start setup')
        ->assertSee('What happens in setup')
        ->assertDontSee('id="studylikepro-shell"', false);
});

test('the about step saves its own fields and unlocks preferences', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->put(route('teacher.onboarding.profile.update'), [
            'headline' => 'Maths tutor for O/L students',
            'bio' => 'I focus on building strong fundamentals.',
            'experience_years' => 5,
            'education' => 'M.Sc. Mathematics',
        ])
        ->assertRedirect(route('teacher.onboarding.preferences'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'about-saved');

    $profile = $teacher->fresh()->teacherProfile;

    expect($profile)->not->toBeNull()
        ->and($profile->headline)->toBe('Maths tutor for O/L students')
        ->and($profile->about_completed_at)->not->toBeNull()
        ->and($profile->preferences_completed_at)->toBeNull()
        ->and($profile->completed_at)->toBeNull();
});

test('the preferences step completes the profile', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)->put(route('teacher.onboarding.profile.update'), [
        'headline' => 'Physics tutor',
        'experience_years' => 4,
        'education' => 'B.Sc. Physics',
    ]);

    $this->actingAs($teacher)
        ->put(route('teacher.onboarding.preferences.update'), [
            'languages' => ['English', 'Sinhala'],
            'hourly_rate' => 900,
        ])
        ->assertRedirect(route('teacher.onboarding.availability'))
        ->assertSessionHas('status', 'preferences-saved');

    $profile = $teacher->fresh()->teacherProfile;

    expect($profile->preferences_completed_at)->not->toBeNull()
        ->and($profile->completed_at)->not->toBeNull()
        ->and($profile->hourly_rate_minor)->toBe(90000)
        ->and($profile->languages)->toBe(['English', 'Sinhala']);
});

test('preferences are locked until the about step is saved', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.onboarding.preferences'))
        ->assertRedirect(route('teacher.onboarding.profile'))
        ->assertSessionHas('status', 'complete-your-profile');
});

test('the availability step saves the week and clears its draft', function () {
    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->for($teacher)->create();

    $teacher->onboardingDraft()->create([
        'payload' => ['availability' => ['days' => []]],
        'current_step' => 'availability',
        'saved_at' => now(),
    ]);

    $this->actingAs($teacher)
        ->put(route('teacher.onboarding.availability.update'), [
            'days' => [
                ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '21:00']]],
            ],
        ])
        ->assertRedirect(route('teacher.onboarding.verification'))
        ->assertSessionHas('status', 'availability-saved');

    expect($teacher->teacherProfile->availabilitySlots()->count())->toBe(1)
        ->and($teacher->fresh()->onboardingDraft)->toBeNull();
});

test('the verification step renders inside the wizard after availability', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.onboarding.verification'))
        ->assertOk()
        ->assertSee('id="teacher-verification-app"', false)
        ->assertSee('Two things left before review')
        ->assertSee('Step 5 of 6')
        ->assertDontSee('id="studylikepro-shell"', false);
});

test('submitting from the wizard lands on the confirmation screen', function () {
    $teacher = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($teacher->teacherProfile)->create();

    $this->actingAs($teacher)
        ->post(route('teacher.verification.submit'), ['agree' => '1', 'return' => 'onboarding'])
        ->assertRedirect(route('teacher.onboarding.submitted'))
        ->assertSessionHas('status', 'submitted-for-review');

    $this->actingAs($teacher)
        ->get(route('teacher.onboarding.submitted'))
        ->assertOk()
        ->assertSee('Application received')
        ->assertSee('Where your application is')
        ->assertSee('not visible to students yet')
        ->assertSee('Preview my public profile');
});

test('the confirmation screen is guarded until the application is submitted', function () {
    $teacher = onboardingTeacher();

    $this->actingAs($teacher)
        ->get(route('teacher.onboarding.submitted'))
        ->assertRedirect(route('teacher.onboarding.verification'));
});

test('guests are redirected and students are forbidden', function () {
    $this->get(route('teacher.onboarding.show'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->student()->create())
        ->get(route('teacher.onboarding.show'))
        ->assertForbidden();
});
