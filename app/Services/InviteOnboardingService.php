<?php

namespace App\Services;

use App\Enums\ServiceType;
use App\Enums\VerificationStatus;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Support\AvailabilityPresets;
use App\Support\TeacherSurvey;
use Illuminate\Support\Facades\Storage;

/**
 * Applies the answers of the guest invite survey to the account that was just
 * created from them.
 *
 * The survey runs before the `users` row exists, so everything it collects is
 * held in the session (see `PendingTeacherSignup`). Once the SMS passcode has
 * proved the mobile number, `MobileRegistrationService` calls this inside the
 * same transaction as the `User::create()`, which means a teacher either ends up
 * with the answers on their profile or no account at all.
 *
 * The survey is the whole onboarding: it collects the profile facts, the rate,
 * the service prices, the week and the proof upload, so the profile is marked as
 * saved and the application is sent for review here. When the account exists the
 * only thing left is the admin's verification decision.
 */
class InviteOnboardingService
{
    /** Where a guest's proof upload waits until the account exists. */
    public const PENDING_PROOF_DIRECTORY = 'verification-documents/pending';

    public function __construct(
        private readonly TeacherSubjectService $subjects,
        private readonly WeeklyAvailabilityService $weeks,
        private readonly VerificationAlertService $alerts,
    ) {}

