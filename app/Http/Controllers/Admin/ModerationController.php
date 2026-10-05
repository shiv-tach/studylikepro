<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\TeacherProfile;
use App\Services\ActivityLogger;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Moderation queues: reviews teachers have reported, and teacher applications
 * waiting for another look.
 */
class ModerationController extends Controller
{
    public function index(Request $request): View
    {
        $reported = Review::query()
            ->flagged()
            ->visible()
            ->with(['teacherProfile.user', 'student', 'booking.subject', 'booking.topic'])
            ->latest('flagged_at')
            ->paginate(15, ['*'], 'reviews');

        return view('admin.moderation.index', [
            'reviews' => $reported,
            'verificationQueue' => TeacherProfile::query()
                ->where('verification_status', VerificationStatus::Pending)
                ->with('user')
                ->oldest('submitted_at')
                ->limit(10)
                ->get(),
            'pendingVerifications' => TeacherProfile::query()->where('verification_status', VerificationStatus::Pending)->count(),
            'hiddenReviews' => Review::query()->whereNotNull('hidden_at')->count(),
        ]);
    }

    /**
     * Take a reported review off the public profile (ratings exclude it too).
     */
    public function hideReview(Request $request, Review $review, ReviewService $reviews, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($review->flagged_at !== null, 404);

        $reviews->hide($review, $request->user());

        $activity->describe('Hid review #'.$review->id.' after a report from '.($review->teacherProfile?->user?->name ?? 'a teacher'));

        return redirect()
            ->route('admin.moderation.index')
            ->with('status', 'review-hidden');
    }

    /**
     * Keep the review but clear the report — support decided it stands.
     */
    public function dismissReview(Request $request, Review $review, ActivityLogger $activity): RedirectResponse
    {
        abort_unless($review->flagged_at !== null, 404);

        $review->forceFill([
            'flagged_at' => null,
            'flagged_by' => null,
            'flag_reason' => null,
            'flag_notes' => null,
        ])->save();

        $activity->describe('Dismissed the report on review #'.$review->id);

        return redirect()
            ->route('admin.moderation.index')
            ->with('status', 'review-report-dismissed');
    }
}
