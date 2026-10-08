<?php

use App\Enums\BookingStatus;
use App\Enums\ClassificationStatus;
use App\Enums\RequestStatus;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Review;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\LessonCompleted;
use App\Services\ConversationService;
use App\Services\ReviewService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * The audit from the launch plan: with lazy loading switched off, every page has
 * to render from eager-loaded data. A violation surfaces as an exception naming
 * the relation, which is exactly what we want to see fail.
 */
beforeEach(function () {
    $this->withoutExceptionHandling();
    Model::preventLazyLoading();
});

afterEach(function () {
    Model::preventLazyLoading(false);
    Model::preventSilentlyDiscardingAttributes(false);
});

/**
 * A completed, paid, reviewed lesson with a chat thread and a dispute, which is
 * enough context for the student, teacher and admin pages at once.
 *
 * @return array<string, mixed>
 */
function eagerLoadingScenario(): array
{
    $scenario = paidBookingScenario();
    $booking = $scenario['booking'];

    $conversation = app(ConversationService::class)->forBooking($booking);
    app(ConversationService::class)->post($conversation, $scenario['student'], 'Ready for the lesson.');
    app(ConversationService::class)->post($conversation, $scenario['teacherUser'], 'See you then.');

    $booking->update([
        'status' => BookingStatus::Completed,
        'started_at' => now()->subDay(),
        'completed_at' => now()->subDay(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]);

    $review = app(ReviewService::class)->submit($booking->refresh(), $scenario['student'], 5, 'Excellent lesson.');

    $dispute = Dispute::factory()->create([
        'booking_id' => $booking->id,
        'conversation_id' => $conversation->id,
        'raised_by' => $scenario['student']->id,
        'against_id' => $scenario['teacherUser']->id,
    ]);

    Notification::send($scenario['student'], new LessonCompleted($booking->refresh()));

    $subject = Subject::query()->active()->first() ?? Subject::factory()->create(['is_active' => true]);

    // The public subject page previews its teachers, so the scenario teacher
    // gets the subject - the card has to render from eager-loaded data too.
    $scenario['teacher']->subjects()->attach($subject->id, ['grade_levels' => []]);

    return [...$scenario, 'conversation' => $conversation, 'review' => $review, 'dispute' => $dispute, 'subject' => $subject];
}

it('renders the public pages from eager-loaded data', function () {
    $scenario = eagerLoadingScenario();

    foreach ([
        '/',
        route('catalog.subjects.index'),
        route('catalog.subjects.show', $scenario['subject']),
        route('teachers.index'),
        route('teachers.show', $scenario['teacher']),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('renders the student pages from eager-loaded data', function () {
    $scenario = eagerLoadingScenario();
    $student = $scenario['student'];

    foreach ([
        route('student.dashboard'),
        route('student.profile'),
        route('student.interests.edit'),
        route('student.teachers.index'),
        route('student.teachers.show', $scenario['teacher']),
        route('student.requests.index'),
        route('student.requests.create'),
        route('student.bookings.index'),
        route('student.bookings.show', $scenario['booking']),
        route('student.reviews.show', $scenario['booking']),
        route('receipts.show', $scenario['booking']),
        route('messages.index'),
        route('messages.show', $scenario['conversation']),
        route('notifications.index'),
        route('settings.index'),
    ] as $url) {
        $this->actingAs($student)->get($url)->assertOk();
    }
});

it('renders the teacher pages from eager-loaded data', function () {
    $scenario = eagerLoadingScenario();
    $teacher = $scenario['teacherUser'];

    foreach ([
        route('teacher.dashboard'),
        route('teacher.profile'),
        route('teacher.verification'),
        route('teacher.subjects.index'),
        route('teacher.availability.index'),
        route('teacher.requests.index'),
        route('teacher.schedule.index'),
        route('teacher.bookings.show', $scenario['booking']),
        route('teacher.earnings.index'),
        route('teacher.reviews.index'),
    ] as $url) {
        $this->actingAs($teacher)->get($url)->assertOk();
    }
});

it('renders the admin pages from eager-loaded data', function () {
    $scenario = eagerLoadingScenario();
    $admin = User::factory()->admin()->create();

    Review::query()->whereKey($scenario['review']->id)->update(['flagged_at' => now(), 'flag_reason' => 'unfair']);

    foreach ([
        route('admin.dashboard'),
        route('admin.reports.index'),
        route('admin.users.index'),
        route('admin.users.show', $scenario['student']),
        route('admin.users.show', $scenario['teacherUser']),
        route('admin.moderation.index'),
        route('admin.disputes.index'),
        route('admin.disputes.show', $scenario['dispute']),
        route('admin.bookings.index'),
        route('admin.bookings.show', $scenario['booking']),
        route('admin.payments.index'),
        route('admin.payments.show', $scenario['payment']),
        route('admin.activity.index'),
        route('admin.settings.edit'),
        route('admin.subjects.index'),
        route('admin.verifications.index'),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});

it('refuses to silently discard attributes when writing', function () {
    Model::preventSilentlyDiscardingAttributes();

    $teacher = TeacherProfile::factory()->approved()->create();

    // Guarded columns (id, timestamps) and unknown keys must never be quietly
    // dropped by a controller filling a model from request data.
    expect(fn () => $teacher->fill(['nope' => 'value']))
        ->toThrow(MassAssignmentException::class);

    expect(fn () => Booking::query()->create(['status' => BookingStatus::Confirmed]))
        ->not->toThrow(MassAssignmentException::class);
});

it('renders the remaining detail pages from eager-loaded data', function () {
    $scenario = eagerLoadingScenario();
    $student = $scenario['student'];

    $request = $student->tutoringRequests()->create([
        'subject_id' => $scenario['subject']->id,
        'description' => 'I need help with quadratic equations before my exam next week.',
        'preferred_windows' => [[
            'starts_at' => now()->addDay()->setTime(17, 0)->toIso8601String(),
            'ends_at' => now()->addDay()->setTime(19, 0)->toIso8601String(),
        ]],
        'status' => RequestStatus::Open,
        'classification_status' => ClassificationStatus::Pending,
        'expires_at' => now()->addDays(7),
    ]);

    $room = classroomScenario();

    $pending = TeacherProfile::factory()->create([
        'user_id' => User::factory()->teacher()->create()->id,
        'verification_status' => VerificationStatus::Pending,
        'submitted_at' => now(),
    ]);

    $admin = User::factory()->admin()->create();

    foreach ([
        [route('student.requests.show', $request), $student],
        [route('classroom.show', $room['booking']), $room['student']],
        [route('classroom.show', $room['booking']), $room['teacherUser']],
    ] as [$url, $actor]) {
        $this->actingAs($actor)->get($url)->assertOk();
    }

    foreach ([
        route('admin.verifications.show', $pending),
        route('admin.payouts.index'),
        route('admin.reports.index', ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()]),
        route('admin.activity.index', ['search' => 'booking']),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});
