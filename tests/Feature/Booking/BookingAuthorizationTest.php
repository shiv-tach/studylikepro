<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TeacherProfile;
use App\Models\User;

it('sends guests to login for every booking surface', function () {
    $booking = ownedBooking();

    $this->get(route('student.bookings.index'))->assertRedirect(route('login'));
    $this->get(route('student.bookings.show', $booking))->assertRedirect(route('login'));
    $this->post(route('student.bookings.cancel', $booking))->assertRedirect(route('login'));
    $this->get(route('teacher.schedule.index'))->assertRedirect(route('login'));
});

it('hides a booking from everybody who is not part of it', function () {
    $booking = ownedBooking();
    $stranger = User::factory()->student()->onboarded()->create();
    $otherTeacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($stranger)
        ->get(route('student.bookings.show', $booking))
        ->assertStatus(403);

    $this->actingAs($otherTeacher)
        ->get(route('teacher.bookings.show', $booking))
        ->assertStatus(403);
});

it('shows the booking to the student and the teacher involved', function () {
    $booking = ownedBooking();

    $this->actingAs($booking->student)
        ->get(route('student.bookings.show', $booking))
        ->assertOk();

    $this->actingAs($booking->teacherProfile->user)
        ->get(route('teacher.bookings.show', $booking))
        ->assertOk();
});

it('only lets the owning student pay for their own hold', function () {
    $booking = ownedBooking();
    $stranger = User::factory()->student()->onboarded()->create();

    $this->actingAs($stranger)
        ->post(route('student.bookings.checkout', $booking))
        ->assertStatus(403);

    $this->actingAs($booking->student)
        ->post(route('student.bookings.checkout', $booking))
        ->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->payments()->count())->toBe(1);
});

it('refuses checkout for a booking that is no longer a hold', function () {
    $booking = ownedBooking(BookingStatus::Cancelled);

    $this->actingAs($booking->student)
        ->post(route('student.bookings.checkout', $booking))
        ->assertStatus(403);

    expect($booking->payments()->count())->toBe(0);
});

it('closes checkout once the payment window has lapsed', function () {
    $booking = ownedBooking(overrides: ['expires_at' => now()->subMinute()]);

    $this->actingAs($booking->student)
        ->post(route('student.bookings.checkout', $booking))
        ->assertSessionHasErrors('status');

    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and($booking->payments()->count())->toBe(0);
});

it('stops a teacher acting on somebody else lesson', function () {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addMinutes(5),
        'ends_at' => now()->addMinutes(65),
    ]);

    $otherTeacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($otherTeacher)
        ->post(route('teacher.bookings.start', $booking))
        ->assertStatus(403);

    $this->actingAs($otherTeacher)
        ->post(route('teacher.bookings.complete', $booking))
        ->assertStatus(403);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('refuses to start a lesson a student has not paid for', function () {
    $booking = Booking::factory()->hold()->create([
        'starts_at' => now()->addMinutes(5),
        'ends_at' => now()->addMinutes(65),
    ]);

    $this->actingAs($booking->teacherProfile->user)
        ->post(route('teacher.bookings.start', $booking))
        ->assertStatus(403);
});

it('keeps the admin console away from students and teachers', function () {
    $booking = Booking::factory()->hold()->create();

    $this->actingAs($booking->student)->get(route('admin.bookings.index'))->assertStatus(403);
    $this->actingAs($booking->teacherProfile->user)->get(route('admin.bookings.index'))->assertStatus(403);
    $this->actingAs($booking->student)->post(route('admin.bookings.cancel', $booking))->assertStatus(403);
});

it('refuses direct bookings with a teacher who is not approved', function () {
    $draftTeacher = TeacherProfile::factory()->create();
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->get(route('student.bookings.create', $draftTeacher))
        ->assertStatus(403);
});
