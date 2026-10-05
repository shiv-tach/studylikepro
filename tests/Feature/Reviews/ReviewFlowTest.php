<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\ReviewFlagged;
use App\Services\ReviewService;
use App\Services\TeacherStatsService;
use Illuminate\Support\Facades\Notification;

/**
 * @return array{student: User, teacherUser: User, teacher: TeacherProfile, booking: Booking}
 */
function deliveredLesson(): array
{
    $scenario = classroomScenario();

    $scenario['booking']->forceFill([
        'status' => BookingStatus::Completed,
        'started_at' => $scenario['booking']->starts_at,
        'completed_at' => now(),
    ])->save();

    return [
        'student' => $scenario['student'],
        'teacherUser' => $scenario['teacherUser'],
        'teacher' => $scenario['teacher']->refresh(),
        'booking' => $scenario['booking']->refresh(),
    ];
}

it('lets the student review a delivered lesson and updates the teacher rating', function () {
    $scenario = deliveredLesson();

    expect($scenario['teacher']->rating_count)->toBe(0)
        ->and($scenario['teacher']->rating_avg)->toBeNull();

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), [
            'rating' => 5,
            'comment' => 'Explained quadratics in a way I finally understood.',
        ])
        ->assertRedirect(route('student.bookings.show', $scenario['booking']))
        ->assertSessionHas('status', 'review-submitted');

    $review = Review::query()->firstOrFail();

    expect($review->booking_id)->toBe($scenario['booking']->id)
        ->and($review->student_id)->toBe($scenario['student']->id)
        ->and($review->teacher_profile_id)->toBe($scenario['teacher']->id)
        ->and($review->rating)->toBe(5);

    $teacher = $scenario['teacher']->fresh();

    expect($teacher->rating_count)->toBe(1)
        ->and((float) $teacher->rating_avg)->toBe(5.0)
        ->and($teacher->lessons_completed_count)->toBe(1);
});

it('refuses to review a lesson that has not been delivered', function () {
    $scenario = classroomScenario();

    $this->actingAs($scenario['student'])
        ->get(route('student.reviews.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('This lesson has not been delivered yet');

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 5])
        ->assertForbidden();

    expect(Review::query()->count())->toBe(0);
});

it('allows only one review per lesson', function () {
    $scenario = deliveredLesson();

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 4, 'comment' => 'Good.']);

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 5, 'comment' => 'Changed my mind.'])
        ->assertForbidden();

    expect(Review::query()->count())->toBe(1)
        ->and($scenario['teacher']->fresh()->rating_count)->toBe(1);
});

it('lets the author edit the review inside the window and blocks it afterwards', function () {
    $scenario = deliveredLesson();

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 3, 'comment' => 'Fine.']);

    $review = Review::query()->firstOrFail();

    // Day five: still editable.
    $this->travelTo(now()->addDays(5));

    $this->actingAs($scenario['student'])
        ->put(route('student.reviews.update', $scenario['booking']), ['rating' => 5, 'comment' => 'Even better on reflection.'])
        ->assertRedirect(route('student.bookings.show', $scenario['booking']));

    $review->refresh();

    expect($review->rating)->toBe(5)
        ->and($review->wasEdited())->toBeTrue()
        ->and((float) $scenario['teacher']->fresh()->rating_avg)->toBe(5.0);

    // Day nine: the window has closed.
    $this->travelTo(now()->addDays(4));

    $this->actingAs($scenario['student'])
        ->put(route('student.reviews.update', $scenario['booking']), ['rating' => 1, 'comment' => 'Too late.'])
        ->assertForbidden();

    expect($review->fresh()->rating)->toBe(5);

    $this->actingAs($scenario['student'])
        ->get(route('student.reviews.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('The edit window has closed');
});

it('validates the rating and the comment length', function () {
    $scenario = deliveredLesson();

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 9])
        ->assertSessionHasErrors('rating');

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 4, 'comment' => str_repeat('a', 1001)])
        ->assertSessionHasErrors('comment');

    expect(Review::query()->count())->toBe(0);
});

it('keeps other students and teachers out of the review form', function () {
    $scenario = deliveredLesson();

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('student.reviews.show', $scenario['booking']))
        ->assertForbidden();

    $this->actingAs($scenario['teacherUser'])
        ->get(route('student.reviews.show', $scenario['booking']))
        ->assertForbidden();
});

