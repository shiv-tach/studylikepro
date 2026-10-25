<?php

use App\Models\TeacherInvite;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use App\Support\PendingRegistration;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;

/**
 * The registration payload used by most tests: a student with a valid number.
 *
 * @return array<string, string>
 */
function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Nimal',
        'last_name' => 'Perera',
        'email' => 'nimal@example.com',
        'mobile' => '076 982 88 74',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('submitting the form texts a code without creating an account', function () {
    $this->post('/register', registrationPayload())
        ->assertRedirect(route('register.verify.show'))
        ->assertSessionHas('status', 'otp-sent');

    $this->assertGuest();
    expect(User::where('email', 'nimal@example.com')->exists())->toBeFalse();

    $messages = FakeSmsGateway::messages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]['to'])->toBe('0769828874')
        ->and(FakeSmsGateway::lastCode())->toMatch('/^\d{6}$/');

    expect(PendingRegistration::get())->not->toBeNull()
        ->and(PendingRegistration::get()['mobile'])->toBe('0769828874');
});

test('the verify screen masks the pending number', function () {
    $this->post('/register', registrationPayload());

    $this->get(route('register.verify.show'))
        ->assertOk()
        ->assertSee('076 ••• 88 74')
        ->assertSee('name="code"', false);
});

test('the verify screen sends visitors back when there is nothing pending', function () {
    $this->get(route('register.verify.show'))
        ->assertRedirect(route('register'));
});

test('entering the code creates and signs in the account', function () {
    $this->post('/register', registrationPayload());

    $code = FakeSmsGateway::lastCode();

    $this->post(route('register.verify.store'), ['code' => $code])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $student = User::where('email', 'nimal@example.com')->first();

    expect($student)->not->toBeNull()
        ->and($student->hasRole(User::ROLE_STUDENT))->toBeTrue()
        ->and($student->mobile)->toBe('0769828874')
        ->and($student->name)->toBe('Nimal Perera')
        ->and($student->hasVerifiedMobile())->toBeTrue()
        // The password was hashed when the pending registration was stored, so
        // the model's hashed cast must not double-hash it.
        ->and(Hash::check('password', $student->password))->toBeTrue();

    expect(PendingRegistration::get())->toBeNull();
});

test('a wrong code is rejected and the account is not created', function () {
    $this->post('/register', registrationPayload());

    $wrong = str_pad((string) (((int) FakeSmsGateway::lastCode() + 1) % 1000000), 6, '0', STR_PAD_LEFT);

    $this->from(route('register.verify.show'))
        ->post(route('register.verify.store'), ['code' => $wrong])
        ->assertRedirect(route('register.verify.show'))
        ->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(User::where('email', 'nimal@example.com')->exists())->toBeFalse()
        ->and(PendingRegistration::get()['attempts'])->toBe(1);
});

test('too many wrong codes discard the pending registration', function () {
    $this->post('/register', registrationPayload());

    $wrong = str_pad((string) (((int) FakeSmsGateway::lastCode() + 1) % 1000000), 6, '0', STR_PAD_LEFT);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $response = $this->from(route('register.verify.show'))
            ->post(route('register.verify.store'), ['code' => $wrong]);
    }

    $response->assertSessionHasErrors('code');
    expect(PendingRegistration::get())->toBeNull();

    // Even the right code no longer works once the pending registration is gone.
    $this->get(route('register.verify.show'))->assertRedirect(route('register'));
});

