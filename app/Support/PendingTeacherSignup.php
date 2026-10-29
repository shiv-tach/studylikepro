<?php

namespace App\Support;

use App\Models\TeacherInvite;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The teacher invite survey a visitor fills in *before* they have an account.
 *
 * An invite link opens the survey as a guest: the answers live in the session
 * (nothing is written to `users`), and only the final "Create your account"
 * step hands them to `PendingRegistration`, which waits for the SMS passcode.
 *
 * Key: `invite.signup`.
 */
final class PendingTeacherSignup
{
    public const KEY = 'invite.signup';

    /** The questions of the survey, in order (see `TeacherSurvey`). */
    public const ANSWER_STEPS = TeacherSurvey::ANSWER_STEPS;

    /**
     * Begin (or continue) the guest survey for one invite token.
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
            'saved_at' => null,
        ]]);
    }

    /**
     * @return array{token?: string, answers?: array<string, mixed>, saved_at?: string|null}|null
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
     * The answers already given, keyed by question.
     *
     * @return array<string, mixed>
     */
    public static function answers(): array
    {
        $answers = self::get()['answers'] ?? [];

        return is_array($answers) ? $answers : [];
    }

    /**
     * One answer. Every next question is gated on this: a question that was
     * never saved cannot unlock the next one, or become part of the account.
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
     * Store a submitted answer.
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
        $answers[$step] = $data;

        self::put(['answers' => $answers]);
    }

    /**
     * Forget one answer — the subject picks when the level changed under them,
     * or the proof when a guest removes it.
     */
    public static function forgetStep(string $step): void
    {
        $current = self::get();

        if ($current === null) {
            return;
        }

        $answers = is_array($current['answers'] ?? null) ? $current['answers'] : [];
        unset($answers[$step]);

        self::put(['answers' => $answers]);
    }

    /**
     * The proof file a guest uploaded, parked outside the user tree until the
     * account exists (see `InviteOnboardingService::apply()`).
     */
    public static function proofPath(): ?string
    {
        $path = self::step(TeacherSurvey::STEP_PROOF)['path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * Everything the survey collected, in the shape the account creation
     * replays after the passcode is checked.
     *
     * @return array<string, mixed>|null
     */
    public static function onboardingPayload(): ?array
    {
        if (! self::isComplete()) {
            return null;
        }

        $payload = [];

        foreach (self::ANSWER_STEPS as $step) {
            $payload[$step] = self::step($step);
        }

        return $payload;
    }

    /**
     * True once every question has an answer.
     */
    public static function isComplete(): bool
    {
        return TeacherSurvey::isComplete(self::answers());
    }

    /**
     * The first question still missing an answer, as a route name.
     */
    public static function nextRouteName(): string
    {
        return TeacherSurvey::nextRouteName(self::answers());
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
