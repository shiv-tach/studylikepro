<?php

namespace App\Support;

use App\Models\TeacherProfile;

/**
 * The teacher onboarding steps, defined once so the three onboarding screens,
 * the Blade tracker and the app-shell sidebar cannot describe them differently.
 *
 * Order: 1 teaching profile -> 2 weekly availability -> 3 verification. The
 * availability step is the gate between the profile and the documents: a
 * teacher without a single weekly range has nothing a student can book, so the
 * verification page refuses to open until a range exists.
 */
final class TeacherOnboarding
{
    /**
     * @return list<array{number: int, label: string, hint: string, url: string, done: bool, reachable: bool}>
     */
    public static function steps(?TeacherProfile $profile): array
    {
        $profileComplete = (bool) $profile?->isComplete();
        $hasAvailability = $profile !== null && self::hasAvailability($profile);
        $verificationSubmitted = (bool) $profile?->hasSubmittedVerification();

        return [
            [
                'number' => 1,
                'label' => __('Teaching profile'),
                'hint' => __('Headline, bio, languages and hourly rate.'),
                'url' => route('teacher.profile'),
                'done' => $profileComplete,
                'reachable' => true,
            ],
            [
                'number' => 2,
                'label' => __('Weekly availability'),
                'hint' => ! $profileComplete
                    ? __('Unlocks once you save your profile.')
                    : ($hasAvailability
                        ? __('Students can book lessons inside these hours.')
                        : __('Load a suggested week, then adjust the days and hours you teach.')),
                'url' => route('teacher.availability.setup'),
                'done' => $hasAvailability,
                'reachable' => $profileComplete,
            ],
            [
                'number' => 3,
                'label' => __('Verification'),
                'hint' => match (true) {
                    ! $profileComplete => __('Unlocks once you save your profile.'),
                    ! $hasAvailability => __('Unlocks once you set your weekly availability.'),
                    $verificationSubmitted => __('Documents submitted — our team is reviewing them.'),
                    default => __('Upload your documents, choose your subjects and submit them for review.'),
                },
                'url' => route('teacher.verification'),
                'done' => $verificationSubmitted,
                'reachable' => $profileComplete && $hasAvailability,
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
