<?php

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;

it('records a line for every state changing admin action', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create();

    $this->actingAs($admin)->post(route('admin.bookings.cancel', $booking), ['reason' => 'Teacher fell ill']);

    $entry = ActivityLog::query()->firstOrFail();

    expect($entry->user_id)->toBe($admin->id)
        ->and($entry->action)->toBe('admin.bookings.cancel')
        ->and($entry->subject_type)->toBe(Booking::class)
        ->and($entry->subject_id)->toBe($booking->id)
        ->and($entry->description)->toContain('Cancelled booking #'.$booking->id)
        ->and($entry->ip_address)->not->toBeNull();
});

it('ignores reads and requests that never took effect', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $student), [])
        ->assertSessionHasErrors('reason');

    expect(ActivityLog::query()->count())->toBe(0)
        ->and($student->refresh()->isSuspended())->toBeFalse();
});

it('redacts secrets from the logged input', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($admin)->post(route('admin.users.suspend', $student), [
        'reason' => 'Chargeback',
        'password' => 'super-secret',
        'payload' => '{"card":"4111"}',
    ]);

    $entry = ActivityLog::query()->firstOrFail();
    $input = data_get($entry->properties, 'input');

    expect($input['reason'])->toBe('Chargeback')
        ->and($input)->not->toHaveKey('password')
        ->and($input)->not->toHaveKey('payload')
        ->and(json_encode($entry->properties))->not->toContain('super-secret');
});

it('lists and filters the audit trail', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($admin)->post(route('admin.users.suspend', $student), ['reason' => 'Spam']);
    $this->actingAs($otherAdmin)->post(route('admin.users.reactivate', $student));

    $this->actingAs($admin)
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertSee('Suspended')
        ->assertSee('Reactivated')
        ->assertSee($otherAdmin->name);

    $this->actingAs($admin)
        ->get(route('admin.activity.index', ['user' => $admin->id]))
        ->assertOk()
        ->assertSee('Suspended '.$student->name)
        ->assertDontSee('Reactivated '.$student->name);

    $this->actingAs($admin)
        ->get(route('admin.activity.index', ['action' => 'admin.users.reactivate']))
        ->assertOk()
        ->assertSee('Reactivated')
        ->assertDontSee('Suspended '.$student->name);

    $this->actingAs($admin)
        ->get(route('admin.activity.index', ['search' => 'Suspended']))
        ->assertOk()
        ->assertSee('Suspended')
        ->assertDontSee('Reactivated');

    $this->actingAs($admin)
        ->get(route('admin.activity.index', ['from' => now()->addDay()->toDateString()]))
        ->assertOk()
        ->assertSee('Nothing logged in this range yet.');
});

it('logs moderation and settings changes too', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create(['status' => BookingStatus::Completed]);
    $review = Review::factory()->flagged()->create(['booking_id' => $booking->id]);

    $this->actingAs($admin)->post(route('admin.moderation.reviews.hide', $review))->assertRedirect();
    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'commission_percent' => 18,
    ]);

    $actions = ActivityLog::query()->orderBy('id')->pluck('action')->all();

    expect($actions)->toBe(['admin.moderation.reviews.hide', 'admin.settings.update']);

    $settings = ActivityLog::query()->where('action', 'admin.settings.update')->first();
    expect($settings->description)->toContain('commission_percent');
});
