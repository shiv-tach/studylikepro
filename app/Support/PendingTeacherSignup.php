<?php

namespace App\Support;

use App\Models\TeacherInvite;
use App\Models\TeacherOnboardingDraft;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The teacher onboarding wizard a visitor is filling in *before* they have an
 * account.
 *
 * An invite link opens the wizard as a guest: the answers live in the session
 * (nothing is written to `users`), and only the final "Create your account"
 * step hands them to `PendingRegistration`, which waits for the SMS passcode.
 * The session row mirrors the shape of `PendingRegistration` so the two steps
 * of the flow read the same way.
 *
 * Key: `invite.signup`.
 */
final class PendingTeacherSignup
{
    public const KEY = 'invite.signup';

    /**
     * The wizard steps that hold answers before the account exists.
     */
    public const ANSWER_STEPS = [
        TeacherOnboarding::STEP_ABOUT,
        TeacherOnboarding::STEP_PREFERENCES,
        TeacherOnboarding::STEP_AVAILABILITY,
    ];

    /**
     * Begin (or continue) the guest wizard for one invite token.
     */
    public static function start(string $token): void
    {
        $current = self::get();

        if ($current !== null && ($current['token'] ?? null) === $token) {
            return;
        }

        session([self::KEY => [
            'token' => $token,
            'answers' => [],
            'drafts' => [],
            'avatar_path' => null,
            'saved_at' => null,
        ]]);
    }

    /**
     * @return array{token?: string, answers?: array<string, mixed>, drafts?: array<string, mixed>, avatar_path?: string|null, saved_at?: string|null}|null
     */
    public static function get(): ?array
    {
        $data = session(self::KEY);

        return is_array($data) ? $data : null;
    }

    public static function token(): ?string
    {
        $token = self::get()['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * The invite the session is filling in, while it is still usable.
     *
     * A link can expire (or be consumed in another tab) halfway through the
     * wizard, so every step resolves it again instead of trusting the session.
     */
    public static function invite(): ?TeacherInvite
    {
        return TeacherInvite::findUsable(self::token());
    }

    public static function hasStarted(): bool
    {
        return self::get() !== null;
    }

    /**
     * The *submitted* answers, keyed by wizard step.
     *
     * @return array<string, mixed>
     */
    public static function answers(): array
    {
        $answers = self::get()['answers'] ?? [];

        return is_array($answers) ? $answers : [];
    }

    /**
     * The submitted answer of one step. Autosaved, half-typed input is kept
     * apart on purpose: only a step the guest actually saved can unlock the next
     * one, or become part of the account.
     *
     * @return array<string, mixed>
     */
    public static function step(string $step): array
    {
        $answers = self::answers();

        return is_array($answers[$step] ?? null) ? $answers[$step] : [];
    }

    public static function hasStep(string $step): bool
    {
        return self::step($step) !== [];
    }

    /**
     * Store a submitted step and drop the autosaved draft it supersedes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function putStep(string $step, array $data): void
    {
        $current = self::get();

        if ($current === null) {
            return;
        }

        $answers = is_array($current['answers'] ?? null) ? $current['answers'] : [];
        $drafts = is_array($current['drafts'] ?? null) ? $current['drafts'] : [];

        $answers[$step] = $data;
        unset($drafts[$step]);

        self::put(['answers' => $answers, 'drafts' => $drafts]);
    }

    /**
     * Store what is on screen right now, for the island to restore. Never used
     * as an answer.
     *
     * @param  array<string, mixed>  $data
     */
    public static function putDraft(string $step, array $data): void
    {
        $current = self::get();

        if ($current === null) {
            return;
        }

        $drafts = is_array($current['drafts'] ?? null) ? $current['drafts'] : [];
        $drafts[$step] = $data;

        self::put(['drafts' => $drafts]);
    }

    /**
     * The uploaded photo of a guest, kept outside the user tree until the
     * account exists (see `InviteOnboardingService::apply()`).
     */
    public static function avatarPath(): ?string
    {
        $path = self::get()['avatar_path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    public static function putAvatar(?string $path): void
    {
        self::put(['avatar_path' => $path]);
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * Everything the wizard has collected, in the shape the account creation
     * replays after the passcode is checked.
     *
     * @return array{about: array<string, mixed>, preferences: array<string, mixed>, availability: array<string, mixed>, avatar_path: string|null}|null
     */
    public static function onboardingPayload(): ?array
    {
        if (! self::isComplete()) {
            return null;
        }

        return [
            'about' => self::step(TeacherOnboarding::STEP_ABOUT),
            'preferences' => self::step(TeacherOnboarding::STEP_PREFERENCES),
            'availability' => self::step(TeacherOnboarding::STEP_AVAILABILITY),
            'avatar_path' => self::avatarPath(),
        ];
    }

    /**
     * True once every answer step has been saved.
     */
    public static function isComplete(): bool
    {
        foreach (self::ANSWER_STEPS as $step) {
            if (! self::hasStep($step)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The first step still missing an answer, as a route name.
     */
    public static function nextRouteName(): string
    {
        foreach (self::ANSWER_STEPS as $step) {
            if (self::hasStep($step)) {
                continue;
            }

            return match ($step) {
                TeacherOnboarding::STEP_ABOUT => 'join.profile',
                TeacherOnboarding::STEP_PREFERENCES => 'join.preferences',
                default => 'join.availability',
            };
        }

        return 'join.account';
    }

    /**
     * The saved answers of a step as a transient draft model, so the existing
     * island builders (and their "restored draft" banners) work for a guest too.
     * Autosaved typing wins over the stored answer, which is what the visitor
     * last saw on screen.
     */
    public static function draft(): ?TeacherOnboardingDraft
    {
        $current = self::get();
        $answers = self::answers();
        $drafts = $current['drafts'] ?? [];
        $payload = array_merge($answers, is_array($drafts) ? $drafts : []);

        if ($payload === []) {
            return null;
        }

        return new TeacherOnboardingDraft([
            'payload' => $payload,
            'saved_at' => self::savedAt(),
        ]);
    }

    public static function savedAt(): ?CarbonInterface
    {
        $saved = self::get()['saved_at'] ?? null;

        return is_string($saved) && $saved !== '' ? Carbon::parse($saved) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function put(array $data): void
    {
        $current = self::get();

        if ($current === null) {
            return;
        }

        session([self::KEY => array_merge($current, $data, ['saved_at' => now()->toIso8601String()])]);
    }
}
