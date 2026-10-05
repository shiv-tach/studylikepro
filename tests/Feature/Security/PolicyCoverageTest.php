<?php

use App\Enums\BookingStatus;
use App\Enums\ClassificationStatus;
use App\Enums\RequestStatus;
use App\Models\Booking;
use App\Models\TeacherVerificationDocument;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\ReviewService;
use Illuminate\Support\Facades\Storage;

/**
 * Policy coverage: everything that hangs off a booking, a document or a chat is
 * checked for the signed-in user, so a stranger — another student, another
 * teacher, even another admin — gets a 403 rather than a peek at somebody
 * else's lesson, money or ID documents.
 */
it('turns strangers away from every lesson-scoped route', function () {
    $scenario = paidBookingScenario();
    $booking = $scenario['booking'];
    $conversation = app(ConversationService::class)->forBooking($booking);
    $review = app(ReviewService::class);

    $booking->forceFill(['status' => BookingStatus::Completed, 'completed_at' => now()])->save();
    $review->submit($booking->refresh(), $scenario['student'], 5, 'Great lesson.');

    $stranger = User::factory()->student()->onboarded()->create();
    $strangerTeacher = User::factory()->teacher()->onboarded()->create();

    $gets = [
        route('student.bookings.show', $booking),
        route('student.reviews.show', $booking),
        route('receipts.show', $booking),
        route('classroom.show', $booking),
        route('messages.show', $conversation),
        route('messages.poll', $conversation),
    ];

    $posts = [
        [route('student.bookings.cancel', $booking), ['reason' => 'Mine now']],
        [route('student.bookings.reschedule', $booking), ['starts_at' => now()->addWeek()->toIso8601String()]],
        [route('student.bookings.checkout', $booking), []],
        [route('student.reviews.store', $booking), ['rating' => 1, 'comment' => 'Not mine to review.']],
        [route('messages.store', $conversation), ['body' => 'Hello?']],
        [route('messages.report', $conversation), ['reason' => 'other', 'details' => 'Not my thread.']],
        [route('classroom.retry', $booking), []],
    ];

    foreach ([$stranger, $strangerTeacher] as $outsider) {
        foreach ($gets as $url) {
            $this->actingAs($outsider)->get($url)->assertForbidden();
        }

        foreach ($posts as [$url, $payload]) {
            $this->actingAs($outsider)->post($url, $payload)->assertForbidden();
        }
    }

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed)
        ->and($conversation->messages()->where('body', 'Hello?')->doesntExist())->toBeTrue();
});

it('keeps teacher documents and question photos private', function () {
    $teacherUser = User::factory()->teacher()->onboarded()->create();
    $document = TeacherVerificationDocument::factory()->for($teacherUser->teacherProfile)->create();

    $requestScenario = paidBookingScenario();
    $studentRequest = $requestScenario['student']->tutoringRequests()->create([
        'subject_id' => $requestScenario['booking']->subject_id,
        'description' => 'A private question that only the owner and their teacher should read.',
        'preferred_windows' => [[
            'starts_at' => now()->addDay()->setTime(17, 0)->toIso8601String(),
            'ends_at' => now()->addDay()->setTime(19, 0)->toIso8601String(),
        ]],
        'status' => RequestStatus::Open,
        'classification_status' => ClassificationStatus::Pending,
        'expires_at' => now()->addDays(7),
    ]);

    $attachment = $studentRequest->attachments()->create([
        'path' => 'tutoring-requests/demo.png',
        'original_name' => 'question.png',
        'mime_type' => 'image/png',
        'size' => 1024,
    ]);

    Storage::disk('local')->put($document->path, 'id-document-bytes');
    Storage::disk('local')->put($attachment->path, 'question-image-bytes');

    $stranger = User::factory()->student()->onboarded()->create();

    $this->actingAs($stranger)->get(route('verification-documents.show', $document))->assertForbidden();
    $this->actingAs($stranger)->get(route('request-attachments.show', $attachment))->assertForbidden();

    // The owner keeps access.
    $this->actingAs($teacherUser)->get(route('verification-documents.show', $document))->assertOk();
    $this->actingAs($requestScenario['student'])->get(route('request-attachments.show', $attachment))->assertOk();
});

it('keeps every role inside its own section', function () {
    $student = User::factory()->student()->onboarded()->create();
    $teacher = User::factory()->teacher()->onboarded()->create();
    $admin = User::factory()->admin()->create();

    $teacherOnly = [
        route('teacher.profile'),
        route('teacher.verification'),
        route('teacher.subjects.index'),
        route('teacher.availability.index'),
        route('teacher.requests.index'),
        route('teacher.schedule.index'),
        route('teacher.earnings.index'),
        route('teacher.reviews.index'),
    ];

    $studentOnly = [
        route('student.profile'),
        route('student.interests.edit'),
        route('student.requests.index'),
        route('student.bookings.index'),
    ];

    foreach ([$student, $admin] as $outsider) {
        foreach ($teacherOnly as $url) {
            $this->actingAs($outsider)->get($url)->assertForbidden();
        }
    }

    foreach ([$teacher, $admin] as $outsider) {
        foreach ($studentOnly as $url) {
            $this->actingAs($outsider)->get($url)->assertForbidden();
        }
    }

    $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($teacher)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($admin)->get(route('messages.index'))->assertForbidden();
});
