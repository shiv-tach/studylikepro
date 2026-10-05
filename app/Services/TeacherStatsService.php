<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\TeacherProfile;

/**
 * The numbers the marketplace shows about a teacher. They live on the profile
 * row so search and sorting stay a single query, and are recomputed from the
 * source rows whenever a lesson is delivered or a review changes.
 */
class TeacherStatsService
{
    public function refresh(TeacherProfile $teacher): TeacherProfile
    {
        $visible = Review::query()->forTeacher($teacher->id)->visible();

        $count = (clone $visible)->count();
        $average = (clone $visible)->avg('rating');

        $teacher->forceFill([
            'rating_count' => $count,
            'rating_avg' => $average === null ? null : round((float) $average, 2),
            'lessons_completed_count' => Booking::query()
                ->where('teacher_profile_id', $teacher->id)
                ->where('status', BookingStatus::Completed->value)
                ->count(),
        ])->save();

        return $teacher;
    }

    /**
     * Star distribution (5 → 1) for the profile and teacher dashboards.
     *
     * @return array<int, int> star => count
     */
    public function ratingBreakdown(TeacherProfile $teacher): array
    {
        $counts = Review::query()
            ->forTeacher($teacher->id)
            ->visible()
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $breakdown = [];

        foreach (range(5, 1) as $star) {
            $breakdown[$star] = (int) ($counts[$star] ?? 0);
        }

        return $breakdown;
    }
}
