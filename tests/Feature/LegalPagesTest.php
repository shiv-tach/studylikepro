<?php

use App\Models\ContactMessage;
use App\Models\User;

it('publishes the policy pages', function (string $route, string $expect) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee($expect);
})->with([
    ['legal.privacy', 'Privacy policy'],
    ['legal.terms', 'Terms of service'],
    ['legal.refunds', 'Cancellation & refund policy'],
    ['legal.contact', 'Talk to support'],
]);

it('shows the live cancellation figures on the refund page', function () {
    platform_settings()->set('student_cancel_window_hours', 36);
    platform_settings()->set('refund_student_percent', 80);

    $this->get(route('legal.refunds'))
        ->assertOk()
        ->assertSee('36')
        ->assertSee('80');
});

it('links the policies from the public pages', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('legal.privacy'))
        ->assertSee(route('legal.terms'))
        ->assertSee(route('legal.refunds'))
        ->assertSee(route('legal.contact'));
});

it('stores a contact message and notifies the admins', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('legal.contact.send'), [
        'name' => 'Neha Sharma',
        'email' => 'neha@example.test',
        'subject' => 'Refund for Tuesday lesson',
        'message' => 'The teacher never joined the classroom and I was charged in full.',
    ])
        ->assertRedirect(route('legal.contact'))
        ->assertSessionHas('status', 'contact-sent');

    $message = ContactMessage::query()->firstOrFail();

    expect($message->name)->toBe('Neha Sharma')
        ->and($message->email)->toBe('neha@example.test')
        ->and($message->user_id)->toBeNull()
        ->and($message->isHandled())->toBeFalse();

    expect($admin->notifications()->count())->toBe(1)
        ->and(data_get($admin->notifications()->first()->data, 'title'))->toContain('Refund for Tuesday lesson');
});

it('attaches the message to the account when the sender is signed in', function () {
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)->post(route('legal.contact.send'), [
        'name' => $student->name,
        'email' => $student->email,
        'subject' => 'Question about my timetable',
        'message' => 'Could you move my Tuesday lesson an hour later in the evening?',
    ])->assertRedirect();

    expect(ContactMessage::query()->firstOrFail()->user_id)->toBe($student->id);
});

it('validates the contact form', function () {
    $this->post(route('legal.contact.send'), [
        'name' => 'N',
        'email' => 'not-an-email',
        'subject' => 'x',
        'message' => 'too short',
    ])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

    expect(ContactMessage::query()->count())->toBe(0);
});

it('rejects automated submissions that fill the honeypot', function () {
    $this->post(route('legal.contact.send'), [
        'name' => 'Spam Bot',
        'email' => 'bot@example.test',
        'subject' => 'Cheap watches',
        'message' => 'Buy our products now at a very special price just for you.',
        'website' => 'http://spam.example',
    ])->assertSessionHasErrors('website');

    expect(ContactMessage::query()->count())->toBe(0);
});

it('rate limits the contact form', function () {
    config(['studylikepro.throttle.contact' => 1]);

    $payload = [
        'name' => 'Neha Sharma',
        'email' => 'neha@example.test',
        'subject' => 'Refund question',
        'message' => 'The teacher never joined the classroom and I was charged in full.',
    ];

    $this->post(route('legal.contact.send'), $payload)->assertRedirect();
    $this->post(route('legal.contact.send'), $payload)->assertStatus(429);
});
