<?php

use App\Enums\BookingStatus;
use App\Enums\DisputeStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Review;
use App\Models\User;

it('turns every admin screen away from students and teachers', function () {
    $scenario = paidBookingScenario();
    $dispute = Dispute::factory()->create([
        'booking_id' => $scenario['booking']->id,
        'raised_by' => $scenario['student']->id,
        'against_id' => $scenario['teacherUser']->id,
    ]);
    Review::factory()->create([
        'booking_id' => $scenario['booking']->id,
        'student_id' => $scenario['student']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
    ]);

    $urls = [
        route('admin.dashboard'),
        route('admin.reports.index'),
        route('admin.reports.export', ['type' => 'bookings']),
        route('admin.users.index'),
        route('admin.users.show', $scenario['student']),
        route('admin.moderation.index'),
        route('admin.disputes.index'),
        route('admin.disputes.show', $dispute),
        route('admin.bookings.index'),
        route('admin.bookings.show', $scenario['booking']),
        route('admin.payments.index'),
        route('admin.payments.export'),
        route('admin.payments.show', $scenario['payment']),
        route('admin.activity.index'),
        route('admin.offers.index'),
        route('admin.settings.edit'),
    ];

    foreach ([User::factory()->student()->create(), User::factory()->teacher()->create()] as $outsider) {
        foreach ($urls as $url) {
            $this->actingAs($outsider)->get($url)->assertForbidden();
        }
    }
});

it('refuses state changing admin routes to non-admins', function () {
    $teacher = User::factory()->teacher()->create();
    $booking = Booking::factory()->create();

    $this->actingAs($teacher)
        ->post(route('admin.bookings.cancel', $booking), ['reason' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($teacher)
        ->post(route('admin.users.suspend', $booking->student), ['reason' => 'Nope'])
        ->assertForbidden();

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->student->refresh()->isSuspended())->toBeFalse();
});

it('shows the console navigation with the live queues', function () {
    $admin = User::factory()->admin()->create();

    Dispute::factory()->create(['status' => DisputeStatus::Open]);
    Review::factory()->flagged()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('Users')
        ->assertSee('Disputes')
        ->assertSee('Moderation')
        ->assertSee('Activity log')
        ->assertSee(route('admin.disputes.index'))
        ->assertSee(route('admin.reports.index'));

    expect(ActivityLog::query()->count())->toBe(0);
});

it('summarises money and attention items on the dashboard', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario();
    $scenario['booking']->status = BookingStatus::Completed;
    $scenario['booking']->save();

    Dispute::factory()->create(['status' => DisputeStatus::Open]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Open disputes')
        ->assertSee('Collected (30 days)')
        ->assertSee(platform_settings()->formatMinor($scenario['payment']->amount_minor));
});
