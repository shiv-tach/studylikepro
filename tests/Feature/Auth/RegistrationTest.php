<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new students can register', function () {
    $response = registerThroughOtp([
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'student@example.com',
        'mobile' => '076 982 88 74',
        'role' => User::ROLE_STUDENT,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $student = User::where('email', 'student@example.com')->first();

    expect($student->hasRole(User::ROLE_STUDENT))->toBeTrue()
        ->and($student->first_name)->toBe('Test')
        ->and($student->last_name)->toBe('Student')
        // Numbers are stored as the canonical ten local digits.
        ->and($student->mobile)->toBe('0769828874')
        ->and($student->mobileFormatted())->toBe('076 982 88 74')
        // The full name keeps every existing reader (exports, receipts, admin) working.
        ->and($student->name)->toBe('Test Student');
});

test('registration requires a first and last name', function () {
    $response = $this->post('/register', [
        'email' => 'noname@example.com',
        'mobile' => '0769828874',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['first_name', 'last_name']);
    $this->assertGuest();
    expect(User::where('email', 'noname@example.com')->exists())->toBeFalse();
});

test('new teachers can register with an invite', function () {
    [, $token] = makeTeacherInvite();

    $response = registerThroughOtp([
        'first_name' => 'Test',
        'last_name' => 'Teacher',
        'email' => 'teacher@example.com',
        'mobile' => '071 234 5678',
        'role' => User::ROLE_TEACHER,
        'invite' => $token,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $teacher = User::where('email', 'teacher@example.com')->first();

    expect($teacher->hasRole(User::ROLE_TEACHER))->toBeTrue()
        ->and($teacher->mobile)->toBe('0712345678');
});

test('teachers cannot register without an invite', function () {
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Teacher',
        'email' => 'teacher@example.com',
        'mobile' => '0712345678',
        'role' => User::ROLE_TEACHER,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('invite');
    $this->assertGuest();
    expect(User::where('email', 'teacher@example.com')->exists())->toBeFalse();
});

test('teachers cannot register with an unknown invite token', function () {
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Teacher',
        'email' => 'teacher@example.com',
        'mobile' => '0712345678',
        'role' => User::ROLE_TEACHER,
        'invite' => 'does-not-exist',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('invite');
    $this->assertGuest();
    expect(User::where('email', 'teacher@example.com')->exists())->toBeFalse();
});

test('teachers cannot register with a used invite', function () {
    [$invite, $token] = makeTeacherInvite();
    $invite->forceFill(['consumed_at' => now()])->save();

    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Teacher',
        'email' => 'teacher@example.com',
        'mobile' => '0712345678',
        'role' => User::ROLE_TEACHER,
        'invite' => $token,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('invite');
    $this->assertGuest();
    expect(User::where('email', 'teacher@example.com')->exists())->toBeFalse();
});

test('teachers cannot register with an expired invite', function () {
    [, $token] = makeTeacherInvite(-1);

    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Teacher',
        'email' => 'teacher@example.com',
        'mobile' => '0712345678',
        'role' => User::ROLE_TEACHER,
        'invite' => $token,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('invite');
    $this->assertGuest();
    expect(User::where('email', 'teacher@example.com')->exists())->toBeFalse();
});

test('registration without a role creates a student account', function () {
    $response = registerThroughOtp([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile' => '0771234567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'test@example.com')->first()->hasRole(User::ROLE_STUDENT))->toBeTrue();
});

test('registration rejects privileged roles', function () {
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile' => '0771234567',
        'role' => User::ROLE_ADMIN,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('role');
    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registration screen collects a mobile number', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('name="mobile"', false)
        ->assertSee('076 982 88 74');
});

test('registration requires a mobile number', function () {
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'nomobile@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('mobile');
    $this->assertGuest();
    expect(User::where('email', 'nomobile@example.com')->exists())->toBeFalse();
});

test('registration stores the international spelling of the same number', function () {
    registerThroughOtp([
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'international@example.com',
        'mobile' => '+94 76 982 8874',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'international@example.com')->first()->mobile)->toBe('0769828874');
});

test('registration rejects a number that is not a Sri Lankan mobile', function (string $mobile) {
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'badmobile@example.com',
        'mobile' => $mobile,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('mobile');
    $this->assertGuest();
    expect(User::where('email', 'badmobile@example.com')->exists())->toBeFalse();
})->with([
    'too short' => '076 982 887',
    'landline' => '011 234 5678',
    'eleven digits' => '07712345678',
    'letters' => 'not-a-number',
]);

test('registration rejects a duplicate mobile number in another format', function () {
    User::factory()->create(['mobile' => '0769828874']);

    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'duplicate@example.com',
        'mobile' => '076 982 88 74',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('mobile');
    $this->assertGuest();
    expect(User::where('email', 'duplicate@example.com')->exists())->toBeFalse();
});