    /**
     * @param  array{level?: mixed, subjects?: mixed, languages?: mixed, services?: mixed, experience?: mixed, education?: mixed, rates?: mixed, availability?: mixed, proof?: mixed}  $payload
     */
    public function apply(User $user, array $payload): void
    {
        $profile = $user->teacherProfile()->firstOrNew([]);

        $subjects = collect((array) ($payload['subjects'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $languages = array_values(array_filter(
            array_map('strval', (array) ($payload['languages'] ?? [])),
            fn (string $language) => $language !== '',
        ));

        $level = $payload['level']['value'] ?? null;
        $education = $payload['education']['value'] ?? null;
        $hourlyMinor = $this->toMinor($payload['rates']['hourly_rate'] ?? null);

        $profile->fill([
            // A placeholder the teacher replaces on /teacher/profile: `headline`
            // is NOT NULL, and the survey does not ask for it.
            'headline' => self::headlineFor($subjects->all()),
            'bio' => null,
            'experience_years' => (int) ($payload['experience']['value'] ?? 0),
            'education' => (string) (TeacherSurvey::educationLabel(is_string($education) ? $education : null) ?? ''),
            'languages' => $languages === []
                ? [(string) (config('studylikepro.languages')[0] ?? 'English')]
                : $languages,
            'tutoring_levels' => TeacherSurvey::allowedLevelKeys(is_string($level) ? $level : null),
            'service_interests' => array_values(array_map('strval', (array) ($payload['services'] ?? []))),
            'service_rates' => $this->serviceRates($payload['rates']['service_rates'] ?? []),
        ]);
        $profile->timezone = $profile->timezone ?: config('studylikepro.default_display_timezone');
        $profile->hourly_rate_minor = $hourlyMinor ?? $profile->hourly_rate_minor ?? 0;

        // The survey is the profile form now: it collects the story fields, the
        // languages and the rate, so the profile counts as saved the moment the
        // account exists.
        $profile->about_completed_at ??= now();
        $profile->preferences_completed_at ??= now();
        $profile->completed_at ??= now();

        // The wizard is the whole application, so it is sent for review here.
        // The only thing left after sign-up is the admin's decision.
        $profile->verification_status = VerificationStatus::Pending;
        $profile->submitted_at = now();
        $profile->verification_notes = null;
        $profile->agreement_accepted_at ??= now();
        $profile->agreement_version = (string) config('studylikepro.support.policy_version');

        $profile->save();

        $user->setRelation('teacherProfile', $profile);

        // Pending pivots with the level's full grade/lesson scope: the admin
        // approves them together with the application (or rejects them there).
        if ($subjects->isNotEmpty()) {
            $this->subjects->syncSubjects($profile, $subjects->all());
        }

        $this->applyAvailability(
            $profile,
            (array) ($payload['availability']['days'] ?? []),
            $payload['availability']['preset'] ?? null,
        );

        $this->storeGovernmentProof($profile, (array) ($payload['proof'] ?? []));

        // Tell the operations mobile there is a new application to review. The
        // alert is best effort and never blocks the account creation.
        $this->alerts->notifyNewSubmission($profile);
    }

    /**
     * The days the teacher picked become a real week, so the availability step
     * opens on hours a student can already book instead of an empty grid. The
     * times come from the suggested week the teacher chose in the survey, or from
     * the recommended preset when they ticked days themselves; the teacher edits
     * them next.
     *
     * @param  array<int, mixed>  $days
     */
    private function applyAvailability(TeacherProfile $profile, array $days, mixed $preset): void
    {
        $selected = array_values(array_unique(array_map('intval', $days)));
        $selected = array_values(array_filter($selected, fn (int $day) => $day >= 0 && $day <= 6));

        if ($selected === []) {
            return;
        }

        $defaultWeek = AvailabilityPresets::defaultWeek();
        $chosen = is_string($preset) ? AvailabilityPresets::find($preset) : null;
        $presetDays = $chosen['days'] ?? $defaultWeek;
        $week = [];

        foreach ($selected as $day) {
            $week[] = [
                'value' => $day,
                'enabled' => true,
                'ranges' => array_map(
                    fn (array $range) => ['start_time' => $range['start'], 'end_time' => $range['end']],
                    $presetDays[$day] ?? $defaultWeek[$day],
                ),
            ];
        }

        $this->weeks->replace($profile, $week);
    }

    /**
     * The price each picked service was given in the survey, in minor units.
     * Unknown service types are dropped so a renamed case cannot leak.
     *
     * @return array<string, int>|null
     */
    private function serviceRates(mixed $rates): ?array
    {
        if (! is_array($rates)) {
            return null;
        }

        $priced = [];

        foreach ($rates as $type => $amount) {
            if (! is_string($type) || ServiceType::tryFrom($type) === null) {
                continue;
            }

            $minor = $this->toMinor($amount);

            if ($minor !== null) {
                $priced[$type] = $minor;
            }
        }

        return $priced === [] ? null : $priced;
    }

    private function toMinor(mixed $amount): ?int
    {
        if (! is_numeric($amount)) {
            return null;
        }

        $minor = (int) round(((float) $amount) * 100);

        return $minor > 0 ? $minor : null;
    }

    /**
     * A guest's proof was uploaded before the user existed, so it was parked in
     * `verification-documents/pending`. Move it into the profile's own folder and
     * file it as the (optional) government-teacher proof document.
     */
    private function storeGovernmentProof(TeacherProfile $profile, array $proof): void
    {
        if (! (bool) ($proof['claimed'] ?? false)) {
            return;
        }

        $path = (string) ($proof['path'] ?? '');
        $disk = Storage::disk('local');

        if ($path === '' || ! $disk->exists($path)) {
            return;
        }

        if ($profile->documents()->where('type', 'government_proof')->exists()) {
            $disk->delete($path);

            return;
        }

        $target = 'verification-documents/'.$profile->id.'/'.basename($path);

        $disk->move($path, $target);

        $profile->documents()->create([
            'type' => 'government_proof',
            'original_name' => (string) ($proof['original_name'] ?? basename($path)),
            'path' => $target,
        ]);

        $profile->unsetRelation('documents');
    }

    /**
     * A readable placeholder headline from the subjects picked in the survey.
     *
     * @param  list<int>  $subjectIds
     */
    private static function headlineFor(array $subjectIds): string
    {
        $names = Subject::query()
            ->whereIn('id', $subjectIds)
            ->ordered()
            ->limit(2)
            ->pluck('name');

        if ($names->isEmpty()) {
            return (string) __('Teacher');
        }

        return $names->implode(' '.__('and').' ').' '.__('teacher');
    }
}
