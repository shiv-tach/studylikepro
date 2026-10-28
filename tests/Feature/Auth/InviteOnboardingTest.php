<?php

use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherInvite;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use App\Support\PendingRegistration;
use App\Support\PendingTeacherSignup;

/**
 * The invite link opens the teacher onboarding wizard as a guest: the three
 * answer steps are filled in first and the account — with its profile, weekly
 * hours and photo — is created only when the SMS code is confirmed.
 */

test('an invite link opens the guest onboarding wizard instead of a sign-up form', function () {
    [, $token] = makeTeacherInvite();

    $this->get('/register?invite='.$token)
        ->assertRedirect(route('join.start'));

    $this->get(route('join.start'))
        ->assertOk()
        ->assertSee('No account needed yet')
        ->assertSee('Create your account');

    expect(User::count())->toBe(0)
        ->and(PendingTeacherSignup::token())->toBe($token);
});

test('an invalid invite link still shows the error on the register page', function () {
    $this->get('/register?invite=does-not-exist')
        ->assertOk()
        ->assertSee('onboarding link is invalid', false);
});

test('a used invite cannot start the wizard', function () {
    [$invite, $token] = makeTeacherInvite();
    $invite->forceFill(['consumed_at' => now()])->save();

    $this->get('/register?invite='.$token)
        ->assertOk()
        ->assertSee('onboarding link is invalid', false);
});

test('the guest steps need the invite the visitor arrived with', function () {
    $this->get(route('join.profile'))->assertRedirect(route('register'));

    [, $token] = makeTeacherInvite();

    // A token in the session that has since expired sends them back to the
    // entry page with the explanation, not to a bare 403.
    $this->get('/register?invite='.$token);
    TeacherInvite::query()->update(['expires_at' => now()->subDay()]);

    $this->get(route('join.profile'))
        ->assertRedirect(route('register', ['invite' => $token]));
});

test('the wizard fills in every step before the account exists', function () {
    [, $token] = makeTeacherInvite();

    $this->get('/register?invite='.$token)->assertRedirect(route('join.start'));

    $this->get(route('join.profile'))
        ->assertOk()
        ->assertSee('teacher-profile-config', false);

    $this->put(route('join.profile.update'), [
        'headline' => 'Physics tutor for O/L students',
        'bio' => 'Ten years of classroom experience.',
        'experience_years' => 10,
        'education' => 'B.Sc. Physics, University of Colombo',
    ])->assertRedirect(route('join.preferences'));

    $this->get(route('join.preferences'))
        ->assertOk()
        ->assertSee('teacher-profile-config', false);

    $this->put(route('join.preferences.update'), [
        'languages' => ['English', 'Sinhala'],
        'hourly_rate' => 2500,
    ])->assertRedirect(route('join.availability'));

    $this->get(route('join.availability'))
        ->assertOk()
        ->assertSee('teacher-availability-setup-config', false);

    $this->put(route('join.availability.update'), [
        'days' => [
            ['value' => 1, 'enabled' => 1, 'ranges' => [['start_time' => '15:00', 'end_time' => '18:00']]],
        ],
    ])->assertRedirect(route('join.account'));

    $this->get(route('join.account'))
        ->assertOk()
        ->assertSee('Create your account')
        ->assertSee('name="password"', false);

    // Nothing has been written to `users` yet.
    expect(User::count())->toBe(0)
        ->and(PendingTeacherSignup::isComplete())->toBeTrue();
});

test('a step cannot be skipped before the previous one is saved', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->get(route('join.preferences'))
        ->assertRedirect(route('join.profile'))
        ->assertSessionHas('status', 'complete-your-profile');

    $this->get(route('join.availability'))
        ->assertRedirect(route('join.preferences'));

    $this->get(route('join.account'))->assertRedirect(route('join.profile'));
});

test('the availability step needs at least one weekly range', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.profile.update'), [
        'headline' => 'Physics tutor',
        'experience_years' => 3,
        'education' => 'B.Sc. Physics',
    ]);

    $this->put(route('join.preferences.update'), [
        'languages' => ['English'],
        'hourly_rate' => 2500,
    ]);

    $this->from(route('join.availability'))->put(route('join.availability.update'), [
        'days' => [
            ['value' => 1, 'enabled' => 0],
        ],
    ])->assertSessionHasErrors('days');

    expect(PendingTeacherSignup::hasStep('availability'))->toBeFalse();
});

