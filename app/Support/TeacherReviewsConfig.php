<?php

namespace App\Support;

use App\Models\Review;
use App\Models\TeacherProfile;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The JSON island behind /teacher/reviews.
 *
 * Blade still owns every label, formatted date, percentage, route and the
 * replayed validation state, so the Vue app never formats a date, builds a
 * plural rule or hardcodes a URL. The page answers two questions — how am I
 * rated, and what did students say — so the payload carries the summary, the
 * star distribution and the current page of visible reviews.
 *
 * A review the teacher already reported ships without a flag URL: the policy
 * refuses a second report, so the card must not offer one.
 */
final class TeacherReviewsConfig
{
    /**
     * @param  LengthAwarePaginator<int, Review>  $reviews  The current page of visible reviews.
     * @param  array<int, int>  $breakdown  Star => count, 5 down to 1.
     */
    public static function build(
        TeacherProfile $teacher,
        LengthAwarePaginator $reviews,
        array $breakdown,
        int $reported,
        string $preset,
    ): array {
        $total = array_sum($breakdown);

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'csrfToken' => csrf_token(),
            'theme' => ['preset' => $preset],
            'notices' => self::notices(),
            'stats' => self::stats($teacher, $reported),
            'breakdown' => self::breakdown($breakdown, $total),
            'reviews' => collect($reviews->items())
                ->map(fn (Review $review) => self::card($review))
                ->values()
                ->all(),
            'flag' => self::flag(),
            'copy' => self::copy(),
            'urls' => [
                'index' => route('teacher.reviews.index'),
                'profile' => route('teachers.show', $teacher),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function stats(TeacherProfile $teacher, int $reported): array
    {
        $count = (int) $teacher->rating_count;

        return [
            'rating' => $count > 0 ? number_format((float) $teacher->rating_avg, 1) : '—',
            'ratingCount' => $count,
            'reviewsLabel' => trans_choice(':count review|:count reviews', $count, ['count' => $count]),
            'lessons' => (int) $teacher->lessons_completed_count,
            'reported' => $reported,
        ];
    }

    /**
     * @param  array<int, int>  $breakdown
     * @return array<string, mixed>
     */
    private static function breakdown(array $breakdown, int $total): array
    {
        return [
            'total' => $total,
            'rows' => collect($breakdown)
                ->map(fn (int $count, int $star) => [
                    'star' => $star,
                    'label' => $star.' ★',
                    'count' => $count,
                    'percent' => $total > 0 ? (int) round($count / $total * 100) : 0,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function card(Review $review): array
    {
        $booking = $review->booking;

        return [
            'id' => $review->id,
            'reviewer' => $review->reviewerName(),
            'rating' => (int) $review->rating,
            'stars' => [
                'filled' => str_repeat('★', (int) $review->rating),
                'empty' => str_repeat('★', max(0, 5 - (int) $review->rating)),
            ],
            // The lesson is the most precise name for what was reviewed; a
            // booking without one still has its subject.
            'scopeLabel' => $booking?->lesson?->name ?? $booking?->subject?->name ?? __('Tutoring lesson'),
            // A date, not a timestamp: the lesson's clock time is on its own page.
            'lessonDate' => $booking?->starts_at?->format('d M Y'),
            'reviewedLabel' => __('reviewed :when', ['when' => $review->created_at->diffForHumans()]),
            'edited' => $review->wasEdited(),
            'comment' => trim((string) $review->comment) ?: null,
            'flagged' => $review->flagged_at !== null,
            'flagReasonLabel' => $review->flagReasonLabel(),
            // Reporting twice is refused by the policy, so an already reported
            // review never carries an action URL.
            'flagUrl' => $review->flagged_at === null ? route('teacher.reviews.flag', $review) : null,
        ];
    }

    /**
     * The report dialog's state, including a rejected submit: the hidden
     * `editing_review` field identifies which card's dialog to reopen, so the
     * typed reason and notes land back on the right review.
     *
     * @return array<string, mixed>
     */
    private static function flag(): array
    {
        $openId = old('editing_review');

        return [
            'openId' => $openId === null || $openId === '' ? null : (int) $openId,
            'reason' => old('reason') ?: null,
            'notes' => old('notes') ?: null,
            'errors' => [
                'reason' => session('errors')?->first('reason') ?: null,
                'notes' => session('errors')?->first('notes') ?: null,
            ],
            'reasons' => collect(Review::FLAG_REASONS)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => __($label)])
                ->values()
                ->all(),
            'maxNotes' => 500,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function notices(): array
    {
        if (session('status') !== 'review-reported') {
            return [];
        }

        return [[
            'tone' => 'emerald',
            'icon' => 'check-circle',
            'title' => __('Review reported'),
            'message' => __('Thanks — support has this review and will look at it before long.'),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function copy(): array
    {
        return [
            'hero' => [
                'eyebrow' => __('Student feedback'),
                'title' => __('Reviews received'),
                'intro' => __('What students said after their lessons — and how to report something that looks wrong.'),
                'profileCta' => __('See my public profile'),
                'statsLabel' => __('Your rating at a glance'),
                'ratingValue' => __(':rating /5'),
                'reviewsChip' => ':count review|:count reviews',
                'lessonsChip' => ':count lesson taught|:count lessons taught',
                'reportedChip' => ':count under review|:count under review',
                'profileNote' => __('Your public profile shows every visible review, exactly as students left it.'),
            ],
            'stats' => [
                'rating' => __('Rating'),
                'lessons' => __('Lessons taught'),
                'lessonsCaption' => __('Delivered on Studylikepro'),
                'reported' => __('Reported'),
                'reportedCaption' => __('Reviews under review by support'),
            ],
            'breakdown' => [
                'title' => __('Rating breakdown'),
                'empty' => __('No reviews yet — students can review a lesson as soon as you mark it delivered.'),
                'starsLabel' => __(':star star|:star stars'),
                'countLabel' => __(':count review|:count reviews'),
                'percentLabel' => __(':percent% of your reviews'),
            ],
            'card' => [
                'outOfFive' => __(':rating out of 5'),
                'edited' => __('edited'),
                'noComment' => __('No comment left.'),
                'reportCta' => __('Report this review'),
                'reportedChip' => __('Reported — under review'),
                'reportedTitle' => __('Support is looking at this review.'),
            ],
            'dialog' => [
                'title' => __('Report this review'),
                'intro' => __('Support reviews reports and can take a review off your public profile. Ratings that break the rules are removed; honest criticism stays.'),
                'reasonLabel' => __('What is wrong with it?'),
                'notesLabel' => __('Anything support should know? (optional)'),
                'notesHint' => __('Up to :max characters.', ['max' => 500]),
                'cancel' => __('Cancel'),
                'submit' => __('Send to support'),
                'close' => __('Close'),
            ],
            'empty' => [
                'title' => __('No reviews yet'),
                'body' => __('Students can review a lesson as soon as you mark it delivered. Strong lessons and clear communication are what turn into stars.'),
            ],
            'toast' => [
                'label' => __('Notifications'),
                'dismiss' => __('Dismiss'),
            ],
        ];
    }
}
