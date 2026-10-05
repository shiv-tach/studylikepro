<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Meetings\MeetingService;
use App\Services\Payments\EarningsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A booking whose student has finished onboarding, so the student routes accept them.
 * Used by the student-facing booking suites.
 */
function ownedBooking(BookingStatus $status = BookingStatus::PendingPayment, array $overrides = []): Booking
{
    $student = User::factory()->student()->onboarded()->create();
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create(['user_id' => $teacherUser->id]);

    return Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $teacher->id,
        'status' => $status,
        'expires_at' => $status === BookingStatus::PendingPayment
            ? now()->addMinutes(platform_settings()->int('hold_ttl_minutes'))
            : null,
        ...$overrides,
    ]);
}

/**
 * A confirmed lesson with a captured payment and the matching earnings row.
 *
 * @return array{student: User, teacherUser: User, teacher: TeacherProfile, booking: Booking, payment: Payment, earning: TeacherEarning}
 */
function paidBookingScenario(int $priceMinor = 70000, int $hoursAhead = 72): array
{
    $student = User::factory()->student()->onboarded()->create();
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create(['user_id' => $teacherUser->id]);

    $booking = Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addHours($hoursAhead),
        'ends_at' => now()->addHours($hoursAhead)->addHour(),
        'price_minor' => $priceMinor,
        'confirmed_at' => now(),
        ...app(BookingService::class)->feeBreakdown($priceMinor),
    ]);

    $payment = Payment::factory()->create([
        'booking_id' => $booking->id,
        'student_id' => $student->id,
        'amount_minor' => $priceMinor,
    ]);

    $earning = app(EarningsService::class)->recordForBooking($booking->fresh(), $payment);

    return compact('student', 'teacherUser', 'teacher', 'booking', 'payment', 'earning');
}

/**
 * A confirmed lesson set up for classroom tests: two participants and, unless
 * asked otherwise, a live room from the offline meeting provider.
 *
 * @param  array<string, mixed>  $overrides
 * @return array{student: User, teacherUser: User, teacher: TeacherProfile, booking: Booking}
 */
function classroomScenario(int $minutesFromNow = 5, int $durationMinutes = 45, array $overrides = [], bool $withRoom = true): array
{
    $student = User::factory()->student()->onboarded()->create();
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create(['user_id' => $teacherUser->id]);

    $booking = Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addMinutes($minutesFromNow),
        'ends_at' => now()->addMinutes($minutesFromNow + $durationMinutes),
        'confirmed_at' => now(),
        ...$overrides,
    ]);

    if ($withRoom) {
        app(MeetingService::class)->provision($booking);
        $booking->refresh();
    }

    return compact('student', 'teacherUser', 'teacher', 'booking');
}
