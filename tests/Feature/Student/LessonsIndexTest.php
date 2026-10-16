<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;

/**
 * The lesson list is rendered by resources/js/student-lessons, so the Blade view
 * only has to hand the Vue app a correct config island (see `lessonsConfig()` in
 * tests/Pest.php). These tests pin that contract down: the tabs, the filter
 * options, the cards and the route map.
 */
test('the lesson island carries the tabs and the route map', function () {
    $scenario = paidBookingScenario();

    $config = lessonsConfig(
        $this->actingAs($scenario['student'])->get(route('student.bookings.index'))->assertOk()
    );

    expect($config['tab'])->toBe('upcoming');
    expect($config['urls']['index'])->toBe(route('student.bookings.index'));
    expect($config['urls']['teachers'])->toBe(route('student.teachers.index'));
    expect($config['urls']['reset'])->toBe(route('student.bookings.index', ['tab' => 'upcoming']));
    expect($config['theme']['preset'])->toBe('classic');

    // Both tabs count the student's whole history, not just this page.
    expect(collect($config['tabs'])->pluck('label', 'key')->all())->toBe([
        'upcoming' => 'Upcoming (1)',
        'past' => 'Past (0)',
    ]);

    expect($config['hasActiveFilters'])->toBeFalse();
    expect($config['showFilters'])->toBeFalse();
    expect($config['matchLabel'])->toBeNull();
    expect(collect($config['lessons'])->pluck('id')->all())->toBe([$scenario['booking']->id]);
});

test('the student\'s own theme preset reaches the island', function () {
    $student = User::factory()->student()->onboarded()->create([
        'theme' => ['preset' => 'midnight', 'accent' => 'indigo', 'mode' => 'dark', 'sidebarStyle' => 'flat'],
    ]);

    $config = lessonsConfig(
        $this->actingAs($student)->get(route('student.bookings.index'))->assertOk()
    );

    expect($config['theme']['preset'])->toBe('midnight');
});

test('a lesson card carries its status, teacher, time and total', function () {
    $scenario = paidBookingScenario();

    $config = lessonsConfig(
        $this->actingAs($scenario['student'])->get(route('student.bookings.index'))->assertOk()
    );

    $booking = $scenario['booking'];
    $card = collect($config['lessons'])->firstWhere('id', $booking->id);

    expect($card['url'])->toBe(route('student.bookings.show', $booking));
    expect($card['status'])->toBe(BookingStatus::Confirmed->value);
    expect($card['statusLabel'])->toBe(BookingStatus::Confirmed->label());
    expect($card['title'])->toBe('Tutoring lesson');
    expect($card['subtitle'])->toBe('with '.$scenario['teacherUser']->name);
    expect($card['durationLabel'])->toBe('60 min');
    expect($card['payByLabel'])->toBeNull();
    expect($card['joinNow'])->toBeFalse();
    expect($card['reviewPrompt'])->toBeFalse();
    expect($card['amountLabel'])->toBe('Total');
    expect($card['amount'])->toBe(platform_settings()->formatMinor($booking->totalMinor()));
    expect($card['startsLabel'])->toMatch('/^[A-Z][a-z]{2} \d{2} [A-Z][a-z]{2}, \d{2}:\d{2}$/');
    expect($card['endsLabel'])->toMatch('/^\d{2}:\d{2}$/');
});

test('a held slot carries its payment countdown', function () {
    $scenario = paidBookingScenario();

    $scenario['booking']->update([
        'status' => BookingStatus::PendingPayment,
        'expires_at' => now()->addMinutes(10),
    ]);

    $config = lessonsConfig(
        $this->actingAs($scenario['student'])->get(route('student.bookings.index'))->assertOk()
    );

    $card = collect($config['lessons'])->firstWhere('id', $scenario['booking']->id);

    expect($card['payByLabel'])->toStartWith('Pay within');
});

test('a finished lesson without a review is flagged on its card', function () {
    $scenario = paidBookingScenario();

    $scenario['booking']->update([
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
        'completed_at' => now()->subDay(),
    ]);

    $config = lessonsConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.bookings.index', ['tab' => 'past']))
            ->assertOk()
    );

    $card = collect($config['lessons'])->firstWhere('id', $scenario['booking']->id);

    expect($card['status'])->toBe(BookingStatus::Completed->value);
    expect($card['reviewPrompt'])->toBeTrue();
});

