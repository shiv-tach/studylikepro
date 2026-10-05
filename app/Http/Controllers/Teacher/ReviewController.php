<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlagReviewRequest;
use App\Models\Review;
use App\Models\TeacherProfile;
use App\Services\ReviewService;
use App\Services\TeacherStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The teacher side of the trust loop: what students said, and the reporting
 * action for something that looks wrong.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly TeacherStatsService $stats,
        private readonly ReviewService $reviews,
    ) {}

    public function index(Request $request): View
    {
        $teacher = $this->teacherProfile($request);

        return view('teacher.reviews.index', [
            'teacher' => $teacher,
            'breakdown' => $this->stats->ratingBreakdown($teacher),
            'reviews' => $teacher->reviews()
                ->visible()
                ->with(['student', 'booking.subject', 'booking.topic'])
                ->paginate(10),
            'reported' => $teacher->reviews()->flagged()->visible()->count(),
        ]);
    }

    public function flag(FlagReviewRequest $request, Review $review): RedirectResponse
    {
        Gate::authorize('flag', $review);

        $this->reviews->flag(
            $review,
            $request->user(),
            $request->validated('reason'),
            $request->validated('notes'),
        );

        return redirect()
            ->route('teacher.reviews.index')
            ->with('status', 'review-reported');
    }

    private function teacherProfile(Request $request): TeacherProfile
    {
        return $request->user()->teacherProfile()->firstOrFail();
    }
}
