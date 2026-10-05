<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

it('lists upcoming lessons and unpaid holds for the teacher', function () {
    $teacherUser = User::factory()->teacher()->onboarded()->create();
    $teacher = $teacherUser->teacherProfile;

    $lesson = Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'learner_name' => 'Aarav Mehta',
    ]);

    $hold = Booking::factory()->hold()->create([
        'teacher_profile_id' => $teacher->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
        'learner_name' => 'Diya Kapoor',
    ]);

    $this->actingAs($teacherUser)
        ->get(route('teacher.schedule.index'))
        ->assertOk()
        ->assertSee('Aarav Mehta')
        ->assertSee('Diya Kapoor')
        ->assertSee('Awaiting payment')
        ->assertSee('Upcoming lessons');
});

it('shows the day earnings after the platform fee on a lesson page', function () {
    $booking = Booking::factory()->create();

    $this->actingAs($booking->teacherProfile->user)
        ->get(route('teacher.bookings.show', $booking))
        ->assertOk()
        ->assertSee('595')
        ->assertSee('Platform fee (15%)');
});

it('starts a lesson from the teacher schedule', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addMinutes(10),
        'ends_at' => now()->addMinutes(70),
    ]);

    $this->actingAs($booking->teacherProfile->user)
        ->post(route('teacher.bookings.start', $booking))
        ->assertRedirect(route('teacher.bookings.show', $booking))
        ->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(BookingStatus::InProgress)
        ->and($booking->started_at)->not->toBeNull();
});

it('refuses to start a lesson far ahead of its start time', function () {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addHours(5),
        'ends_at' => now()->addHours(6),
    ]);

    $this->actingAs($booking->teacherProfile->user)
        ->post(route('teacher.bookings.start', $booking))
        ->assertSessionHasErrors('status');

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('marks a delivered lesson as completed', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'status' => BookingStatus::InProgress,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($booking->teacherProfile->user)
        ->post(route('teacher.bookings.complete', $booking))
        ->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed);
});

it('records a no-show from the lesson page', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($booking->teacherProfile->user)
        ->post(route('teacher.bookings.no-show', $booking))
        ->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(BookingStatus::NoShow);
});

it('shows past lessons in the teacher history', function () {
    $teacherUser = User::factory()->teacher()->onboarded()->create();

    Booking::factory()->create([
        'teacher_profile_id' => $teacherUser->teacherProfile->id,
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subWeek(),
        'ends_at' => now()->subWeek()->addHour(),
        'learner_name' => 'Kabir Singh',
    ]);

    $this->actingAs($teacherUser)
        ->get(route('teacher.schedule.index'))
        ->assertOk()
        ->assertSee('Recent history')
        ->assertSee('Kabir Singh');
});
