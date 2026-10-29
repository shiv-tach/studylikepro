<?php

use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a new teacher lands on the dashboard', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.dashboard'))
        ->assertOk();
});

test('the profile page is open to a teacher who has not saved a profile yet', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.profile'))
        ->assertOk();
});

test('the profile page ships the live preview of the public teacher card', function () {
    $user = User::factory()->teacher()->onboarded()->create(['name' => 'Nimal Perera']);
    $subject = Subject::query()->active()->first() ?? Subject::factory()->create(['is_active' => true]);

    $user->teacherProfile->subjects()->attach($subject->id, ['grade_levels' => []]);

    $response = $this->actingAs($user)->get(route('teacher.profile'))->assertOk();

    $response->assertSee('id="teacher-profile-app"', false);

    $config = teachingProfileConfig($response);

    expect($config['teacher']['name'])->toBe('Nimal Perera')
        ->and($config['teacher']['initials'])->toBe('Ni')
        ->and($config['values']['headline'])->toBe($user->teacherProfile->headline)
        ->and($config['values']['hourlyRate'])->toBe((int) $user->teacherProfile->hourlyRate())
        ->and($config['preview']['subjects'])->toHaveCount(1)
        ->and($config['preview']['currencySymbol'])->not->toBe('')
        ->and($config['preview']['verificationLabel'])->toBe('Verified')
        ->and($config['steps'])->toHaveCount(4)
        ->and($config['urls']['update'])->toBe(route('teacher.profile.update'))
        ->and($config['urls']['availability'])->toBe(route('teacher.availability.index'));
});

test('a rejected save hands the answers and messages back to the preview', function () {
    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->from(route('teacher.profile'))
        ->put(route('teacher.profile.update'), [
            'headline' => '',
            'experience_years' => -2,
            'languages' => [],
            'hourly_rate' => 50,
        ])
        ->assertSessionHasErrors(['headline', 'experience_years', 'languages', 'hourly_rate']);

    $config = teachingProfileConfig($this->actingAs($user)->get(route('teacher.profile'))->assertOk());

    expect($config['values']['hourlyRate'])->toBe(50)
        ->and($config['values']['headline'])->toBe('')
        ->and($config['errors']['headline'])->not->toBeEmpty()
        ->and($config['errors']['languages'])->not->toBeEmpty();
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

    $response->assertRedirect(route('teacher.profile'));

    $profile = $user->fresh()->teacherProfile;

    expect($profile)->not->toBeNull()
        ->and($profile->completed_at)->not->toBeNull()
        ->and($profile->hourly_rate_minor)->toBe(75000)
        ->and($profile->languages)->toBe(['English', 'Sinhala'])
        ->and($profile->timezone)->toBe('Asia/Colombo')
        ->and($profile->verification_status->value)->toBe('draft');

    // Only the documents are left: the verification page is where they are added.
    $this->get(route('teacher.verification'))
        ->assertOk()
        ->assertSee('id="teacher-verification-app"', false);
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

test('avatar uploads accept files up to 5 MB', function () {
    Storage::fake('public');

    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('teacher.profile.update'), [
            'headline' => 'Chemistry tutor',
            'experience_years' => 3,
            'education' => 'B.Sc. Chemistry',
            'languages' => ['English'],
            'hourly_rate' => 500,
            'avatar' => UploadedFile::fake()->image('photo.jpg', 300, 300)->size(4096),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teacher.profile'));

    expect($user->fresh()->avatar_path)->not->toBeNull();
});

test('avatar uploads reject files larger than 5 MB', function () {
    Storage::fake('public');

    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('teacher.profile.update'), [
            'headline' => 'Chemistry tutor',
            'experience_years' => 3,
            'education' => 'B.Sc. Chemistry',
            'languages' => ['English'],
            'hourly_rate' => 500,
            'avatar' => UploadedFile::fake()->image('photo.jpg', 300, 300)->size(6144),
        ])
        ->assertSessionHasErrors('avatar');

    expect($user->fresh()->avatar_path)->toBeNull();
});

test('students cannot access teacher profile routes', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('teacher.profile'))
        ->assertForbidden();
});
