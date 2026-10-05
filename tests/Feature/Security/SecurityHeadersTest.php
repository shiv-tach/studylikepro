<?php

use App\Models\User;

it('sends the hardening headers on public pages', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Permissions-Policy'))
        ->toContain('geolocation=()')
        ->toContain('microphone=(self "https://*.daily.co")');
});

it('sends the hardening headers on signed-in pages too', function () {
    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('only advertises HSTS over https', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('allows our own assets, fonts and the payment vendor in the policy', function () {
    $policy = $this->get('/')->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain('https://fonts.gstatic.com')
        ->toContain('https://checkout.razorpay.com')
        ->toContain("frame-ancestors 'self'")
        ->toContain("object-src 'none'")
        ->toContain("form-action 'self'");
});

it('can turn the content security policy off from config', function () {
    config(['studylikepro.security.csp_enabled' => false]);

    $this->get('/')->assertHeaderMissing('Content-Security-Policy');
});

it('rate limits sign-in attempts per account', function () {
    config(['studylikepro.throttle.login' => 2]);

    $student = User::factory()->student()->create();

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $this->post('/login', ['email' => $student->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => $student->email, 'password' => 'wrong-password'])
        ->assertStatus(429);

    $this->assertGuest();
});

it('rate limits registrations per address', function () {
    config(['studylikepro.throttle.register' => 1]);

    $payload = fn (string $email) => [
        'name' => 'New Person',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => User::ROLE_STUDENT,
    ];

    $this->post('/register', $payload('first@example.test'))->assertRedirect();
    $this->post('/register', $payload('second@example.test'))->assertStatus(429);
});
