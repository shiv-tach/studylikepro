<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
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

it('builds the next-lesson spotlight and the three views', function () {
    $teacherUser = User::factory()->teacher()->onboarded()->create();
    $teacher = $teacherUser->teacherProfile;

    $soon = Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addHours(3),
        'ends_at' => now()->addHours(4),
        'learner_name' => 'Aarav Mehta',
    ]);

    Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
        'learner_name' => 'Diya Kapoor',
    ]);

    Booking::factory()->hold()->create([
        'teacher_profile_id' => $teacher->id,
        'starts_at' => now()->addDays(4),
        'ends_at' => now()->addDays(4)->addHour(),
        'learner_name' => 'Kabir Singh',
    ]);

    $config = teacherScheduleConfig(
        $this->actingAs($teacherUser)->get(route('teacher.schedule.index'))->assertOk()
    );

    expect($config['next']['id'])->toBe($soon->id)
        ->and($config['next']['learnerName'])->toBe('Aarav Mehta')
        ->and($config['next']['isLive'])->toBeFalse()
        ->and($config['next']['url'])->toBe(route('teacher.bookings.show', $soon))
        ->and(array_column($config['tabs'], 'key'))->toBe(['upcoming', 'holds', 'history'])
        ->and($config['tabs'][0]['count'])->toBe(2)
        ->and($config['tabs'][1]['count'])->toBe(1)
        ->and($config['csrfToken'])->not->toBe('');
});

it('lists the whole day in the today section', function () {
    $this->travelTo(Carbon::parse('2026-03-10 09:00:00'));

    $teacherUser = User::factory()->teacher()->onboarded()->create();
    $teacher = $teacherUser->teacherProfile;
    $teacher->update(['timezone' => 'Asia/Colombo']);

    // Delivered earlier today: the section shows the day, not just what is left.
    Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Completed,
        'starts_at' => Carbon::parse('2026-03-10 02:00:00'),
        'ends_at' => Carbon::parse('2026-03-10 03:00:00'),
        'learner_name' => 'Aarav Mehta',
    ]);

    Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => Carbon::parse('2026-03-10 11:00:00'),
        'ends_at' => Carbon::parse('2026-03-10 12:00:00'),
        'learner_name' => 'Diya Kapoor',
    ]);

    Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => Carbon::parse('2026-03-11 11:00:00'),
        'ends_at' => Carbon::parse('2026-03-11 12:00:00'),
        'learner_name' => 'Kabir Singh',
    ]);

    $config = teacherScheduleConfig(
        $this->actingAs($teacherUser)->get(route('teacher.schedule.index'))->assertOk()
    );

    expect($config['today'])
        ->toMatchArray(['count' => 2, 'countLabel' => '2 lessons today', 'minutesLabel' => '120 min booked'])
        ->and(array_column($config['today']['lessons'], 'learnerName'))->toBe(['Aarav Mehta', 'Diya Kapoor'])
        ->and($config['today']['lessons'][0]['status'])->toBe('completed')
        ->and($config['next']['eyebrow'])->toBe('Next lesson')
        ->and($config['copy']['priority']['title'])->toBe('Needs your attention');
});

it('ranks a finished lesson above a hold that is about to lapse', function () {
    $teacherUser = User::factory()->teacher()->onboarded()->create();
    $teacher = $teacherUser->teacherProfile;

    $finished = Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->subMinutes(55),
        'ends_at' => now()->subMinutes(5),
    ]);

    Booking::factory()->hold()->create([
        'teacher_profile_id' => $teacher->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'expires_at' => now()->addMinutes(20),
    ]);

    $config = teacherScheduleConfig(
        $this->actingAs($teacherUser)->get(route('teacher.schedule.index'))->assertOk()
    );

    expect($config['priority'][0]['kind'])->toBe('needs_outcome')
        ->and($config['priority'][0]['id'])->toBe($finished->id)
        ->and(array_column($config['priority'][0]['actions'], 'label'))->toContain('Mark complete', 'No-show')
        ->and($config['priority'][1]['kind'])->toBe('hold_expiring');
});

