<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('new students are sent to the onboarding wizard from the dashboard', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.dashboard'))
        ->assertRedirect(route('student.onboarding.show'));
});

test('the learning profile is for students who finished onboarding', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.profile'))
        ->assertRedirect(route('student.onboarding.show'));

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('student.profile'))
        ->assertOk()
        ->assertSee('My Learning Profile');
});

test('students can update their profile after onboarding', function () {
    $user = User::factory()->student()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('student.profile.update'), [
            'grade_id' => gradeId(13),
            'learning_language' => 'English',
            'learning_goals' => 'Calculus and linear algebra',
        ])
        ->assertRedirect(route('student.profile'));

    $profile = $user->fresh()->studentProfile;

    expect($profile->grade_id)->toBe(gradeId(13))
        ->and($profile->learning_language)->toBe('English')
        ->and($profile->learning_goals)->toBe('Calculus and linear algebra');
});

test('the profile rejects a learning language the platform does not teach in', function () {
    $user = User::factory()->student()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('student.profile.update'), [
            'grade_id' => gradeId(11),
            'learning_language' => 'Tamil',
        ])
        ->assertSessionHasErrors('learning_language');
});

test('completing the profile validates its inputs', function () {
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->put(route('student.profile.update'), [
            'grade_id' => 99999,
        ])
        ->assertSessionHasErrors(['grade_id']);

    expect($user->fresh()->studentProfile)->toBeNull();
});

test('students can upload an avatar and the old file is removed', function () {
    Storage::fake('public');

    $user = User::factory()->student()->onboarded()->create(['avatar_path' => 'avatars/old.jpg']);
    Storage::disk('public')->put('avatars/old.jpg', 'old-avatar');

    $this->actingAs($user)
        ->put(route('student.profile.update'), [
            'grade_id' => gradeId(11),
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
            'grade_id' => gradeId(11),
            'avatar' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');
});

test('teachers cannot access student profile routes', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('student.profile'))
        ->assertForbidden();
});
