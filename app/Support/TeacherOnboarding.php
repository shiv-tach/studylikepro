<?php

namespace App\Support;

use App\Models\TeacherProfile;

/**
 * The teacher setup checklist, defined once so the sidebar, the step rails and
 * the approval screen cannot describe the same journey differently.
 *
 * The invite survey collects the profile answers, so the signed-in setup is
 * short: confirm the profile on /teacher/profile, tune the weekly hours on
 * /teacher/availability, send the documents from /teacher/verification, and
 * wait for the decision on /teacher/approval.
 */
final class TeacherOnboarding
{
    public const STEP_PROFILE = 'profile';

    public const STEP_AVAILABILITY = 'availability';

    public const STEP_VERIFICATION = 'verification';

    /**
     * The review phase. It is not a form: it is the state the teacher is in
     * after submitting, and it only turns green when an admin approves.
     */
    public const STEP_REVIEW = 'review';

    /**
     * Every step, with the state the progress rails render.
     *
     * @return list<array{key: string, number: int, label: string, hint: string, url: string, done: bool, reachable: bool, state?: string|null}>
     */
    public static function steps(?TeacherProfile $profile): array
    {
        $hasProfile = $profile !== null;
        $profileComplete = (bool) $profile?->isComplete();
        $hasAvailability = $hasProfile && self::hasAvailability($profile);
        $verificationSubmitted = (bool) $profile?->hasSubmittedVerification();
        $approved = (bool) $profile?->isApproved();
        $rejected = (bool) $profile?->isRejected();

        return [
            [
                'key' => self::STEP_PROFILE,
                'number' => 1,
                'label' => __('Teaching profile'),
                'hint' => __('Headline, biography, languages and your hourly rate.'),
                'url' => route('teacher.profile'),
                'done' => $profileComplete,
                'reachable' => true,
            ],
            [
                'key' => self::STEP_AVAILABILITY,
                'number' => 2,
                'label' => __('Weekly availability'),
                'hint' => $hasAvailability
                    ? __('Students can book lessons inside these hours.')
                    : __('Set the days and hours you teach — the survey week is already loaded.'),
                'url' => route('teacher.availability.index'),
                'done' => $hasAvailability,
                'reachable' => $hasProfile,
            ],
            [
                'key' => self::STEP_VERIFICATION,
                'number' => 3,
                'label' => __('Verification'),
                'hint' => match (true) {
                    $verificationSubmitted => __('Documents submitted — our team is reviewing them.'),
                    $hasProfile => __('Upload your documents, confirm your subjects and submit them for review.'),
                    default => __('Unlocks once your profile exists.'),
                },
                'url' => route('teacher.verification'),
                'done' => $verificationSubmitted,
                'reachable' => $hasProfile,
            ],
            [
                'key' => self::STEP_REVIEW,
                'number' => 4,
                'label' => __('Review & approval'),
                'hint' => match (true) {
                    $approved => __('Approved — you are live. Students can find and book you.'),
                    $rejected => __('Action needed — update your application and resubmit.'),
                    $verificationSubmitted => __('Our team is reviewing — usually 1–2 business days.'),
                    default => __('Unlocks when you submit your application.'),
                },
                'url' => route('teacher.approval.status'),
                // Green is reserved for an actual approval: submitting only moves
                // the teacher into the review state.
                'done' => $approved,
                'reachable' => $verificationSubmitted,
                'state' => match (true) {
                    $approved => null,
                    $rejected => 'action',
                    $verificationSubmitted => 'pending',
                    default => null,
                },
            ],
        ];
    }

    /**
     * Whether the teacher has at least one weekly range. Respects a loaded
     * relation so a page that already eager-loaded the slots costs no query.
     */
    public static function hasAvailability(TeacherProfile $profile): bool
    {
        return $profile->relationLoaded('availabilitySlots')
            ? $profile->availabilitySlots->isNotEmpty()
            : $profile->availabilitySlots()->exists();
    }
}
