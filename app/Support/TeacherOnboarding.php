<?php

namespace App\Support;

use App\Models\TeacherOnboardingDraft;
use App\Models\TeacherProfile;

/**
 * The teacher onboarding wizard, defined once so the wizard, the sidebar, the
 * middleware and every step page cannot describe the same flow differently.
 *
 * Order: 1 welcome -> 2 about you -> 3 teaching preferences -> 4 weekly
 * availability -> 5 verification. The availability step is the gate between the
 * profile and the documents (a teacher without a weekly range has nothing a
 * student can book), and the profile itself is saved in two focused steps so a
 * phone never has to scroll one long form.
 */
final class TeacherOnboarding
{
    public const STEP_WELCOME = 'welcome';

    public const STEP_ABOUT = 'about';

    public const STEP_PREFERENCES = 'preferences';

    public const STEP_AVAILABILITY = 'availability';

    public const STEP_VERIFICATION = 'verification';

    /**
     * The review phase. It is not a form: it is the state the teacher is in
     * after submitting, and it only turns green when an admin approves.
     */
    public const STEP_REVIEW = 'review';

    /**
     * The last step of the *guest* wizard: the account is created here, after
     * the mobile number is proved with the SMS passcode.
     */
    public const STEP_ACCOUNT = 'account';

    /** The data steps, in order (the welcome screen is an intro, not a form). */
    public const DATA_STEPS = [
        self::STEP_ABOUT,
        self::STEP_PREFERENCES,
        self::STEP_AVAILABILITY,
        self::STEP_VERIFICATION,
    ];

