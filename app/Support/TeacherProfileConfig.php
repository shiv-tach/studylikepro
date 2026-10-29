<?php

namespace App\Support;

use App\Enums\VerificationStatus;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;

/**
 * The JSON island behind `/teacher/profile`.
 *
 * One builder keeps the field limits, the language catalog, the live public-card
 * preview and the validation messages identical to what `TeacherProfileRequest`
 * accepts.
 */
final class TeacherProfileConfig
{
    /**
     * @param  Collection<int, mixed>|null  $subjects
     * @return array<string, mixed>
     */
    public static function build(
        ?User $user,
        ?TeacherProfile $profile,
        MessageBag $errors,
        string $themePreset = 'classic',
        ?Collection $subjects = null,
    ): array {
        $profileComplete = (bool) $profile?->isComplete();
        $verificationSubmitted = (bool) $profile?->hasSubmittedVerification();
        $verificationStatus = $profile?->verification_status;
        $ratingCount = (int) ($profile?->rating_count ?? 0);
        $hasAvailability = $profile !== null && TeacherOnboarding::hasAvailability($profile);

        $subjects ??= $profile?->subjects ?? collect();
        $languages = config('studylikepro.languages');

        $values = [
            'headline' => old('headline', $profile?->headline) ?? '',
            'bio' => old('bio', $profile?->bio) ?? '',
            'experienceYears' => old('experience_years', $profile?->experience_years) ?? '',
            'education' => old('education', $profile?->education) ?? '',
            'languages' => array_values(old('languages', $profile?->languages ?? ['English'])),
            'hourlyRate' => old('hourly_rate', ($profile && (int) $profile->hourly_rate_minor > 0 ? (int) $profile->hourlyRate() : '')) ?? '',
        ];

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'csrfToken' => csrf_token(),
            'theme' => ['preset' => $themePreset],
            'status' => [
                'updated' => session('status') === 'profile-updated',
                'incomplete' => session('status') === 'complete-your-profile',
            ],
            'teacher' => [
                'name' => $user?->name ?? '',
                'firstName' => $user?->displayName() ?? '',
                'initials' => $user !== null ? (string) Str::of($user->name)->substr(0, 2) : '',
                'avatarUrl' => $user?->avatarUrl(),
            ],
            'profile' => [
                'isComplete' => $profileComplete,
                'hasSubmittedVerification' => $verificationSubmitted,
                'hasAvailability' => $hasAvailability,
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
            'steps' => TeacherOnboarding::steps($profile),
            'urls' => [
                'update' => route('teacher.profile.update'),
                'profile' => route('teacher.profile'),
                'verification' => route('teacher.verification'),
                'availability' => route('teacher.availability.index'),
                'nextStep' => $hasAvailability
                    ? route('teacher.verification')
                    : route('teacher.availability.index'),
            ],
            'copy' => self::copy(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function copy(): array
    {
        return [
            'incomplete' => __('Finish your teaching profile so students know who you are.'),
            'updated' => __('Your teaching profile has been updated.'),
            'errorTitle' => __('Please fix the following:'),
            'stepCompleteTitle' => __('Next: weekly availability'),
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
        ];
    }
}
