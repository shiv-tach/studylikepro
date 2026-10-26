<?php

use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use App\Models\User;

test('the draft endpoint stores and merges one step at a time', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->patchJson(route('teacher.onboarding.draft'), [
            'step' => 'about',
            'data' => ['headline' => 'Draft headline', 'experience_years' => 3],
        ])
        ->assertOk()
        ->assertJsonPath('step', 'about');

    $this->actingAs($teacher)
        ->patchJson(route('teacher.onboarding.draft'), [
            'step' => 'preferences',
            'data' => ['languages' => ['English'], 'hourly_rate' => 850],
        ])
        ->assertOk();

    $draft = $teacher->fresh()->onboardingDraft;

    expect($draft->payload['about']['headline'])->toBe('Draft headline')
        ->and($draft->payload['preferences']['hourly_rate'])->toBe(850)
        ->and($draft->current_step)->toBe('preferences')
        ->and($draft->saved_at)->not->toBeNull();
});

test('the about page restores the server draft', function () {
    $teacher = User::factory()->teacher()->create();

    $teacher->onboardingDraft()->create([
        'payload' => ['about' => [
            'headline' => 'Restored headline',
            'bio' => 'Restored bio',
            'experience_years' => 7,
            'education' => 'Restored education',
        ]],
        'current_step' => 'about',
        'saved_at' => now(),
    ]);

    $config = teachingProfileConfig(
        $this->actingAs($teacher)->get(route('teacher.onboarding.profile'))->assertOk()
    );

    expect($config['values']['headline'])->toBe('Restored headline')
        ->and($config['values']['bio'])->toBe('Restored bio')
        ->and($config['values']['experienceYears'])->toBe(7)
        ->and($config['draft']['restored'])->toBeTrue()
        ->and($config['draft']['savedAt'])->not->toBeNull();
});

test('an availability draft overrides the recommended preset', function () {
    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->for($teacher)->create();

    $teacher->onboardingDraft()->create([
        'payload' => ['availability' => ['days' => [
            ['value' => 1, 'enabled' => true, 'ranges' => [['start' => '09:00', 'end' => '11:00']]],
        ]]],
        'current_step' => 'availability',
        'saved_at' => now(),
    ]);

    $config = availabilitySetupConfig(
        $this->actingAs($teacher)->get(route('teacher.onboarding.availability'))->assertOk()
    );

    expect(collect($config['week'])->firstWhere('value', 1)['ranges'])
        ->toBe([['start' => '09:00', 'end' => '11:00']])
        ->and(collect($config['week'])->firstWhere('value', 2)['enabled'])->toBeFalse()
        ->and($config['draft']['restored'])->toBeTrue();
});

test('the verification step restores a drafted terms tick', function () {
    $teacher = onboardingTeacher();

    $teacher->onboardingDraft()->create([
        'payload' => ['verification' => ['agree' => 1]],
        'current_step' => 'verification',
        'saved_at' => now(),
    ]);

    $config = verificationConfig(
        $this->actingAs($teacher)->get(route('teacher.onboarding.verification'))->assertOk()
    );

    expect($config['values']['agree'])->toBeTrue()
        ->and($config['draft']['restored'])->toBeTrue();
});

test('the draft endpoint rejects an unknown step and oversized values', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->patchJson(route('teacher.onboarding.draft'), ['step' => 'welcome', 'data' => []])
        ->assertUnprocessable();

    $this->actingAs($teacher)
        ->patchJson(route('teacher.onboarding.draft'), [
            'step' => 'about',
            'data' => ['headline' => str_repeat('a', 121)],
        ])
        ->assertUnprocessable();
});

test('discarding one step keeps the other steps', function () {
    $teacher = User::factory()->teacher()->create();

    $teacher->onboardingDraft()->create([
        'payload' => [
            'about' => ['headline' => 'Keep me out'],
            'preferences' => ['hourly_rate' => 700],
        ],
        'current_step' => 'preferences',
        'saved_at' => now(),
    ]);

    $this->actingAs($teacher)
        ->deleteJson(route('teacher.onboarding.draft', ['step' => 'about']))
        ->assertOk()
        ->assertJsonPath('cleared', 'about');

    $payload = $teacher->fresh()->onboardingDraft->payload;

    expect($payload)->not->toHaveKey('about')
        ->and($payload['preferences']['hourly_rate'])->toBe(700);
});

test('completing onboarding deletes the draft', function () {
    $teacher = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($teacher->teacherProfile)->create();

    $teacher->onboardingDraft()->create([
        'payload' => ['about' => ['headline' => 'Unused']],
        'current_step' => 'about',
        'saved_at' => now(),
    ]);

    $this->actingAs($teacher)->post(route('teacher.verification.submit'), [
        'agree' => '1',
        'return' => 'onboarding',
    ]);

    $this->assertDatabaseCount('teacher_onboarding_drafts', 0);
});
