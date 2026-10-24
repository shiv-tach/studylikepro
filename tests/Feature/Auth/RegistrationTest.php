<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new students can register', function () {
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'student@example.com',
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
        // The full name keeps every existing reader (exports, receipts, admin) working.
        ->and($student->name)->toBe('Test Student');
});

test('registration requires a first and last name', function () {
    $response = $this->post('/register', [
        'email' => 'noname@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['first_name', 'last_name']);
    $this->assertGuest();
    expect(User::where('email', 'noname@example.com')->exists())->toBeFalse();
});

test('new teachers can register with an invite', function () {
    [, $token] = makeTeacherInvite();

    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Teacher',
        'email' => 'teacher@example.com',
        'role' => User::ROLE_TEACHER,
        'invite' => $token,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'teacher@example.com')->first()->hasRole(User::ROLE_TEACHER))->toBeTrue();
});

test('teachers cannot register without an invite', function () {
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'Teacher',
        'email' => 'teacher@example.com',
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
    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
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
        'role' => User::ROLE_ADMIN,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('role');
    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});