test('the account is created only once the texted code is confirmed', function () {
    [, $token] = makeTeacherInvite();
    $invite = TeacherInvite::firstOrFail();
    $account = joinThroughInvite($token);

    // The details wait for the passcode; no account exists yet.
    $this->assertGuest();

    expect(User::count())->toBe(0)
        ->and(PendingRegistration::get())->not->toBeNull()
        ->and(PendingRegistration::get()['onboarding'])->not->toBeNull();

    $messages = FakeSmsGateway::messages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]['to'])->toBe('0771234567');

    $this->post(route('join.account.verify'), ['code' => FakeSmsGateway::lastCode()])
        ->assertRedirect(route('teacher.onboarding.verification'));

    $this->assertAuthenticated();

    $user = User::where('email', $account['email'])->firstOrFail();

    expect($user->hasRole(User::ROLE_TEACHER))->toBeTrue()
        ->and($user->mobile)->toBe('0771234567')
        ->and($user->mobile_verified_at)->not->toBeNull()
        // The wizard's answers are now the profile.
        ->and($user->teacherProfile)->not->toBeNull()
        ->and($user->teacherProfile->headline)->toBe('Physics tutor for O/L students')
        ->and($user->teacherProfile->hourly_rate_minor)->toBe(250000)
        ->and($user->teacherProfile->languages)->toBe(['English', 'Sinhala'])
        ->and($user->teacherProfile->isComplete())->toBeTrue()
        ->and($user->teacherProfile->availabilitySlots()->count())->toBe(1)
        ->and($user->hasCompletedOnboarding())->toBeFalse();

    // The single-use link is spent.
    expect($invite->fresh()->consumed_at)->not->toBeNull()
        ->and(PendingTeacherSignup::get())->toBeNull();

    // Verification is where the signed-in wizard resumes.
    $this->get(route('teacher.onboarding.verification'))->assertOk();
});

test('the availability week from the wizard is bookable', function () {
    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    $this->post(route('join.account.verify'), ['code' => FakeSmsGateway::lastCode()]);

    $profile = TeacherProfile::firstOrFail();
    $slot = TeacherAvailabilitySlot::firstOrFail();

    expect($slot->teacher_profile_id)->toBe($profile->id)
        ->and($slot->day_of_week)->toBe(1)
        ->and($slot->startLabel())->toBe('15:00')
        ->and($slot->endLabel())->toBe('18:00');
});

test('the code step can be reopened on the same url', function () {
    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    $this->get(route('join.account'))
        ->assertOk()
        ->assertSee('Confirm your mobile number')
        ->assertSee('077 ••• 45 67')
        ->assertSee('name="code"', false);
});

test('changing the details drops the passcode but keeps the answers', function () {
    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    $this->post(route('join.account.edit'))
        ->assertRedirect(route('join.account'));

    expect(PendingRegistration::get())->toBeNull()
        ->and(PendingTeacherSignup::isComplete())->toBeTrue();

    $this->get(route('join.account'))
        ->assertOk()
        ->assertSee('Create your account');
});

test('an email that is already registered is caught before the code is sent', function () {
    User::factory()->student()->create(['email' => 'taken@example.com']);

    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.profile.update'), [
        'headline' => 'Physics tutor',
        'experience_years' => 3,
        'education' => 'B.Sc. Physics',
    ]);
    $this->put(route('join.preferences.update'), [
        'languages' => ['English'],
        'hourly_rate' => 2500,
    ]);
    $this->put(route('join.availability.update'), [
        'days' => [
            ['value' => 2, 'enabled' => 1, 'ranges' => [['start_time' => '09:00', 'end_time' => '12:00']]],
        ],
    ]);

    $this->from(route('join.account'))
        ->post(route('join.account.store'), [
            'first_name' => 'Amara',
            'last_name' => 'Silva',
            'email' => 'taken@example.com',
            'mobile' => '077 123 4567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors('email');

    expect(FakeSmsGateway::messages())->toBeEmpty()
        ->and(User::count())->toBe(1);
});

test('the account is created immediately when mobile verification is switched off', function () {
    platform_settings()->set('sms_otp_enabled', '0');

    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.profile.update'), [
        'headline' => 'Chemistry tutor',
        'experience_years' => 5,
        'education' => 'B.Sc. Chemistry',
    ]);
    $this->put(route('join.preferences.update'), [
        'languages' => ['English'],
        'hourly_rate' => 3000,
    ]);
    $this->put(route('join.availability.update'), [
        'days' => [
            ['value' => 3, 'enabled' => 1, 'ranges' => [['start_time' => '16:00', 'end_time' => '19:00']]],
        ],
    ]);

    $this->post(route('join.account.store'), [
        'first_name' => 'Amara',
        'last_name' => 'Silva',
        'email' => 'amara@example.com',
        'mobile' => '077 123 4567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('teacher.onboarding.verification'));

    $this->assertAuthenticated();

    $user = User::where('email', 'amara@example.com')->firstOrFail();

    expect($user->hasRole(User::ROLE_TEACHER))->toBeTrue()
        ->and($user->teacherProfile?->headline)->toBe('Chemistry tutor')
        ->and($user->teacherProfile?->availabilitySlots()->count())->toBe(1);

    expect(FakeSmsGateway::messages())->toBeEmpty();
});