test('an expired code is rejected', function () {
    $this->post('/register', registrationPayload());

    $code = FakeSmsGateway::lastCode();
    $this->travel(6)->minutes();

    $this->from(route('register.verify.show'))
        ->post(route('register.verify.store'), ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(User::where('email', 'nimal@example.com')->exists())->toBeFalse();
});

test('resending is blocked during the cooldown', function () {
    $this->post('/register', registrationPayload());

    $this->from(route('register.verify.show'))
        ->post(route('register.verify.resend'))
        ->assertSessionHasErrors('code');

    expect(FakeSmsGateway::messages())->toHaveCount(1);
});

test('resending after the cooldown issues a new code and retires the old one', function () {
    $this->post('/register', registrationPayload());

    $firstCode = FakeSmsGateway::lastCode();
    $this->travel(61)->seconds();

    $this->from(route('register.verify.show'))
        ->post(route('register.verify.resend'))
        ->assertSessionHas('status', 'otp-resent');

    expect(FakeSmsGateway::messages())->toHaveCount(2);

    $secondCode = FakeSmsGateway::lastCode();
    expect($secondCode)->not->toBe($firstCode);

    // The retired code is refused.
    $this->post(route('register.verify.store'), ['code' => $firstCode])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('the resend allowance is capped', function () {
    platform_settings()->set('sms_otp_max_resends', 1);

    $this->post('/register', registrationPayload());

    $this->travel(61)->seconds();
    $this->post(route('register.verify.resend'))->assertSessionHas('status', 'otp-resent');

    $this->travel(61)->seconds();
    $this->from(route('register.verify.show'))
        ->post(route('register.verify.resend'))
        ->assertSessionHasErrors('code');
});

test('changing the number returns to the form with the details replayed', function () {
    $this->post('/register', registrationPayload());

    $this->post(route('register.cancel'))
        ->assertRedirect(route('register'))
        ->assertSessionHasInput('email', 'nimal@example.com');

    expect(PendingRegistration::get())->toBeNull();

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('nimal@example.com');
});

test('registration skips the code when an admin turns verification off', function () {
    platform_settings()->set('sms_otp_enabled', '0');

    $this->post('/register', registrationPayload())
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(FakeSmsGateway::messages())->toBeEmpty();

    $student = User::where('email', 'nimal@example.com')->first();

    expect($student)->not->toBeNull()
        ->and($student->hasVerifiedMobile())->toBeTrue();
});

test('invited teachers still verify before the invite is consumed', function () {
    [$invite, $token] = makeTeacherInvite();

    $this->post('/register', registrationPayload([
        'email' => 'teacher@example.com',
        'mobile' => '071 234 5678',
        'role' => User::ROLE_TEACHER,
        'invite' => $token,
    ]))->assertRedirect(route('register.verify.show'));

    expect($invite->fresh()->consumed_at)->toBeNull();
    $this->assertGuest();

    $this->post(route('register.verify.store'), ['code' => FakeSmsGateway::lastCode()])
        ->assertRedirect(route('dashboard', absolute: false));

    $teacher = User::where('email', 'teacher@example.com')->first();

    expect($teacher->hasRole(User::ROLE_TEACHER))->toBeTrue()
        ->and($teacher->mobile)->toBe('0712345678')
        ->and($invite->fresh()->consumed_at)->not->toBeNull();
});

test('an invite consumed during verification sends the visitor back to the form', function () {
    [$invite, $token] = makeTeacherInvite();

    $this->post('/register', registrationPayload([
        'email' => 'teacher@example.com',
        'mobile' => '071 234 5678',
        'role' => User::ROLE_TEACHER,
        'invite' => $token,
    ]));

    // Another visitor used the link while this one was reading the SMS.
    TeacherInvite::findUsable($token)->forceFill(['consumed_at' => now()])->save();

    $this->post(route('register.verify.store'), ['code' => FakeSmsGateway::lastCode()])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('invite');

    expect(User::where('email', 'teacher@example.com')->exists())->toBeFalse();
    expect(PendingRegistration::get())->toBeNull();
});

test('a mobile registered during verification sends the visitor back to the form', function () {
    $this->post('/register', registrationPayload());

    // Someone else claims the number while the code is in flight.
    User::factory()->create(['mobile' => '0769828874']);

    $this->post(route('register.verify.store'), ['code' => FakeSmsGateway::lastCode()])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('mobile');

    expect(User::where('email', 'nimal@example.com')->exists())->toBeFalse();
});

test('a provider failure keeps the form and offers a resend', function () {
    FakeSmsGateway::fail();

    $this->post('/register', registrationPayload())
        ->assertRedirect(route('register.verify.show'))
        ->assertSessionHas('status', 'otp-send-failed');

    $this->assertGuest();
    expect(PendingRegistration::get())->not->toBeNull();

    // The cooldown is untouched, so the visitor can retry immediately.
    FakeSmsGateway::fail(false);

    $this->from(route('register.verify.show'))
        ->post(route('register.verify.resend'))
        ->assertSessionHas('status', 'otp-resent');

    $this->post(route('register.verify.store'), ['code' => FakeSmsGateway::lastCode()])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('the message carries the app name, code and lifetime', function () {
    $this->post('/register', registrationPayload());

    $message = FakeSmsGateway::last()['message'];

    expect($message)->toContain(FakeSmsGateway::lastCode())
        ->and($message)->toContain((string) config('app.name'))
        ->and($message)->toContain('5 minutes')
        ->and($message)->not->toContain(':code');
});

test('the pending registration never stores the plain password or code', function () {
    $this->post('/register', registrationPayload());

    $pending = PendingRegistration::get();
    $code = FakeSmsGateway::lastCode();

    expect($pending['password'])->not->toBe('password')
        ->and($pending['code'])->not->toBe($code)
        ->and($pending)->not->toHaveKey('password_confirmation');
});

test('phone numbers expose an international form for the gateway', function () {
    expect(PhoneNumber::international('0769828874'))->toBe('+94769828874')
        ->and(PhoneNumber::international('+94 76 982 8874'))->toBe('+94769828874')
        ->and(PhoneNumber::international('nope'))->toBeNull();
});

test('phone numbers can be masked for the verify screen', function () {
    expect(PhoneNumber::mask('0769828874'))->toBe('076 ••• 88 74')
        ->and(PhoneNumber::mask('nope'))->toBeNull();
});