test('the filter form offers only the subjects and teachers in the student\'s history', function () {
    $student = User::factory()->student()->onboarded()->create();
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $lesson = Lesson::factory()->for($maths)->create(['name' => 'Quadratic factorising']);
    $booked = TeacherProfile::factory()->approved()->create();
    TeacherProfile::factory()->approved()->create();

    Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $booked->id,
        'subject_id' => $maths->id,
        'lesson_id' => $lesson->id,
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDays(3)->addHour(),
    ]);

    $config = lessonsConfig(
        $this->actingAs($student)->get(route('student.bookings.index', ['tab' => 'past']))->assertOk()
    );

    expect(collect($config['subjectOptions'])->pluck('label')->all())->toBe(['Mathematics']);
    expect(collect($config['teacherOptions'])->pluck('label')->all())->toBe([$booked->user->name]);
    expect($config['statusOptions'])->toHaveCount(count(BookingStatus::cases()));
    expect(collect($config['lessons'])->first()['title'])->toBe('Mathematics · Quadratic factorising');
});

test('an applied filter comes back with its match count and reset link', function () {
    $student = User::factory()->student()->onboarded()->create();

    Booking::factory()->create([
        'student_id' => $student->id,
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDays(3)->addHour(),
    ]);

    Booking::factory()->create([
        'student_id' => $student->id,
        'status' => BookingStatus::Cancelled,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHour(),
    ]);

    $config = lessonsConfig(
        $this->actingAs($student)
            ->get(route('student.bookings.index', ['tab' => 'past', 'status' => BookingStatus::Completed->value]))
            ->assertOk()
    );

    expect($config['filters']['status'])->toBe(BookingStatus::Completed->value);
    expect($config['hasActiveFilters'])->toBeTrue();
    expect($config['matchLabel'])->toBe('1 lesson matches these filters');
    expect($config['urls']['reset'])->toBe(route('student.bookings.index', ['tab' => 'past']));
    expect($config['lessons'])->toHaveCount(1);
});

test('the filter panel only appears once a tab holds more than five lessons', function () {
    $student = User::factory()->student()->onboarded()->create();

    Booking::factory()->count(5)->create(['student_id' => $student->id]);
    Booking::factory()->count(5)->create([
        'student_id' => $student->id,
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHour(),
    ]);

    // Five lessons in a tab is still one short of the threshold.
    expect(lessonsConfig(
        $this->actingAs($student)->get(route('student.bookings.index'))->assertOk()
    )['showFilters'])->toBeFalse();

    expect(lessonsConfig(
        $this->actingAs($student)->get(route('student.bookings.index', ['tab' => 'past']))->assertOk()
    )['showFilters'])->toBeFalse();

    Booking::factory()->hold()->create(['student_id' => $student->id]);

    // The sixth upcoming lesson is where the panel starts earning its space.
    expect(lessonsConfig(
        $this->actingAs($student)->get(route('student.bookings.index'))->assertOk()
    )['showFilters'])->toBeTrue();

    // The whole tab decides, not the filtered page: narrowing the list to the
    // single held slot must not hide the form that narrowed it.
    $filtered = lessonsConfig(
        $this->actingAs($student)
            ->get(route('student.bookings.index', ['status' => BookingStatus::PendingPayment->value]))
            ->assertOk()
    );

    expect($filtered['lessons'])->toHaveCount(1);
    expect($filtered['hasActiveFilters'])->toBeTrue();
    expect($filtered['showFilters'])->toBeTrue();
});

test('an empty tab hands the app no cards so it can render the empty state', function () {
    $student = User::factory()->student()->onboarded()->create();

    $config = lessonsConfig(
        $this->actingAs($student)->get(route('student.bookings.index', ['tab' => 'past']))->assertOk()
    );

    expect($config['tab'])->toBe('past');
    expect($config['lessons'])->toBe([]);
});

test('the release flash only arrives when a lesson was released for rescheduling', function () {
    $student = User::factory()->student()->onboarded()->create();

    expect(lessonsConfig(
        $this->actingAs($student)->get(route('student.bookings.index'))->assertOk()
    )['flash']['rescheduled'])->toBeFalse();

    // Rescheduling redirects to the booking form and flashes `status` there, so
    // the island contract is pinned by handing the list the same flash.
    expect(lessonsConfig(
        $this->actingAs($student)
            ->withSession(['status' => 'booking-reschedule'])
            ->get(route('student.bookings.index'))
            ->assertOk()
    )['flash']['rescheduled'])->toBeTrue();
});
