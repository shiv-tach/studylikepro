<?php

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\BookingTransitionService;

/**
 * The lesson detail page is rendered by resources/js/student-booking-show, so the
 * Blade view only has to hand the Vue app a correct config island (see
 * `bookingShowConfig()` in tests/Pest.php). These tests pin that contract down:
 * the state banner, the money, the timeline and the permitted actions.
 */
test('the detail island carries the money, the timeline and the permitted actions', function () {
    $scenario = paidBookingScenario();
    $booking = $scenario['booking'];

    $config = bookingShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.bookings.show', $booking))
            ->assertOk()
    );

    expect($config['theme']['preset'])->toBe('classic')
        ->and($config['flash'])->toBeNull()
        ->and($config['error'])->toBeNull()
        ->and($config['banner'])->toBeNull()
        ->and($config['join'])->toBeNull()
        ->and($config['cancelNotice'])->toBeNull()
        ->and($config['review'])->toBeNull();

    expect($config['money']['totalLabel'])->toBe('Lesson total')
        ->and($config['money']['total'])->toBe(platform_settings()->formatMinor($booking->totalMinor()))
        ->and($config['money']['payment']['statusLabel'])->toBe('Paid')
        ->and($config['money']['payment']['statusTone'])->toBe('emerald')
        ->and($config['money']['receiptUrl'])->toBe(route('receipts.show', $booking));

    expect(collect($config['timeline']['steps'])->pluck('key')->all())
        ->toBe(['requested', 'confirmed', 'in_progress', 'completed', 'reviewed']);

    // A confirmed lesson offers the classroom, the cancel dialog and the reschedule.
    $labels = collect($config['actions'])->pluck('label')->all();

    expect($labels)->toContain('Live classroom')
        ->toContain('Cancel lesson')
        ->toContain('Reschedule');

    expect($config['cancel']['url'])->toBe(route('student.bookings.cancel', $booking))
        ->and($config['cancel']['submitLabel'])->toBe('Cancel lesson');
});

test('a held slot travels with its payment call to action', function () {
    $booking = ownedBooking(BookingStatus::PendingPayment, ['expires_at' => now()->addMinutes(10)]);

    $config = bookingShowConfig(
        $this->actingAs($booking->student)
            ->get(route('student.bookings.show', $booking))
            ->assertOk()
    );

    expect($config['banner']['kind'])->toBe('hold')
        ->and($config['banner']['title'])->toBe('Your slot is held')
        ->and($config['banner']['total'])->toBe(platform_settings()->formatMinor($booking->totalMinor()))
        ->and($config['banner']['payLabel'])->toBe('Pay '.platform_settings()->formatMinor($booking->totalMinor()))
        ->and($config['banner']['payUrl'])->toBe(route('student.bookings.checkout', $booking));
});

test('a cancelled lesson hands the island its banner and a way out', function () {
    $booking = ownedBooking(BookingStatus::Cancelled, [
        'cancelled_by' => Booking::CANCELLED_BY_TEACHER,
        'cancellation_reason' => 'Teacher is unwell.',
    ]);

    $config = bookingShowConfig(
        $this->actingAs($booking->student)
            ->get(route('student.bookings.show', $booking))
            ->assertOk()
    );

    expect($config['banner']['kind'])->toBe('cancelled')
        ->and($config['banner']['body'])->toBe('Cancelled by the teacher.')
        ->and($config['banner']['reason'])->toBe('Teacher is unwell.')
        ->and($config['banner']['action']['url'])->toBe(route('student.teachers.index'))
        ->and($config['actions'])->toBe([]);
});

test('the island drops the cancel copy once the window closes', function () {
    $booking = ownedBooking(BookingStatus::Confirmed, [
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
    ]);

    $response = $this->actingAs($booking->student)
        ->get(route('student.bookings.show', $booking))
        ->assertOk();

    $config = bookingShowConfig($response);

    expect($config['cancel'])->toBeNull()
        ->and(collect($config['actions'])->pluck('label')->all())->not->toContain('Cancel lesson')
        ->and($config['cancelNotice'])->toStartWith('This lesson starts in less than');

    // Nothing on the page may offer an action the policy refuses.
    $response->assertDontSee('Cancel lesson');
});

