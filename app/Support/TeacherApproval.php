<?php

namespace App\Support;

use App\Models\TeacherProfile;
use Illuminate\Support\Carbon;

/**
 * The teacher approval lifecycle, defined once so the wizard, the sidebar, the
 * status page and the shell banner cannot describe the same teacher differently.
 *
 * Setup (draft) -> Review (pending) -> Live (approved), with a detour through
 * Action needed (rejected) before the teacher resubmits.
 */
final class TeacherApproval
{
    public const STAGE_SETUP = 'setup';

    public const STAGE_REVIEW = 'review';

    public const STAGE_LIVE = 'live';

    public const STAGE_ACTION = 'action';

    /** Business days the review is promised to take. */
    public const REVIEW_BUSINESS_DAYS = 2;

    public static function stage(?TeacherProfile $profile): string
    {
        return match (true) {
            $profile === null => self::STAGE_SETUP,
            $profile->isApproved() => self::STAGE_LIVE,
            $profile->isRejected() => self::STAGE_ACTION,
            $profile->isPending() => self::STAGE_REVIEW,
            default => self::STAGE_SETUP,
        };
    }

    public static function label(?TeacherProfile $profile): string
    {
        return match (self::stage($profile)) {
            self::STAGE_REVIEW => __('In review'),
            self::STAGE_LIVE => __('Live'),
            self::STAGE_ACTION => __('Action needed'),
            default => __('Setup'),
        };
    }

    /**
     * Semantic status colors, independent of the primary accent theme. Amber is
     * "waiting", emerald is earned only by an actual approval.
     */
    public static function badgeClasses(?TeacherProfile $profile): string
    {
        return match (self::stage($profile)) {
            self::STAGE_REVIEW => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::STAGE_LIVE => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::STAGE_ACTION => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    public static function isReviewing(?TeacherProfile $profile): bool
    {
        return self::stage($profile) === self::STAGE_REVIEW;
    }

    public static function isLive(?TeacherProfile $profile): bool
    {
        return self::stage($profile) === self::STAGE_LIVE;
    }

    public static function needsAction(?TeacherProfile $profile): bool
    {
        return self::stage($profile) === self::STAGE_ACTION;
    }

    /**
     * The date the promised review window ends, counted in business days from
     * submission so a Friday application is not "late" on Sunday.
     */
    public static function expectedDecisionAt(?TeacherProfile $profile): ?Carbon
    {
        if ($profile?->submitted_at === null) {
            return null;
        }

        return $profile->submitted_at->copy()->addWeekdays(self::REVIEW_BUSINESS_DAYS);
    }

    /**
     * The review promise has passed: the teacher should get an apology and a
     * way to reach the team instead of an open-ended wait.
     */
    public static function isOverdue(?TeacherProfile $profile): bool
    {
        if (! self::isReviewing($profile)) {
            return false;
        }

        $expected = self::expectedDecisionAt($profile);

        return $expected !== null && $expected->isPast();
    }
}
