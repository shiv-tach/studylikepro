<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('Email or mobile number')
        ->assertSee('name="login"', false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can authenticate with their mobile number', function (string $format) {
    $user = User::factory()->create(['mobile' => '0769828874']);

    $this->post('/login', [
        'login' => $format,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
})->with([
    'local digits' => '0769828874',
    'spaced' => '076 982 88 74',
    'international' => '+94 76 982 8874',
    'international prefix' => '0094 76 982 8874',
]);

test('users can not authenticate with an unknown mobile number', function () {
    User::factory()->create(['mobile' => '0769828874']);

    $this->post('/login', [
        'login' => '071 111 1111',
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('users can not authenticate with an invalid login identifier', function (string $login) {
    $this->post('/login', [
        'login' => $login,
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
})->with([
    'landline' => '0112345678',
    'letters' => 'not-a-number',
]);

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('users can not authenticate a mobile number with the wrong password', function () {
    $user = User::factory()->create(['mobile' => '0769828874']);

    $this->post('/login', [
        'login' => '076 982 88 74',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
