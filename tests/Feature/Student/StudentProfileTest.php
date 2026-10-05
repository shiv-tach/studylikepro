<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('new students are redirected to their profile from the dashboard', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.dashboard'))
        ->assertRedirect(route('student.profile'));
});

test('the profile page shows the onboarding form for new students', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.profile'))
        ->assertOk()
        ->assertSee('Complete your profile');
});

test('students can complete their profile', function () {
    $user = User::factory()->student()->create();

    $response = $this->actingAs($user)->put(route('student.profile.update'), [
        'grade_level' => 'high_school',
        'timezone' => 'Asia/Kolkata',
        'learning_goals' => 'Prepare for board exams',
        'guardian_name' => 'Priya Sharma',
        'guardian_phone' => '+91 98765 43210',
    ]);

    $response->assertRedirect(route('student.dashboard'));

    $profile = $user->fresh()->studentProfile;

    expect($profile)->not->toBeNull()
        ->and($profile->completed_at)->not->toBeNull()
        ->and($profile->grade_level)->toBe('high_school')
        ->and($profile->guardian_name)->toBe('Priya Sharma');

    $this->get(route('student.dashboard'))->assertOk();
});

test('completing the profile validates its inputs', function () {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->put(route('student.profile.update'), [
            'grade_level' => 'not-a-grade',
            'timezone' => 'Mars/Olympus',
        ])
        ->assertSessionHasErrors(['grade_level', 'timezone']);

    expect($user->fresh()->studentProfile)->toBeNull();
});

test('students can update their profile after onboarding', function () {
    $user = User::factory()->student()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('student.profile.update'), [
            'grade_level' => 'college',
            'timezone' => 'Asia/Dubai',
            'learning_goals' => 'Calculus and linear algebra',
        ])
        ->assertRedirect(route('student.profile'));

    expect($user->fresh()->studentProfile->grade_level)->toBe('college');
});

test('students can upload an avatar and the old file is removed', function () {
    Storage::fake('public');

    $user = User::factory()->student()->onboarded()->create(['avatar_path' => 'avatars/old.jpg']);
    Storage::disk('public')->put('avatars/old.jpg', 'old-avatar');

    $this->actingAs($user)
        ->put(route('student.profile.update'), [
            'grade_level' => 'high_school',
            'timezone' => 'Asia/Kolkata',
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ])
        ->assertRedirect(route('student.profile'));

    $path = $user->fresh()->avatar_path;

    expect($path)->not->toBe('avatars/old.jpg');
    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertMissing('avatars/old.jpg');
});

test('avatar uploads are validated', function () {
    Storage::fake('public');

    $user = User::factory()->student()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('student.profile.update'), [
            'grade_level' => 'high_school',
            'timezone' => 'Asia/Kolkata',
            'avatar' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');
});

test('teachers cannot access student profile routes', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('student.profile'))
        ->assertForbidden();
});
