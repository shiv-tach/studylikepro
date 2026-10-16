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

test('the profile island carries the pickers, the answers and the save action', function () {
    $user = User::factory()->student()->onboarded()->create();
    $user->studentProfile->update([
        'grade_id' => gradeId(11),
        'learning_language' => 'English',
        'learning_goals' => 'Calculus and linear algebra',
    ]);

    $config = studentProfileConfig(
        $this->actingAs($user)->get(route('student.profile'))->assertOk()
    );

    $gradeIds = collect($config['levels'])->flatMap(fn (array $level) => $level['grades'])->pluck('id');

    expect($config['urls']['update'])->toBe(route('student.profile.update'))
        ->and($config['csrfToken'])->not->toBeEmpty()
        ->and($config['student']['email'])->toBe($user->email)
        ->and($config['values']['gradeId'])->toBe(gradeId(11))
        ->and($config['values']['learningLanguage'])->toBe('English')
        ->and($config['values']['learningGoals'])->toBe('Calculus and linear algebra')
        // The wizard's level → grade tree drives the picker, in order.
        ->and($gradeIds)->toContain(gradeId(11))
        ->and(collect($config['levels'])->pluck('name')->all())->not->toBeEmpty()
        // Only the languages the marketplace teaches in are offered.
        ->and(collect($config['languages'])->pluck('value')->all())->toBe(['Sinhala', 'English'])
        // The limits mirror StudentProfileRequest, so the counters agree with it.
        ->and($config['limits']['learningGoals'])->toBe(1000)
        ->and($config['errors']['gradeId'])->toBe([])
        ->and($config['status']['updated'])->toBeFalse();
});

test('a rejected save reopens the form with the answers and messages the server sent back', function () {
    $user = User::factory()->student()->onboarded()->create();
    $user->studentProfile->update([
        'grade_id' => gradeId(11),
        'learning_language' => 'English',
        'learning_goals' => 'Old goal',
    ]);

    $this->actingAs($user)
        ->from(route('student.profile'))
        ->put(route('student.profile.update'), [
            'grade_id' => gradeId(13),
            'learning_language' => 'Tamil',
            'learning_goals' => 'Calculus and linear algebra',
        ])
        ->assertRedirect(route('student.profile'))
        ->assertSessionHasErrors(['learning_language']);

    $config = studentProfileConfig(
        $this->actingAs($user)->get(route('student.profile'))->assertOk()
    );

    expect($config['values']['gradeId'])->toBe(gradeId(13))
        ->and($config['values']['learningLanguage'])->toBe('Tamil')
        ->and($config['values']['learningGoals'])->toBe('Calculus and linear algebra')
        ->and($config['errors']['learningLanguage'])->not->toBeEmpty()
        // Nothing was written: the rejected answers only came back as input.
        ->and($user->fresh()->studentProfile->learning_language)->toBe('English');
});

test('a saved profile flashes the confirmation the island renders', function () {
    $user = User::factory()->student()->onboarded()->create();

    $response = $this->actingAs($user)->followingRedirects()->put(route('student.profile.update'), [
        'grade_id' => gradeId(12),
        'learning_language' => 'Sinhala',
        'guardian_name' => 'Nimal Perera',
        'guardian_phone' => '0771234567',
    ]);

    $config = studentProfileConfig($response->assertOk());

    expect($config['status']['updated'])->toBeTrue()
        ->and($config['values']['guardianName'])->toBe('Nimal Perera')
        ->and($config['errors']['guardianPhone'])->toBe([]);
});
