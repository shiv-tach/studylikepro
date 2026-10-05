<?php

use App\Enums\BookingStatus;
use App\Enums\VerificationStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Review;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\TeacherStatsService;

/**
 * A completed lesson with a review the teacher has reported.
 *
 * @return array{review: Review, teacherUser: User, student: User}
 */
function flaggedReviewScenario(): array
{
    $scenario = paidBookingScenario();
    $scenario['booking']->update(['status' => BookingStatus::Completed, 'completed_at' => now()]);

    $review = Review::query()->create([
        'booking_id' => $scenario['booking']->id,
        'student_id' => $scenario['student']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
        'rating' => 1,
        'comment' => 'Terrible teacher, do not book.',
        'flagged_at' => now(),
        'flagged_by' => $scenario['teacherUser']->id,
        'flag_reason' => 'unfair',
        'flag_notes' => 'The student never joined the call.',
    ]);

    app(TeacherStatsService::class)->refresh($scenario['teacher']->refresh());

    return [
        'review' => $review->refresh(),
        'teacherUser' => $scenario['teacherUser'],
        'student' => $scenario['student'],
        'teacher' => $scenario['teacher'],
        'booking' => $scenario['booking'],
    ];
}

it('shows reported reviews next to the verification queue', function () {
    $admin = User::factory()->admin()->create();
    $scenario = flaggedReviewScenario();

    TeacherProfile::factory()->create([
        'user_id' => User::factory()->teacher()->create()->id,
        'verification_status' => VerificationStatus::Pending,
        'submitted_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.moderation.index'))
        ->assertOk()
        ->assertSee('Terrible teacher, do not book.')
        ->assertSee('The student never joined the call.')
        ->assertSee('Waiting on verification');
});

it('hides a reported review and recalculates the rating', function () {
    $admin = User::factory()->admin()->create();
    $scenario = flaggedReviewScenario();

    expect((float) $scenario['teacher']->refresh()->rating_avg)->toBe(1.0);

    $this->actingAs($admin)
        ->post(route('admin.moderation.reviews.hide', $scenario['review']))
        ->assertRedirect(route('admin.moderation.index'));

    $review = $scenario['review']->refresh();
    $teacher = $scenario['teacher']->refresh();

    expect($review->hidden_at)->not->toBeNull()
        ->and($review->hidden_by)->toBe($admin->id)
        ->and($review->flagged_at)->toBeNull()
        ->and($teacher->rating_count)->toBe(0)
        ->and((float) $teacher->rating_avg)->toBe(0.0);

    expect(ActivityLog::query()->first()->description)->toContain('Hid review #'.$review->id);
});

it('dismisses a report and keeps the review published', function () {
    $admin = User::factory()->admin()->create();
    $scenario = flaggedReviewScenario();

    $this->actingAs($admin)
        ->post(route('admin.moderation.reviews.dismiss', $scenario['review']))
        ->assertRedirect(route('admin.moderation.index'));

    $review = $scenario['review']->refresh();

    expect($review->flagged_at)->toBeNull()
        ->and($review->flag_reason)->toBeNull()
        ->and($review->hidden_at)->toBeNull()
        ->and((float) $scenario['teacher']->refresh()->rating_avg)->toBe(1.0);
});

it('only moderates reviews that were actually reported', function () {
    $admin = User::factory()->admin()->create();
    $scenario = flaggedReviewScenario();
    $unreported = Review::query()->create([
        'booking_id' => Booking::factory()->create(['status' => BookingStatus::Completed])->id,
        'student_id' => $scenario['student']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
        'rating' => 5,
        'comment' => 'Great lesson.',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.moderation.reviews.hide', $unreported))
        ->assertNotFound();

    expect($unreported->refresh()->hidden_at)->toBeNull();
});

it('counts reported reviews in the moderation queue', function () {
    $admin = User::factory()->admin()->create();
    $scenario = flaggedReviewScenario();
    $scenario['review']->forceFill(['hidden_at' => now()])->save();

    $this->actingAs($admin)
        ->get(route('admin.moderation.index'))
        ->assertOk()
        ->assertSee('Nothing reported right now.');
});
