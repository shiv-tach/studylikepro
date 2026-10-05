<?php

use App\Enums\BookingStatus;
use App\Events\BookingCompleted;
use App\Models\Booking;
use App\Notifications\LessonReminder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('completes a lesson the teacher never closed once the grace period lapses', function () {
    Event::fake([BookingCompleted::class]);
    Notification::fake();

    $booking = Booking::factory()->create([
        'status' => BookingStatus::InProgress,
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subMinutes(30),
        'started_at' => now()->subHours(2),
    ]);

    $this->artisan('studylikepro:complete-lessons')->assertSuccessful();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Completed)
        ->and($booking->completed_at)->not->toBeNull();

    Event::assertDispatched(BookingCompleted::class, fn (BookingCompleted $event) => $event->automatic === true);
});

it('leaves a lesson that just ended alone until the grace period passes', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(5),
    ]);

    $this->artisan('studylikepro:complete-lessons')->assertSuccessful();

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('never auto-completes a hold that was never paid', function () {
    Notification::fake();

    $booking = Booking::factory()->hold()->create([
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHours(1),
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->artisan('studylikepro:complete-lessons')->assertSuccessful();

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);
});

it('queues one reminder per party shortly before the lesson', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addMinutes(45),
        'ends_at' => now()->addMinutes(105),
    ]);

    $this->artisan('studylikepro:send-lesson-reminders')->assertSuccessful();

    Notification::assertSentTo($booking->student, LessonReminder::class);
    Notification::assertSentTo($booking->teacherProfile->user, LessonReminder::class);

    expect($booking->refresh()->reminder_sent_at)->not->toBeNull();

    $this->artisan('studylikepro:send-lesson-reminders')->assertSuccessful();

    Notification::assertSentToTimes($booking->student, LessonReminder::class, 1);
    Notification::assertSentToTimes($booking->teacherProfile->user, LessonReminder::class, 1);
});

it('does not remind anybody about lessons that are still days away or unpaid', function () {
    Notification::fake();

    $later = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHour(),
    ]);

    $hold = Booking::factory()->hold()->create([
        'starts_at' => now()->addMinutes(30),
        'ends_at' => now()->addMinutes(90),
    ]);

    $this->artisan('studylikepro:send-lesson-reminders')->assertSuccessful();

    Notification::assertNothingSent();

    expect($later->refresh()->reminder_sent_at)->toBeNull()
        ->and($hold->refresh()->reminder_sent_at)->toBeNull();
});
