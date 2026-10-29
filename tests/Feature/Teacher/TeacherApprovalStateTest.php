<?php

use App\Enums\VerificationStatus;
use App\Models\TeacherProfile;
use App\Support\TeacherApproval;
use App\Support\TeacherOnboarding;

test('the approval stage follows the verification status', function () {
    expect(TeacherApproval::stage(null))->toBe(TeacherApproval::STAGE_SETUP)
        ->and(TeacherApproval::stage(TeacherProfile::factory()->make(['verification_status' => VerificationStatus::Draft])))->toBe(TeacherApproval::STAGE_SETUP)
        ->and(TeacherApproval::stage(TeacherProfile::factory()->make(['verification_status' => VerificationStatus::Pending])))->toBe(TeacherApproval::STAGE_REVIEW)
        ->and(TeacherApproval::stage(TeacherProfile::factory()->make(['verification_status' => VerificationStatus::Approved])))->toBe(TeacherApproval::STAGE_LIVE)
        ->and(TeacherApproval::stage(TeacherProfile::factory()->make(['verification_status' => VerificationStatus::Rejected])))->toBe(TeacherApproval::STAGE_ACTION);
});

test('the expected decision date counts business days from submission', function () {
    // Friday + 2 business days = Tuesday, not Sunday.
    $profile = TeacherProfile::factory()->make([
        'verification_status' => VerificationStatus::Pending,
        'submitted_at' => \Illuminate\Support\Carbon::parse('2026-10-23 09:00:00'),
    ]);

    expect(TeacherApproval::expectedDecisionAt($profile)->toDateString())->toBe('2026-10-27');
});

test('only a pending application can be overdue', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28 12:00:00'));

    $late = TeacherProfile::factory()->make([
        'verification_status' => VerificationStatus::Pending,
        'submitted_at' => now()->subWeekdays(3),
    ]);
    $fresh = TeacherProfile::factory()->make([
        'verification_status' => VerificationStatus::Pending,
        'submitted_at' => now()->subHour(),
    ]);
    $approved = TeacherProfile::factory()->make([
        'verification_status' => VerificationStatus::Approved,
        'submitted_at' => now()->subWeekdays(5),
    ]);

    expect(TeacherApproval::isOverdue($late))->toBeTrue()
        ->and(TeacherApproval::isOverdue($fresh))->toBeFalse()
        ->and(TeacherApproval::isOverdue($approved))->toBeFalse();
});

test('the setup checklist ends on a review step that only turns green when approved', function () {
    $pending = TeacherProfile::factory()->make([
        'verification_status' => VerificationStatus::Pending,
        'submitted_at' => now(),
    ]);
    $approved = TeacherProfile::factory()->make([
        'verification_status' => VerificationStatus::Approved,
    ]);

    $pendingReview = collect(TeacherOnboarding::steps($pending))->firstWhere('key', TeacherOnboarding::STEP_REVIEW);
    $approvedReview = collect(TeacherOnboarding::steps($approved))->firstWhere('key', TeacherOnboarding::STEP_REVIEW);

    expect($pendingReview['done'])->toBeFalse()
        ->and($pendingReview['state'])->toBe('pending')
        ->and($pendingReview['reachable'])->toBeTrue()
        ->and($approvedReview['done'])->toBeTrue()
        ->and($approvedReview['state'])->toBeNull()
        ->and(TeacherOnboarding::steps($pending))->toHaveCount(4)
        ->and(collect(TeacherOnboarding::steps($pending))->pluck('url')->all())->toBe([
            route('teacher.profile'),
            route('teacher.availability.index'),
            route('teacher.verification'),
            route('teacher.approval.status'),
        ]);
});

test('a rejected application shows the review step as action needed', function () {
    $rejected = TeacherProfile::factory()->make([
        'verification_status' => VerificationStatus::Rejected,
        'submitted_at' => now()->subDay(),
    ]);

    $review = collect(TeacherOnboarding::steps($rejected))->firstWhere('key', TeacherOnboarding::STEP_REVIEW);

    expect($review['state'])->toBe('action')
        ->and($review['done'])->toBeFalse();
});

test('the review step is not reachable before the application is submitted', function () {
    $draft = TeacherProfile::factory()->make([
        'verification_status' => VerificationStatus::Draft,
    ]);

    $review = collect(TeacherOnboarding::steps($draft))->firstWhere('key', TeacherOnboarding::STEP_REVIEW);

    expect($review['reachable'])->toBeFalse()
        ->and($review['done'])->toBeFalse();
});