it('only offers the transitions the server would accept', function () {
    $teacherUser = User::factory()->teacher()->onboarded()->create();
    $teacher = $teacherUser->teacherProfile;

    $farAhead = Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addHours(5),
        'ends_at' => now()->addHours(6),
    ]);

    $withinStartWindow = Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addMinutes(10),
        'ends_at' => now()->addMinutes(70),
    ]);

    $running = Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::InProgress,
        'starts_at' => now()->subMinutes(20),
        'ends_at' => now()->addMinutes(40),
    ]);

    $config = teacherScheduleConfig(
        $this->actingAs($teacherUser)->get(route('teacher.schedule.index'))->assertOk()
    );

    $cards = collect($config['agenda'])
        ->flatMap(fn (array $day) => $day['lessons'])
        ->keyBy('id');

    expect($cards[$farAhead->id]['actions'])
        ->toMatchArray(['canStart' => false, 'canComplete' => false, 'canNoShow' => false])
        ->and($cards[$withinStartWindow->id]['actions'])
        ->toMatchArray(['canStart' => true, 'canComplete' => false, 'canNoShow' => false])
        ->and($cards[$running->id]['actions'])
        ->toMatchArray(['canStart' => false, 'canComplete' => true, 'canNoShow' => false]);
});

it('groups the agenda by day in the teacher timezone', function () {
    $this->travelTo(Carbon::parse('2026-03-10 09:00:00'));

    $teacherUser = User::factory()->teacher()->onboarded()->create();
    $teacher = $teacherUser->teacherProfile;
    $teacher->update(['timezone' => 'Asia/Colombo']);

    Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => Carbon::parse('2026-03-10 11:00:00'),
        'ends_at' => Carbon::parse('2026-03-10 12:00:00'),
    ]);

    Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => Carbon::parse('2026-03-11 11:00:00'),
        'ends_at' => Carbon::parse('2026-03-11 12:00:00'),
    ]);

    $config = teacherScheduleConfig(
        $this->actingAs($teacherUser)->get(route('teacher.schedule.index'))->assertOk()
    );

    expect($config['agenda'])->toHaveCount(2)
        ->and($config['agenda'][0])->toMatchArray(['key' => '2026-03-10', 'label' => 'Today', 'count' => 1])
        ->and($config['agenda'][1])->toMatchArray(['key' => '2026-03-11', 'label' => 'Tomorrow', 'count' => 1]);
});

it('counts the week and flags a hold that is about to lapse', function () {
    $this->travelTo(Carbon::parse('2026-03-10 09:00:00'));

    $teacherUser = User::factory()->teacher()->onboarded()->create();
    $teacher = $teacherUser->teacherProfile;
    $teacher->update(['timezone' => 'Asia/Colombo']);

    Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => Carbon::parse('2026-03-11 11:00:00'),
        'ends_at' => Carbon::parse('2026-03-11 12:00:00'),
    ]);

    Booking::factory()->hold()->create([
        'teacher_profile_id' => $teacher->id,
        'starts_at' => Carbon::parse('2026-03-12 11:00:00'),
        'ends_at' => Carbon::parse('2026-03-12 12:00:00'),
        'expires_at' => Carbon::parse('2026-03-10 09:20:00'),
    ]);

    $config = teacherScheduleConfig(
        $this->actingAs($teacherUser)->get(route('teacher.schedule.index'))->assertOk()
    );

    expect($config['stats'])
        ->toMatchArray(['todayCount' => 0, 'weekCount' => 1, 'holdCount' => 1, 'holdSoonCount' => 1])
        ->and($config['holds'][0]['expiresAt'])->not->toBeNull()
        ->and($config['holds'][0]['expiresLabel'])->toBe('14:50')
        ->and(collect($config['priority'])->pluck('kind')->all())->toContain('hold_expiring');
});

it('returns to the schedule after an inline transition', function () {
    Notification::fake();

    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addMinutes(10),
        'ends_at' => now()->addMinutes(70),
    ]);

    $this->actingAs($booking->teacherProfile->user)
        ->post(route('teacher.bookings.start', $booking), ['return' => 'schedule'])
        ->assertRedirect(route('teacher.schedule.index'))
        ->assertSessionHas('status', 'booking-start');

    expect($booking->refresh()->status)->toBe(BookingStatus::InProgress);

    $config = teacherScheduleConfig(
        $this->actingAs($booking->teacherProfile->user)->get(route('teacher.schedule.index'))->assertOk()
    );

    expect($config['flash']['tone'])->toBe('primary')
        ->and($config['flash']['message'])->not->toBeEmpty();
});

it('replays a refused transition on the schedule', function () {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addHours(5),
        'ends_at' => now()->addHours(6),
    ]);

    $this->actingAs($booking->teacherProfile->user)
        ->from(route('teacher.schedule.index'))
        ->post(route('teacher.bookings.start', $booking), ['return' => 'schedule'])
        ->assertRedirect(route('teacher.schedule.index'))
        ->assertSessionHasErrors('status');

    $config = teacherScheduleConfig(
        $this->actingAs($booking->teacherProfile->user)->get(route('teacher.schedule.index'))->assertOk()
    );

    expect($config['errors']['status'])->not->toBeNull();
});
