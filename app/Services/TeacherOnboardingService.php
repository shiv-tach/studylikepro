<?php

namespace App\Services;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes the workspace teaching profile, which is the single form that owns the
 * headline, bio, education, languages and hourly rate.
 */
class TeacherOnboardingService
{
    /**
     * The profile page submits every field at once, so saving it marks both
     * halves of the profile — and the profile itself — as complete.
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
}