test('the review panel offers the next action and then the edit', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $this->travelTo($booking->starts_at->addMinutes(10));
    app(BookingTransitionService::class)->complete($booking);

    $config = bookingShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.bookings.show', $booking))
            ->assertOk()
    );

    expect($config['review']['hasReview'])->toBeFalse()
        ->and($config['review']['actionLabel'])->toBe('Leave a review')
        ->and($config['money']['totalLabel'])->toBe('Paid');

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $booking), ['rating' => 4, 'comment' => 'Clear explanations.']);

    $config = bookingShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.bookings.show', $booking))
            ->assertOk()
    );

    expect($config['review']['hasReview'])->toBeTrue()
        ->and($config['review']['actionLabel'])->toBe('Edit review')
        ->and($config['review']['comment'])->toBe('Clear explanations.')
        ->and($config['review']['outOfFive'])->toBe('4/5');
});

test('a redirect flash rides in the island', function () {
    $scenario = paidBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $scenario['booking']), ['reason' => 'Change of plans'])
        ->assertRedirect(route('student.bookings.show', $scenario['booking']));

    $config = bookingShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.bookings.show', $scenario['booking']))
            ->assertOk()
    );

    expect($config['flash']['tone'])->toBe('slate')
        ->and($config['flash']['message'])->toBe('Lesson cancelled. The slot is back on the teacher calendar.');
});

test('a session of a plan carries its sibling sessions and the next booking', function () {
    $student = User::factory()->student()->onboarded()->create();
    $teacher = TeacherProfile::factory()->approved()->create();
    $subject = Subject::factory()->create(['name' => 'Mathematics']);
    $lesson = Lesson::factory()->create([
        'subject_id' => $subject->id,
        'grade_id' => Grade::factory()->create()->id,
        'name' => 'Quadratic factorising',
    ]);

    $offering = ServiceOffering::factory()->forType(ServiceType::Lesson)->create([
        'teacher_profile_id' => $teacher->id,
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
        'price_minor' => 120000,
    ]);

    $purchase = ServicePurchase::factory()->forOffering($offering)->create([
        'student_id' => $student->id,
        'allocation_minutes' => 180,
        'max_sessions' => 4,
        'booking_fee_minor' => 0,
    ]);

    $session = Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $teacher->id,
        'service_purchase_id' => $purchase->id,
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
        'starts_at' => now()->addDays(2)->setTime(15, 0),
        'ends_at' => now()->addDays(2)->setTime(16, 0),
        'price_minor' => 0,
    ]);

    $sibling = Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $teacher->id,
        'service_purchase_id' => $purchase->id,
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
        'starts_at' => now()->addDays(3)->setTime(15, 0),
        'ends_at' => now()->addDays(3)->setTime(16, 0),
        'price_minor' => 0,
    ]);

    $config = bookingShowConfig(
        $this->actingAs($student)
            ->get(route('student.bookings.show', $session))
            ->assertOk()
    );

    expect($config['plan'])->not->toBeNull()
        ->and($config['plan']['balance']['value'])->toBe('60 min')
        ->and($config['plan']['sessionsLeft'])->toBe('2 sessions left to book')
        ->and($config['plan']['bookUrl'])->toBe(route('student.purchase-sessions.create', $purchase));

    $sessions = collect($config['plan']['sessions']);

    expect($sessions)->toHaveCount(2)
        ->and($sessions->firstWhere('id', $session->id)['current'])->toBeTrue()
        ->and($sessions->firstWhere('id', $sibling->id)['current'])->toBeFalse()
        ->and($sessions->pluck('url')->all())->toBe([
            route('student.bookings.show', $session),
            route('student.bookings.show', $sibling),
        ]);
});

test('a capped plan with no sessions left hides the booking action', function () {
    $student = User::factory()->student()->onboarded()->create();
    $teacher = TeacherProfile::factory()->approved()->create();
    $offering = ServiceOffering::factory()->forType(ServiceType::Lesson)->create([
        'teacher_profile_id' => $teacher->id,
        'price_minor' => 120000,
    ]);

    $purchase = ServicePurchase::factory()->forOffering($offering)->create([
        'student_id' => $student->id,
        'allocation_minutes' => 120,
        'max_sessions' => 1,
        'booking_fee_minor' => 0,
    ]);

    $session = Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $teacher->id,
        'service_purchase_id' => $purchase->id,
        'starts_at' => now()->addDays(2)->setTime(15, 0),
        'ends_at' => now()->addDays(2)->setTime(16, 0),
        'price_minor' => 0,
    ]);

    $config = bookingShowConfig(
        $this->actingAs($student)
            ->get(route('student.bookings.show', $session))
            ->assertOk()
    );

    expect($config['plan']['bookUrl'])->toBeNull()
        ->and($config['plan']['sessionsLeft'])->toBe('0 sessions left to book')
        ->and($config['plan']['exhausted'])->toBeTrue();
});
