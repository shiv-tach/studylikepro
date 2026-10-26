<?php

namespace App\Services;

use App\Models\TeacherOnboardingDraft;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes the answers of the onboarding wizard. Each step saves only its own
 * columns, so a teacher can finish "About you" and come back to "Teaching
 * preferences" later without the second step's fields being wiped.
 */
class TeacherOnboardingService
{
    public function __construct(private readonly WeeklyAvailabilityService $weeks) {}

    /**
     * Save the "About you" step.
     *
     * @param  array{headline: string, bio?: ?string, experience_years: int|string, education: string}  $attributes
     */
    public function saveAbout(User $user, array $attributes): TeacherProfile
    {
        return DB::transaction(function () use ($user, $attributes) {
            $profile = $user->teacherProfile()->firstOrNew([]);

            $profile->fill([
                'headline' => $attributes['headline'],
                'bio' => $attributes['bio'] ?? null,
                'experience_years' => (int) $attributes['experience_years'],
                'education' => $attributes['education'],
            ]);
            $profile->timezone = $profile->timezone ?: config('studylikepro.default_display_timezone');
            // The preferences step fills these in. They are NOT NULL columns, so
            // the row created here needs a safe starting value.
            $profile->languages = $profile->languages ?: [(string) (config('studylikepro.languages')[0] ?? 'English')];
            $profile->hourly_rate_minor = $profile->hourly_rate_minor ?? 0;
            $profile->about_completed_at ??= now();

            $this->completeIfReady($profile);
            $profile->save();

            $user->setRelation('teacherProfile', $profile);

            return $profile;
        });
    }

    /**
     * Save the "Teaching preferences" step (the avatar is handled by the
     * controller through `StoresAvatar`, which owns the file handling).
     *
     * @param  array{languages: array<int, string>, hourly_rate: int|float|string}  $attributes
     */
    public function savePreferences(User $user, array $attributes): TeacherProfile
    {
        return DB::transaction(function () use ($user, $attributes) {
            $profile = $user->teacherProfile()->firstOrNew([]);

            $profile->fill([
                'languages' => array_values($attributes['languages']),
                'hourly_rate_minor' => (int) round(((float) $attributes['hourly_rate']) * 100),
            ]);
            $profile->timezone = $profile->timezone ?: config('studylikepro.default_display_timezone');
            $profile->preferences_completed_at ??= now();

            $this->completeIfReady($profile);
            $profile->save();

            $user->setRelation('teacherProfile', $profile);

            return $profile;
        });
    }

    /**
     * The workspace profile page submits every field at once, so both steps are
     * done by definition.
     *
     * @param  array{headline: string, bio?: ?string, experience_years: int|string, education: string, languages: array<int, string>, hourly_rate: int|float|string}  $attributes
     */
    public function saveWholeProfile(User $user, array $attributes): TeacherProfile
    {
        return DB::transaction(function () use ($user, $attributes) {
            $profile = $user->teacherProfile()->firstOrNew([]);

            $profile->fill([
                'headline' => $attributes['headline'],
                'bio' => $attributes['bio'] ?? null,
                'experience_years' => (int) $attributes['experience_years'],
                'education' => $attributes['education'],
                'languages' => array_values($attributes['languages']),
            ]);
            $profile->timezone = $profile->timezone ?: config('studylikepro.default_display_timezone');
            $profile->hourly_rate_minor = (int) round(((float) $attributes['hourly_rate']) * 100);
            $profile->about_completed_at ??= now();
            $profile->preferences_completed_at ??= now();
            $profile->completed_at ??= now();
            $profile->save();

            $user->setRelation('teacherProfile', $profile);

            return $profile;
        });
    }

    /**
     * Replace the weekly ranges of the availability step.
     *
     * @param  array<int, array<string, mixed>>  $days
     */
    public function saveAvailability(TeacherProfile $profile, array $days): int
    {
        return $this->weeks->replace($profile, $days);
    }

    public function draftFor(User $user): ?TeacherOnboardingDraft
    {
        return $user->onboardingDraft()->first();
    }

    /**
     * Merge one step's answers into the teacher's draft. Autosave sends the
     * whole step every time, so a retry after a dropped connection restores the
     * exact state the editor held.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeDraft(User $user, string $step, array $data): TeacherOnboardingDraft
    {
        $draft = $user->onboardingDraft()->firstOrNew([]);
        $payload = is_array($draft->payload) ? $draft->payload : [];

        $payload[$step] = $data;

        $draft->fill([
            'payload' => $payload,
            'current_step' => $step,
            'saved_at' => now(),
        ]);
        $draft->save();

        $user->setRelation('onboardingDraft', $draft);

        return $draft;
    }

    /**
     * Drop one step's draft, or every step when `$step` is null.
     */
    public function clearDraft(User $user, ?string $step = null): void
    {
        $draft = $user->onboardingDraft()->first();

        if ($draft === null) {
            return;
        }

        if ($step === null) {
            $draft->delete();
            $user->unsetRelation('onboardingDraft');

            return;
        }

        $payload = is_array($draft->payload) ? $draft->payload : [];
        unset($payload[$step]);

        $draft->payload = $payload;

        if ($draft->isEmpty()) {
            $draft->delete();
            $user->unsetRelation('onboardingDraft');

            return;
        }

        $draft->save();
        $user->setRelation('onboardingDraft', $draft);
    }

    /**
     * `completed_at` still means "the profile data is saved": set it as soon as
     * both profile steps are done.
     */
    private function completeIfReady(TeacherProfile $profile): void
    {
        if ($profile->about_completed_at !== null && $profile->preferences_completed_at !== null) {
            $profile->completed_at ??= now();
        }
    }
}
