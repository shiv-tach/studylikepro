<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('new teachers are redirected to their profile from the dashboard', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.profile'));
});

test('the profile page shows the onboarding form for new teachers', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('Set up your teaching profile');
});

test('teachers can complete their profile', function () {
    $user = User::factory()->teacher()->create();

    $response = $this->actingAs($user)->put(route('teacher.profile.update'), [
        'headline' => 'Maths tutor for high school',
        'bio' => 'I focus on building strong fundamentals.',
        'experience_years' => 5,
        'education' => 'M.Sc. Mathematics, Delhi University',
        'languages' => ['English', 'Sinhala'],
        'hourly_rate' => 750,
    ]);

    $response->assertRedirect(route('teacher.verification'));

    $profile = $user->fresh()->teacherProfile;

    expect($profile)->not->toBeNull()
        ->and($profile->completed_at)->not->toBeNull()
        ->and($profile->hourly_rate_minor)->toBe(75000)
        ->and($profile->languages)->toBe(['English', 'Sinhala'])
        ->and($profile->timezone)->toBe('Asia/Colombo')
        ->and($profile->verification_status->value)->toBe('draft');

    // Step 2 is still open, so the teacher area stays locked until the documents are submitted.
    $this->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.verification'))
        ->assertSessionHas('status', 'complete-your-verification');
});

test('updating an existing profile redirects back to the profile', function () {
    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('teacher.profile.update'), [
            'headline' => 'Physics and maths tutor',
            'experience_years' => 8,
            'education' => 'B.Tech',
            'languages' => ['English'],
            'hourly_rate' => 900,
        ])
        ->assertRedirect(route('teacher.profile'));

    expect($user->fresh()->teacherProfile->headline)->toBe('Physics and maths tutor');
});

test('teacher profile validation rejects bad input', function () {
    $user = User::factory()->teacher()->create();

    $this->actingAs($user)
        ->put(route('teacher.profile.update'), [
            'headline' => '',
            'experience_years' => -2,
            'languages' => [],
            'hourly_rate' => 50,
        ])
        ->assertSessionHasErrors(['headline', 'experience_years', 'languages', 'hourly_rate']);
});

test('teachers can upload an avatar', function () {
    Storage::fake('public');

    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('teacher.profile.update'), [
            'headline' => 'Chemistry tutor',
            'experience_years' => 3,
            'education' => 'B.Sc. Chemistry',
            'languages' => ['English'],
            'hourly_rate' => 500,
            'avatar' => UploadedFile::fake()->image('photo.png', 300, 300),
        ])
        ->assertRedirect(route('teacher.profile'));

    $path = $user->fresh()->avatar_path;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('students cannot access teacher profile routes', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('teacher.profile'))
        ->assertForbidden();
});
