<?php

use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\UploadedFile;

/**
 * The named limits from config/studylikepro.php, exercised one by one. Each
 * test lowers the limit so a handful of requests is enough to trip it.
 */
it('rate limits tutoring request submissions', function () {
    config(['studylikepro.throttle.requests' => 2]);

    $student = User::factory()->student()->onboarded()->create();

    $payload = fn (string $description) => [
        'description' => $description,
        'windows' => [[
            'date' => now()->addDay()->toDateString(),
            'from' => '10:00',
            'to' => '11:00',
        ]],
    ];

    $this->actingAs($student)->post(route('student.requests.store'), $payload('Please help me with algebra before my exam.'))->assertRedirect();
    $this->actingAs($student)->post(route('student.requests.store'), $payload('Please help me with geometry before my exam.'))->assertRedirect();
    $this->actingAs($student)->post(route('student.requests.store'), $payload('Please help me with calculus before my exam.'))->assertStatus(429);
});

it('rate limits chat messages', function () {
    config(['studylikepro.throttle.messages' => 2]);

    $scenario = paidBookingScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $this->actingAs($scenario['student'])->post(route('messages.store', $conversation), ['body' => 'First'])->assertRedirect();
    $this->actingAs($scenario['student'])->post(route('messages.store', $conversation), ['body' => 'Second'])->assertRedirect();
    $this->actingAs($scenario['student'])->post(route('messages.store', $conversation), ['body' => 'Third'])->assertStatus(429);

    expect($conversation->messages()->where('body', 'Third')->exists())->toBeFalse();
});

it('rate limits uploads', function () {
    config(['studylikepro.throttle.uploads' => 2]);

    $student = User::factory()->student()->onboarded()->create();

    $update = fn () => $this->actingAs($student)->put(route('student.profile.update'), [
        'grade_level' => 'high_school',
        'timezone' => 'Asia/Kolkata',
        'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
    ]);

    $update()->assertRedirect();
    $update()->assertRedirect();
    $update()->assertStatus(429);
});

it('rate limits checkout attempts', function () {
    config(['studylikepro.throttle.checkout' => 1]);

    $booking = ownedBooking();
    $student = $booking->student;

    $this->actingAs($student)
        ->post(route('student.bookings.checkout', $booking))
        ->assertRedirect();

    $this->actingAs($student)
        ->post(route('student.bookings.checkout', $booking))
        ->assertStatus(429);
});

it('rate limits provider webhooks generously but still bounds them', function () {
    config(['studylikepro.throttle.webhooks' => 2]);

    for ($call = 1; $call <= 2; $call++) {
        $this->postJson(route('payments.webhook', ['gateway' => 'fake']), ['event' => 'payment.captured'])
            ->assertStatus(400);
    }

    $this->postJson(route('payments.webhook', ['gateway' => 'fake']), ['event' => 'payment.captured'])
        ->assertStatus(429);
});
