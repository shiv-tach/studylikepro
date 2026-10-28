<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Applies the answers of the guest invite wizard to the account that was just
 * created from them.
 *
 * The wizard runs before the `users` row exists, so everything it collects is
 * held in the session (see `PendingTeacherSignup`). Once the SMS passcode has
 * proved the mobile number, `MobileRegistrationService` calls this inside the
 * same transaction as the `User::create()`, which means a teacher either ends up
 * with a complete profile or no account at all.
 */
class InviteOnboardingService
{
    /** Where a guest's photo waits until the account exists. */
    public const PENDING_AVATAR_DIRECTORY = 'avatars/pending';

    public function __construct(private readonly TeacherOnboardingService $onboarding) {}

    /**
     * @param  array{about?: array<string, mixed>, preferences?: array<string, mixed>, availability?: array<string, mixed>, avatar_path?: string|null}  $payload
     */
    public function apply(User $user, array $payload): void
    {
        $about = $payload['about'] ?? [];

        if ($about !== []) {
            $this->onboarding->saveAbout($user, [
                'headline' => (string) ($about['headline'] ?? ''),
                'bio' => $about['bio'] ?? null,
                'experience_years' => (int) ($about['experience_years'] ?? 0),
                'education' => (string) ($about['education'] ?? ''),
            ]);
        }

        $preferences = $payload['preferences'] ?? [];

        if ($preferences !== []) {
            $this->onboarding->savePreferences($user, [
                'languages' => array_values((array) ($preferences['languages'] ?? [])),
                'hourly_rate' => $preferences['hourly_rate'] ?? 0,
            ]);
        }

        $profile = $user->teacherProfile;

        if ($profile === null) {
            return;
        }

        $days = $payload['availability']['days'] ?? [];

        if (is_array($days) && $days !== []) {
            $this->onboarding->saveAvailability($profile, $days);
        }

        $this->promoteAvatar($user, $payload['avatar_path'] ?? null);
    }

    /**
     * A guest's photo was uploaded before the user existed, so it was parked in
     * `avatars/pending`. Move it into the user's tree and point the account at it.
     */
    private function promoteAvatar(User $user, ?string $pendingPath): void
    {
        if (blank($pendingPath)) {
            return;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($pendingPath)) {
            return;
        }

        $extension = pathinfo($pendingPath, PATHINFO_EXTENSION);
        $target = 'avatars/'.basename($pendingPath);

        if ($extension !== '') {
            $target = 'avatars/'.pathinfo($pendingPath, PATHINFO_FILENAME).'.'.$extension;
        }

        $disk->move($pendingPath, $target);

        $user->avatar_path = $target;
        $user->save();
    }
}