    /**
     * Every step of the wizard, with the state the progress rail renders.
     *
     * @return list<array{key: string, number: int, label: string, hint: string, url: string, done: bool, reachable: bool, state?: string|null}>
     */
    public static function steps(?TeacherProfile $profile, ?TeacherOnboardingDraft $draft = null): array
    {
        $started = $profile !== null || ($draft !== null && ! $draft->isEmpty());
        $aboutDone = (bool) $profile?->hasCompletedAboutStep();
        $preferencesDone = (bool) $profile?->hasCompletedPreferencesStep();
        $profileComplete = (bool) $profile?->isComplete();
        $hasAvailability = $profile !== null && self::hasAvailability($profile);
        $verificationSubmitted = (bool) $profile?->hasSubmittedVerification();
        $approved = (bool) $profile?->isApproved();
        $rejected = (bool) $profile?->isRejected();

        return [
            [
                'key' => self::STEP_WELCOME,
                'number' => 1,
                'label' => __('Getting started'),
                'hint' => $started
                    ? __('You can leave at any time — your answers are kept.')
                    : __('What to prepare and how long setup takes.'),
                'url' => route('teacher.onboarding.show'),
                'done' => $started,
                'reachable' => true,
            ],
            [
                'key' => self::STEP_ABOUT,
                'number' => 2,
                'label' => __('About you'),
                'hint' => __('Headline, biography and teaching experience.'),
                'url' => route('teacher.onboarding.profile'),
                'done' => $aboutDone,
                'reachable' => true,
            ],
            [
                'key' => self::STEP_PREFERENCES,
                'number' => 3,
                'label' => __('Teaching preferences'),
                'hint' => ! $aboutDone
                    ? __('Unlocks once you save the About you step.')
                    : __('Languages, hourly rate and profile photo.'),
                'url' => route('teacher.onboarding.preferences'),
                'done' => $preferencesDone,
                'reachable' => $aboutDone || $preferencesDone,
            ],
            [
                'key' => self::STEP_AVAILABILITY,
                'number' => 4,
                'label' => __('Weekly availability'),
                'hint' => ! $profileComplete
                    ? __('Unlocks once your profile is saved.')
                    : ($hasAvailability
                        ? __('Students can book lessons inside these hours.')
                        : __('Load a suggested week, then adjust the days and hours you teach.')),
                'url' => route('teacher.onboarding.availability'),
                'done' => $hasAvailability,
                'reachable' => $profileComplete,
            ],
            [
                'key' => self::STEP_VERIFICATION,
                'number' => 5,
                'label' => __('Verification'),
                'hint' => match (true) {
                    ! $profileComplete => __('Unlocks once your profile is saved.'),
                    ! $hasAvailability => __('Unlocks once you set your weekly availability.'),
                    $verificationSubmitted => __('Documents submitted — our team is reviewing them.'),
                    default => __('Upload your documents, choose your subjects and submit them for review.'),
                },
                'url' => route('teacher.onboarding.verification'),
                'done' => $verificationSubmitted,
                'reachable' => $profileComplete && $hasAvailability,
            ],
            [
                'key' => self::STEP_REVIEW,
                'number' => 6,
                'label' => __('Review & approval'),
                'hint' => match (true) {
                    $approved => __('Approved — you are live. Students can find and book you.'),
                    $rejected => __('Action needed — update your application and resubmit.'),
                    $verificationSubmitted => __('Our team is reviewing — usually 1–2 business days.'),
                    default => __('Unlocks when you submit your application.'),
                },
                'url' => route('teacher.onboarding.submitted'),
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
     * The step the teacher should resume at: the first one that is not done and
     * is reachable, or verification when everything else is finished. The review
     * step is a state, not a form, so it is never a resume target.
     */
    public static function resumeStep(?TeacherProfile $profile, ?TeacherOnboardingDraft $draft = null): string
    {
        $steps = self::steps($profile, $draft);

        if (! ($steps[0]['done'] ?? false)) {
            return self::STEP_WELCOME;
        }

        foreach ($steps as $step) {
            if ($step['key'] === self::STEP_REVIEW) {
                continue;
            }

            if (! $step['done'] && $step['reachable']) {
                return $step['key'];
            }
        }

        return self::STEP_VERIFICATION;
    }

    /**
     * The route name of the step the teacher still owes, used by the onboarding
     * gate in the middleware.
     */
    public static function nextRouteName(?TeacherProfile $profile, ?TeacherOnboardingDraft $draft = null): string
    {
        return match (self::resumeStep($profile, $draft)) {
            self::STEP_WELCOME => 'teacher.onboarding.show',
            self::STEP_ABOUT => 'teacher.onboarding.profile',
            self::STEP_PREFERENCES => 'teacher.onboarding.preferences',
            self::STEP_AVAILABILITY => 'teacher.onboarding.availability',
            default => 'teacher.onboarding.verification',
        };
    }

    /**
     * The flash status the middleware attaches to the redirect, so the step page
     * can explain why the teacher landed there.
     */
    public static function nextStatus(?TeacherProfile $profile, ?TeacherOnboardingDraft $draft = null): string
    {
        return match (self::resumeStep($profile, $draft)) {
            self::STEP_WELCOME, self::STEP_ABOUT => 'complete-your-profile',
            self::STEP_PREFERENCES => 'complete-your-preferences',
            self::STEP_AVAILABILITY => 'complete-your-availability',
            default => 'complete-your-verification',
        };
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

    /**
     * The steps of the *guest* wizard an invite link opens before any account
     * exists (see `PendingTeacherSignup`).
     *
     * It mirrors the first four steps of the signed-in wizard — same labels and
     * numbers — and ends with the account itself, because identity and password
     * are deliberately the last thing a visitor is asked for. Verification
     * follows immediately after sign-in, so it is explained on the account step
     * instead of being listed as a locked row here.
     *
     * @param  array<string, mixed>  $answers  the answers already submitted in the guest wizard
     * @return list<array{key: string, number: int, label: string, hint: string, url: string, done: bool, reachable: bool}>
     */
    public static function guestSteps(array $answers = []): array
    {
        $aboutDone = ! empty($answers[self::STEP_ABOUT]);
        $preferencesDone = ! empty($answers[self::STEP_PREFERENCES]);
        $availabilityDone = ! empty($answers[self::STEP_AVAILABILITY]);

        return [
            [
                'key' => self::STEP_WELCOME,
                'number' => 1,
                'label' => __('Getting started'),
                'hint' => __('What to prepare and how long setup takes.'),
                'url' => route('join.start'),
                'done' => $aboutDone,
                'reachable' => true,
            ],
            [
                'key' => self::STEP_ABOUT,
                'number' => 2,
                'label' => __('About you'),
                'hint' => __('Headline, biography and teaching experience.'),
                'url' => route('join.profile'),
                'done' => $aboutDone,
                'reachable' => true,
            ],
            [
                'key' => self::STEP_PREFERENCES,
                'number' => 3,
                'label' => __('Teaching preferences'),
                'hint' => $aboutDone
                    ? __('Languages, hourly rate and profile photo.')
                    : __('Unlocks once you fill in the About you step.'),
                'url' => route('join.preferences'),
                'done' => $preferencesDone,
                'reachable' => $aboutDone || $preferencesDone,
            ],
            [
                'key' => self::STEP_AVAILABILITY,
                'number' => 4,
                'label' => __('Weekly availability'),
                'hint' => $preferencesDone
                    ? __('Students can book lessons inside these hours.')
                    : __('Unlocks once you save your teaching preferences.'),
                'url' => route('join.availability'),
                'done' => $availabilityDone,
                'reachable' => $preferencesDone || $availabilityDone,
            ],
            [
                'key' => self::STEP_ACCOUNT,
                'number' => 5,
                'label' => __('Create your account'),
                'hint' => $availabilityDone
                    ? __('Your name, password and a code we text to your mobile.')
                    : __('Unlocks once you set your weekly availability.'),
                'url' => route('join.account'),
                'done' => false,
                'reachable' => $availabilityDone,
            ],
        ];
    }
}
