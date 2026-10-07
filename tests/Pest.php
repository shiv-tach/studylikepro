<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherEarning;
use App\Models\TeacherInvite;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Meetings\MeetingService;
use App\Services\Payments\EarningsService;
use App\Support\BookingDraft;
use Carbon\CarbonImmutable;
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
 * Create a usable teacher onboarding invite and return the plaintext token.
 *
 * @return array{0: TeacherInvite, 1: string}
 */
function makeTeacherInvite(?int $expiresInDays = 7): array
{
    return TeacherInvite::createWithToken($expiresInDays, null);
}

/**
 * The id of the seeded grade with this number (1-13). The migrations seed the
 * Sri Lankan levels and grades, so tests can rely on them being present.
 */
function gradeId(int $number): int
{
    return (int) Grade::query()->where('number', $number)->value('id');
}

/**
 * A teacher who finished step 1 only: the profile is complete but the verification
 * documents have not been submitted, so the teacher area stays locked.
 */
function onboardingTeacher(): User
{
    $user = User::factory()->teacher()->create();
    TeacherProfile::factory()->for($user)->create();

    return $user;
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

/**
 * An approved maths teacher with an evening window two days out and an onboarded
 * student, ready to reserve a slot at Rs 600 per hour. Used by the booking-fee
 * suites, which care about the student-facing platform fee.
 *
 * @return array{subject: Subject, teacher: TeacherProfile, teacherUser: User, student: User, day: CarbonImmutable, slot: CarbonImmutable}
 */
function bookingFeeScenario(): array
{
    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $teacherUser->id,
        'timezone' => 'UTC',
        'hourly_rate_minor' => 60000,
        'lesson_duration_minutes' => 60,
    ]);
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) gradeId(11)]]);

    $day = CarbonImmutable::now('UTC')->addDays(2)->startOfDay();
    TeacherAvailabilitySlot::factory()
        ->on($day->dayOfWeek, '18:00', '21:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    return [
        'subject' => $subject,
        'teacher' => $teacher,
        'teacherUser' => $teacherUser,
        'student' => User::factory()->student()->onboarded()->create(),
        'day' => $day,
        'slot' => $day->setTime(18, 0),
    ];
}

/**
 * Reserve a slot of the booking-fee scenario as a pending-payment hold.
 *
 * @param  array{student: User, teacher: TeacherProfile, subject: Subject, day: CarbonImmutable}  $scenario
 */
function reserveFeeBooking(array $scenario, int $hour = 18): Booking
{
    return app(BookingService::class)->reserve(new BookingDraft(
        student: $scenario['student'],
        teacher: $scenario['teacher'],
        startsAt: $scenario['day']->setTime($hour, 0),
        endsAt: $scenario['day']->setTime($hour + 1, 0),
        subject: $scenario['subject'],
    ));
}