it('averages only visible reviews and recomputes on change', function () {
    $teacher = TeacherProfile::factory()->approved()->create();
    $stats = app(TeacherStatsService::class);

    foreach ([5, 4, 3] as $rating) {
        Review::factory()->rating($rating)->create(['teacher_profile_id' => $teacher->id]);
        $stats->refresh($teacher);
    }

    expect($teacher->fresh()->rating_count)->toBe(3)
        ->and((float) $teacher->fresh()->rating_avg)->toBe(4.0);

    // A four-star review on a fresh lesson moves the average.
    $scenario = deliveredLesson();
    $scenario['booking']->forceFill(['teacher_profile_id' => $teacher->id])->save();

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 4]);

    expect($teacher->fresh()->rating_count)->toBe(4)
        ->and((float) $teacher->fresh()->rating_avg)->toBe(4.0);

    // Hiding the three-star review takes it out of the maths and the profile.
    $lowest = Review::query()->where('teacher_profile_id', $teacher->id)->orderBy('rating')->firstOrFail();
    app(ReviewService::class)->hide($lowest, User::factory()->admin()->create());

    $teacher->refresh();

    expect($teacher->rating_count)->toBe(3)
        ->and((float) $teacher->rating_avg)->toBeGreaterThan(4.0)
        ->and($teacher->visibleReviews()->count())->toBe(3)
        ->and($teacher->reviews()->count())->toBe(4);
});

it('shows the reviews on the public teacher profile and hides hidden ones', function () {
    $scenario = deliveredLesson();

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 5, 'comment' => 'Best chemistry lesson I have had.']);

    $this->get(route('teachers.show', $scenario['teacher']))
        ->assertOk()
        ->assertSee('Student reviews')
        ->assertSee('Best chemistry lesson I have had.')
        ->assertSee('5 ★');

    $review = Review::query()->firstOrFail();
    app(ReviewService::class)->hide($review, User::factory()->admin()->create());

    $this->get(route('teachers.show', $scenario['teacher']))
        ->assertOk()
        ->assertSee('No reviews yet')
        ->assertDontSee('Best chemistry lesson I have had.');

    expect($scenario['teacher']->fresh()->rating_count)->toBe(0)
        ->and((float) $scenario['teacher']->fresh()->rating_avg)->toBe(0.0);
});

it('lets the teacher report a review once and tells the admins', function () {
    Notification::fake();

    $scenario = deliveredLesson();
    $admin = User::factory()->admin()->create();

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 1, 'comment' => 'Awful.']);

    $review = Review::query()->firstOrFail();

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.reviews.flag', $review), ['reason' => 'abusive', 'notes' => 'Swearing in the comment.'])
        ->assertRedirect(route('teacher.reviews.index'))
        ->assertSessionHas('status', 'review-reported');

    $review->refresh();

    expect($review->flagged_at)->not->toBeNull()
        ->and($review->flagged_by)->toBe($scenario['teacherUser']->id)
        ->and($review->flag_reason)->toBe('abusive')
        ->and($review->isVisible())->toBeTrue();

    Notification::assertSentTo($admin, ReviewFlagged::class);

    // Reporting twice is pointless — support already has it.
    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.reviews.flag', $review), ['reason' => 'unfair'])
        ->assertForbidden();
});

it('keeps strangers and students away from the teacher review tools', function () {
    $scenario = deliveredLesson();

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 2, 'comment' => 'Meh.']);

    $review = Review::query()->firstOrFail();

    $this->actingAs($scenario['student'])
        ->post(route('teacher.reviews.flag', $review), ['reason' => 'unfair'])
        ->assertForbidden();

    // Another teacher, fully onboarded but not this lesson's teacher.
    $otherTeacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->approved()->create([
        'user_id' => $otherTeacher->id,
        'completed_at' => now(),
    ]);

    $this->actingAs($otherTeacher)
        ->post(route('teacher.reviews.flag', $review), ['reason' => 'unfair'])
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('teacher.reviews.flag', $review), ['reason' => 'unfair'])
        ->assertForbidden();
});

it('shows the teacher their reviews with the rating summary', function () {
    $scenario = deliveredLesson();

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $scenario['booking']), ['rating' => 5, 'comment' => 'Superb lesson.']);

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.reviews.index'))
        ->assertOk()
        ->assertSee('Superb lesson.')
        ->assertSee('5.0')
        ->assertSee('Rating breakdown')
        ->assertSee('Report this review');
});
