<?php

use App\Enums\ApprovalStatus;
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
use Illuminate\Testing\TestResponse;
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
 * Read the config island the Vue app-shell renders the sidebar and top bar from.
 *
 * @return array<string, mixed>
 */
function shellConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="studylikepro-shell-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the "My lessons" page renders its cards from.
 *
 * @return array<string, mixed>
 */
function lessonsConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-lessons-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the teaching profile renders its form and live public
 * card preview from. (The student-facing teacher page has its own reader.)
 *
 * @return array<string, mixed>
 */
function teachingProfileConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="teacher-profile-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the availability setup step renders its week from.
 *
 * @return array<string, mixed>
 */
function availabilitySetupConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="teacher-availability-setup-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the verification step renders its checklist, documents
 * and upload cards from.
 *
 * @return array<string, mixed>
 */
function verificationConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="teacher-verification-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the teacher subjects workspace renders its setup
 * cards, request queue and catalog picker from.
 *
 * @return array<string, mixed>
 */
function teacherSubjectsConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="teacher-subjects-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the teacher services catalog renders its tabs,
 * offerings and composer from.
 *
 * @return array<string, mixed>
 */
function teacherServicesConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="teacher-services-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the teacher order book renders its status rail, search
 * and order cards from.
 *
 * @return array<string, mixed>
 */
function teacherOrdersConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="teacher-orders-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the teacher order detail page renders its timeline,
 * money, plan, student, offering, session and marking panels from.
 *
 * @return array<string, mixed>
 */
function teacherOrderShowConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="teacher-order-show-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the teaching schedule renders its next-lesson
 * spotlight, priority queue and agenda from.
 *
 * @return array<string, mixed>
 */
function teacherScheduleConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="teacher-schedule-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the "My purchases" page renders its cards from.
 *
 * @return array<string, mixed>
 */
function purchasesConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-purchases-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the purchase detail page renders its panels from.
 *
 * @return array<string, mixed>
 */
function purchaseShowConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-purchase-show-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the "Messages" inbox renders its threads from.
 *
 * @return array<string, mixed>
 */
function messagesConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="messages-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island one lesson thread renders its messages, composer and
 * report dialog from.
 *
 * @return array<string, mixed>
 */
function messagesThreadConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="messages-thread-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the grade-matched teacher finder renders its filters,
 * cards and empty state from.
 *
 * @return array<string, mixed>
 */
function teacherFinderConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-teacher-finder-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island one teacher's workspace page renders the grade-scoped
 * rates, services, availability, reviews and booking action from.
 *
 * @return array<string, mixed>
 */
function teacherProfileConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-teacher-profile-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the public subject page renders its sections, prices
 * and actions from. The catalog fetches the same shape when a subject is
 * opened on click.
 *
 * @return array<string, mixed>
 */
function publicSubjectConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="public-subject-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the public catalog renders its sticky explorer from.
 * The explorer fetches the same level/grade shape when a pick changes.
 *
 * @return array<string, mixed>
 */
function publicSubjectsConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="public-subjects-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the learning profile renders its grade and language
 * pickers, the student's answers, the validation messages and the save action
 * from.
 *
 * @return array<string, mixed>
 */
function studentProfileConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-profile-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the direct-booking stepper renders its subject picker,
 * slots and order summary from.
 *
 * @return array<string, mixed>
 */
function bookingCreateConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-booking-create-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the lesson detail page renders its banners, timeline,
 * money and actions from.
 *
 * @return array<string, mixed>
 */
function bookingShowConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-booking-show-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the teacher's one-lesson page renders its next step,
 * ranked queue, lesson flow and money from.
 *
 * @return array<string, mixed>
 */
function teacherBookingConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="teacher-booking-show-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the settings home renders its cards, the unread badge
 * and the email preference from.
 *
 * @return array<string, mixed>
 */
function settingsConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="settings-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the theme page renders its presets, accents, modes and
 * saved choice from.
 *
 * @return array<string, mixed>
 */
function settingsThemeConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="settings-theme-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the admin platform settings console renders its grouped
 * definitions and current values from.
 *
 * @return array<string, mixed>
 */
function adminSettingsConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="admin-settings-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the profile page renders its three account forms from.
 *
 * @return array<string, mixed>
 */
function profileConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="profile-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Read the config island the notification centre renders its rows and email
 * preference from.
 *
 * @return array<string, mixed>
 */
function notificationsConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="notifications-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
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
 * A teacher who finished onboarding steps 1 and 2: the profile is complete and
 * a weekly range exists, but the verification documents have not been submitted,
 * so the teacher area stays locked at step 3. A subject is attached because the
 * verification step requires at least one before the application can be submitted.
 */
function onboardingTeacher(): User
{
    $user = User::factory()->teacher()->create();
    $profile = TeacherProfile::factory()->for($user)->create();

    TeacherAvailabilitySlot::factory()->create(['teacher_profile_id' => $profile->id]);

    $subject = Subject::query()->active()->first() ?? Subject::factory()->create(['is_active' => true]);

    $profile->subjects()->attach($subject->id, [
        'status' => ApprovalStatus::Approved,
        'grade_levels' => [],
    ]);

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
    $teacher->subjects()->attach($subject->id, [
        'status' => ApprovalStatus::Approved,
        'grade_levels' => [(string) gradeId(11)],
    ]);

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
