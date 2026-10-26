<?php

namespace App\Support;

use App\Enums\VerificationStatus;
use App\Models\TeacherOnboardingDraft;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;

/**
 * The JSON island behind `/teacher/profile` and the two profile steps of the
 * onboarding wizard.
 *
 * One builder for all three screens keeps the field limits, the language
 * catalog, the live public-card preview and the validation messages identical,
 * while `stepKey` decides which cards the Vue app renders and where the form
 * posts.
 */
final class TeacherProfileConfig
{
    /**
     * @param  'workspace'|'onboarding'  $mode
     * @param  'all'|'about'|'preferences'  $stepKey
     * @param  Collection<int, mixed>|null  $subjects
     * @return array<string, mixed>
     */
    public static function build(
        User $user,
        ?TeacherProfile $profile,
        ?TeacherOnboardingDraft $draft,
        MessageBag $errors,
        string $themePreset = 'classic',
        string $mode = 'workspace',
        string $stepKey = 'all',
        ?Collection $subjects = null,
    ): array {
        $profileComplete = (bool) $profile?->isComplete();
        $verificationSubmitted = (bool) $profile?->hasSubmittedVerification();
        $verificationStatus = $profile?->verification_status;
        $ratingCount = (int) ($profile?->rating_count ?? 0);
        $hasAvailability = $profile !== null && TeacherOnboarding::hasAvailability($profile);

        $subjects ??= $profile?->subjects ?? collect();
        $onboarding = $mode === 'onboarding';
        $draftStep = $onboarding ? ($draft?->step($stepKey) ?? []) : [];
        $restored = $draftStep !== [] && ! session()->hasOldInput();

        $source = $restored ? $draftStep : [];
        $languages = config('studylikepro.languages');

        $steps = TeacherOnboarding::steps($profile, $draft);
        $currentStep = match ($stepKey) {
            'about' => 2,
            'preferences' => 3,
            default => 1,
        };

        $values = [
            'headline' => old('headline', $source['headline'] ?? $profile?->headline) ?? '',
            'bio' => old('bio', $source['bio'] ?? $profile?->bio) ?? '',
            'experienceYears' => old('experience_years', $source['experience_years'] ?? $profile?->experience_years) ?? '',
            'education' => old('education', $source['education'] ?? $profile?->education) ?? '',
            'languages' => array_values(old('languages', $source['languages'] ?? $profile?->languages ?? ['English'])),
            'hourlyRate' => old('hourly_rate', $source['hourly_rate'] ?? ($profile && (int) $profile->hourly_rate_minor > 0 ? (int) $profile->hourlyRate() : '')) ?? '',
        ];

        $updateUrl = match (true) {
            $onboarding && $stepKey === 'about' => route('teacher.onboarding.profile.update'),
            $onboarding && $stepKey === 'preferences' => route('teacher.onboarding.preferences.update'),
            default => route('teacher.profile.update'),
        };

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'csrfToken' => csrf_token(),
            'theme' => ['preset' => $themePreset],
            'mode' => $mode,
            'stepKey' => $stepKey,
            'status' => [
                'updated' => session('status') === 'profile-updated',
                'incomplete' => session('status') === 'complete-your-profile',
                'preferencesMissing' => session('status') === 'complete-your-preferences',
                'aboutSaved' => session('status') === 'about-saved',
                'preferencesSaved' => session('status') === 'preferences-saved',
            ],
            'teacher' => [
                'name' => $user->name,
                'firstName' => $user->displayName(),
                'initials' => (string) Str::of($user->name)->substr(0, 2),
                'avatarUrl' => $user->avatarUrl(),
            ],
            'profile' => [
                'isComplete' => $profileComplete,
                'hasSubmittedVerification' => $verificationSubmitted,
                'hasAvailability' => $hasAvailability,
                'aboutComplete' => (bool) $profile?->hasCompletedAboutStep(),
                'preferencesComplete' => (bool) $profile?->hasCompletedPreferencesStep(),
            ],
            'languages' => $languages,
            'limits' => [
                'headline' => 120,
                'bio' => 2000,
                'education' => 255,
                'experienceYears' => 60,
                'minRate' => 100,
                'maxRate' => 100000,
            ],
            'values' => $values,
            // What the public directory card shows and the form cannot change yet.
            'preview' => [
                'rating' => $ratingCount > 0 ? number_format((float) $profile->rating_avg, 1) : null,
                'ratingCount' => $ratingCount,
                'isApproved' => $verificationStatus === VerificationStatus::Approved,
                'verificationLabel' => $verificationStatus?->isSubmitted()
                    ? $verificationStatus->label()
                    : __('Awaiting verification'),
                'subjects' => $subjects->take(3)->map(fn ($subject) => [
                    'label' => trim(($subject->icon ?? '').' '.$subject->name),
                ])->values(),
                'extraSubjects' => max(0, $subjects->count() - 3),
                'metaLabel' => $profile?->gradeScopeLabel(),
                'nextSlot' => null,
                'currencySymbol' => platform_settings()->currencySymbol(),
            ],
            // Laravel owns the wording of every message; the app only decides
            // where it is shown, so the two never drift apart.
            'errors' => [
                'headline' => $errors->get('headline'),
                'bio' => $errors->get('bio'),
                'experienceYears' => $errors->get('experience_years'),
                'education' => $errors->get('education'),
                'languages' => $errors->get('languages'),
                'hourlyRate' => $errors->get('hourly_rate'),
                'avatar' => $errors->get('avatar'),
            ],
            'steps' => $steps,
            'currentStep' => $currentStep,
            'draft' => [
                'restored' => $restored,
                'savedAt' => $restored ? $draft?->saved_at?->toIso8601String() : null,
                'savedAtLabel' => $restored ? display_datetime($draft?->saved_at) : null,
                'hasAny' => $draft !== null && ! $draft->isEmpty(),
                'step' => $stepKey,
            ],
            'urls' => [
                'update' => $updateUrl,
                'back' => match (true) {
                    $onboarding && $stepKey === 'about' => route('teacher.onboarding.show'),
                    $onboarding && $stepKey === 'preferences' => route('teacher.onboarding.profile'),
                    default => null,
                },
                'next' => match (true) {
                    $onboarding && $stepKey === 'about' => route('teacher.onboarding.preferences'),
                    $onboarding && $stepKey === 'preferences' => route('teacher.onboarding.availability'),
                    default => null,
                },
                'draft' => $onboarding ? route('teacher.onboarding.draft') : null,
                'draftDelete' => $onboarding ? route('teacher.onboarding.draft.destroy') : null,
                'hub' => route('teacher.onboarding.show'),
                'verification' => route('teacher.onboarding.verification'),
                'availability' => route('teacher.onboarding.availability'),
                'nextStep' => $hasAvailability
                    ? route('teacher.onboarding.verification')
                    : route('teacher.onboarding.availability'),
            ],
            'copy' => self::copy($mode),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function copy(string $mode): array
    {
        $onboarding = $mode === 'onboarding';

        return [
            'incomplete' => __('Finish your teaching profile to unlock weekly availability and continue to verification.'),
            'updated' => __('Your teaching profile has been updated.'),
            'aboutSavedTitle' => __('About you saved'),
            'aboutSavedBody' => __('Next: how you teach and what you charge. You can change any of this later.'),
            'preferencesSavedTitle' => __('Teaching preferences saved'),
            'preferencesSavedBody' => __('Almost there — set your weekly hours next.'),
            'preferencesMissingBody' => __('Save your teaching preferences to unlock the weekly availability step.'),
            'errorTitle' => __('Please fix the following:'),
            'stepsTitle' => __('Teacher setup'),
            'stepsCompleted' => __(':completed of :total steps completed'),
            'stepLabel' => __('Step :number'),
            'stepStatusDone' => __('Completed'),
            'stepStatusCurrent' => __('In progress'),
            'stepStatusUpNext' => __('Up next'),
            'stepStatusLocked' => __('Locked'),
            'stepCompleteTitle' => __('Step 1 complete — next: weekly availability'),
            'stepCompleteBody' => __('Set the days and hours you teach so students can book you. You can load a suggested week and change anything.'),
            'stepCompleteCta' => __('Continue to availability'),
            'professionalTitle' => __('Professional details'),
            'professionalHint' => __('What students read before they book you.'),
            'headlineLabel' => __('Headline'),
            'headlinePlaceholder' => __('e.g. Physics tutor for high school students'),
            'bioLabel' => __('About you'),
            'bioHint' => __('Optional, but a clear introduction builds trust.'),
            'bioPlaceholder' => __('Describe your teaching style, strengths, and the students you work best with.'),
            'experienceLabel' => __('Years of experience'),
            'educationLabel' => __('Education'),
            'educationPlaceholder' => __('e.g. M.Sc. Mathematics, IIT Delhi'),
            'preferencesTitle' => __('Teaching preferences'),
            'preferencesHint' => __('How, and at what rate, you take lessons.'),
            'languagesLabel' => __('Languages you teach in'),
            'languagesHint' => __('Pick every language you can run a lesson in.'),
            'rateLabel' => __('Hourly rate'),
            'rateHint' => __('Students see :amount per 60-minute lesson before commission.'),
            'rateHintEmpty' => __('Students see your hourly rate per 60-minute lesson before commission.'),
            'photoTitle' => __('Profile photo'),
            'photoHint' => __('A clear, friendly photo builds trust with students. JPG, PNG or WebP up to 5 MB.'),
            'photoChoose' => __('Upload photo'),
            'photoReplace' => __('Choose another'),
            'photoClear' => __('Clear'),
            'unsaved' => __('You have unsaved changes.'),
            'saved' => __('All changes saved.'),
            'saving' => __('Saving…'),
            'undo' => __('Undo changes'),
            'save' => __('Save changes'),
            'saveAndContinue' => __('Save profile & continue to availability'),
            'previewTitle' => __('Public card preview'),
            'previewLive' => __('Live'),
            'previewHint' => __('This is the card students see in the teacher directory.'),
            'previewHeadlineEmpty' => __('Your headline will appear here'),
            'previewRateEmpty' => __('Set your hourly rate'),
            'previewNote' => __('Your languages show here until a subject is assigned. Ratings and availability fill in once your profile is approved and students start booking.'),
            'newTeacher' => __('New teacher'),
            'verified' => __('Verified'),
            'nextLabel' => __('Next:'),
            'noSlots' => __('No slots in the next 7 days'),
            'perHour' => __('/hr'),
            'view' => __('View'),
            // Wizard additions.
            'back' => __('Back'),
            'continue' => __('Save & continue'),
            'finishLater' => __('Save & finish later'),
            'draftDirty' => __('Unsaved changes — keeping a draft…'),
            'draftSaving' => __('Saving draft…'),
            'draftSaved' => __('Draft saved just now'),
            'draftOffline' => __('Offline — your answers are kept on this device.'),
            'draftError' => __('Could not save the draft. Your answers are kept on this device.'),
            'draftRestoredTitle' => __('We restored your unsaved answers'),
            'draftRestoredBody' => __('Carry on from :time. Save the step to keep them. Your photo needs to be selected again.'),
            'draftRestoredBodyNoPhoto' => __('Carry on from :time. Save the step to keep them.'),
            'draftDiscard' => __('Start over'),
            'draftDiscardConfirm' => __('Discard the draft and restore your saved answers?'),
            'aboutStepTitle' => __('Tell students about you'),
            'aboutStepBody' => __('This is the first thing a student reads. Keep it specific and friendly.'),
            'preferencesStepTitle' => __('How you teach and what you charge'),
            'preferencesStepBody' => __('Students filter by language, and your rate is what they see before booking.'),
            'previewToggle' => __('Preview my public card'),
            'stepOf' => $onboarding ? __('Step :current of :total') : '',
        ];
    }
}
