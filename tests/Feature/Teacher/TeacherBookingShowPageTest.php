<?php

/**
 * /teacher/schedule/{booking} is a Vue page now. The island is the contract the
 * page renders from, so these tests pin the answer ("what is next"), the ranked
 * queue, the action flags (which must mirror the policy) and the money down.
 *
 * The component layer lives in resources/js/teacher-booking-show; the Blade twin
 * is only the noscript fallback.
 */

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

it('answers "what is next" for a confirmed lesson in the future', function () {
    $scenario = paidBookingScenario(hoursAhead: 48);

    $response = $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.bookings.show', $scenario['booking']))
        ->assertOk();

    $config = teacherBookingConfig($response);

    expect($config['next']['kind'])->toBe('upcoming')
        ->and($config['next']['deadline'])->not->toBeNull()
        ->and($config['next']['countdownPrefix'])->toBe('Starts in :time')
        ->and($config['actions']['canStart'])->toBeFalse()
        ->and($config['priority'])->toBe([])
        ->and($config['journey'])->toHaveCount(4)
        ->and($config['journey'][1]['state'])->toBe('upcoming')
        ->and($config['header']['statusLabel'])->toBe('Confirmed');
});

it('offers start once the early window opens and ranks it first', function () {
    $scenario = paidBookingScenario();

    $scenario['booking']->forceFill([
        'starts_at' => now()->addMinutes(10),
        'ends_at' => now()->addMinutes(70),
    ])->save();

    $config = teacherBookingConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.bookings.show', $scenario['booking']))
            ->assertOk()
    );

    expect($config['next']['kind'])->toBe('start_now')
        ->and($config['actions']['canStart'])->toBeTrue()
        ->and($config['next']['primary']['kind'])->toBe('form')
        ->and($config['next']['primary']['url'])->toBe(route('teacher.bookings.start', $scenario['booking']))
        ->and($config['priority'][0]['kind'])->toBe('start')
        ->and($config['journey'][1]['state'])->toBe('current');
});

it('puts a running lesson first and offers the classroom', function () {
    $scenario = classroomScenario(minutesFromNow: -10, durationMinutes: 50);

    $scenario['booking']->forceFill([
        'status' => BookingStatus::InProgress,
        'started_at' => now(),
    ])->save();

    $config = teacherBookingConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.bookings.show', $scenario['booking']))
            ->assertOk()
    );

    expect($config['join']['state'])->toBe('open')
        ->and($config['next']['kind'])->toBe('live_join')
        ->and($config['actions']['canComplete'])->toBeTrue()
        ->and(array_column($config['priority'], 'kind'))->toBe(['join', 'deliver'])
        ->and($config['journey'][2]['state'])->toBe('current');
});

it('asks for an outcome when a confirmed lesson has already finished', function () {
    $scenario = paidBookingScenario();

    $scenario['booking']->forceFill([
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHour(),
    ])->save();

    $config = teacherBookingConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.bookings.show', $scenario['booking']))
            ->assertOk()
    );

    $secondary = collect($config['next']['secondary']);

    expect($config['next']['kind'])->toBe('outcome')
        ->and($config['next']['primary']['url'])->toBe(route('teacher.bookings.complete', $scenario['booking']))
        ->and($secondary->pluck('label'))->toContain('Student did not show')
        ->and($secondary->firstWhere('label', 'Student did not show')['confirm'])->toBeTrue()
        ->and($config['priority'][0]['kind'])->toBe('outcome');
});

it('shows the fee split and the earning note', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);

    $config = teacherBookingConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.bookings.show', $scenario['booking']))
            ->assertOk()
    );

    expect($config['money']['feeLabel'])->toBe('Platform fee (15%)')
        ->and($config['money']['price'])->toContain('1,000')
        ->and($config['money']['payout'])->toContain('850')
        ->and($config['money']['note'])->not->toBeNull()
        ->and($config['money']['noteTone'])->toBeIn(['amber', 'emerald']);
});

it('refuses an administrator who is not a teacher', function () {
    $scenario = paidBookingScenario();
    $admin = User::factory()->admin()->create();

    // The teacher route group is role-gated, so even an admin has to go through
    // the admin booking screens instead of the teacher workspace.
    $this->actingAs($admin)
        ->get(route('teacher.bookings.show', $scenario['booking']))
        ->assertForbidden();
});

it('replays a rejected cancellation into the dialog', function () {
    $scenario = paidBookingScenario();

    $this->actingAs($scenario['teacherUser'])
        ->from(route('teacher.bookings.show', $scenario['booking']))
        ->post(route('teacher.bookings.cancel', $scenario['booking']), [
            'reason' => str_repeat('a', 501),
        ])
        ->assertSessionHasErrors('reason');

    $config = teacherBookingConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.bookings.show', $scenario['booking']))
            ->assertOk()
    );

    expect($config['cancel']['error'])->not->toBeNull()
        ->and($config['cancel']['url'])->toBe(route('teacher.bookings.cancel', $scenario['booking']));
});

it('shows the cancellation reason on a closed lesson', function () {
    $scenario = paidBookingScenario();

    $scenario['booking']->forceFill([
        'status' => BookingStatus::Cancelled,
        'cancelled_by' => Booking::CANCELLED_BY_TEACHER,
        'cancelled_at' => now(),
        'cancellation_reason' => 'Family emergency',
    ])->save();

    $config = teacherBookingConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.bookings.show', $scenario['booking']))
            ->assertOk()
    );

    expect($config['next']['kind'])->toBe('closed')
        ->and($config['timeline']['cancellationReason'])->toBe('Family emergency')
        ->and(collect($config['timeline']['steps'])->pluck('key'))->toContain('cancelled')
        ->and($config['cancel'])->toBeNull();
});

it('renders the Vue mount point and the noscript fallback', function () {
    $scenario = paidBookingScenario(hoursAhead: 48);

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.bookings.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('id="teacher-booking-show-config"', false)
        ->assertSee('id="teacher-booking-show-app"', false)
        ->assertSee('<noscript>', false)
        ->assertSee('Mark as delivered');
});
