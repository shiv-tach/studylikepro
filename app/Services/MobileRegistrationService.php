<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Exceptions\RegistrationConflictException;
use App\Exceptions\SmsDeliveryException;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Support\PendingRegistration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Two-step registration backed by an SMS passcode.
 *
 * The form's details live in the session until the code is entered; only then
 * is the `users` row created, the role assigned, the teacher invite consumed
 * and the mobile stamped as verified. When an admin turns the passcode off the
 * account is created in one step, exactly as the app behaved before.
 */
class MobileRegistrationService
{
    public function __construct(private readonly SmsGateway $sms) {}

    /**
     * The live policy (admin settings win over the config defaults).
     *
     * @return array{enabled: bool, length: int, ttl_minutes: int, resend_seconds: int, max_attempts: int, max_resends: int}
     */
    public function policy(): array
    {
        $settings = platform_settings();

        return [
            'enabled' => $settings->string('sms_otp_enabled') === '1',
            'length' => max(4, min(8, $settings->int('sms_otp_length'))),
            'ttl_minutes' => max(1, $settings->int('sms_otp_ttl_minutes')),
            'resend_seconds' => max(0, $settings->int('sms_otp_resend_seconds')),
            'max_attempts' => max(1, $settings->int('sms_otp_max_attempts')),
            'max_resends' => max(0, $settings->int('sms_otp_max_resends')),
        ];
    }

    public function enabled(): bool
    {
        return $this->policy()['enabled'];
    }

    /**
     * Start a registration.
     *
     * Returns the created user when the passcode step is switched off,
     * otherwise null after texting the first code. The pending registration is
     * kept even when the provider rejects the message, so the visitor can retry
     * from the verify screen instead of losing the form.
     *
     * @param  array{first_name: string, last_name: string, email: string, mobile: string, password: string, role?: string, invite?: string|null}  $data
     *
     * @throws SmsDeliveryException
     */
    public function begin(array $data): ?User
    {
        if (! $this->enabled()) {
            return $this->createAccount($data);
        }

        $policy = $this->policy();
        $code = $this->newCode($policy['length']);

        PendingRegistration::put([
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'email' => mb_strtolower(trim($data['email'])),
            'mobile' => $data['mobile'],
            'role' => $data['role'] ?? User::ROLE_STUDENT,
            'invite' => $data['invite'] ?? null,
            'password' => Hash::make($data['password']),
            'attempts' => 0,
            'resends' => 0,
        ]);

        $this->sms->send($data['mobile'], $this->message($code));

        // Store the code only once the provider accepted it: a failed send
        // leaves no usable code, and the resend button stays available.
        PendingRegistration::merge([
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes($policy['ttl_minutes'])->getTimestamp(),
            'last_sent_at' => now()->getTimestamp(),
        ]);

        return null;
    }

    /**
     * Text a fresh code for the pending registration.
     *
     * @return string "sent", "missing", "cooldown" or "limit"
     *
     * @throws SmsDeliveryException
     */
    public function resend(): string
    {
        $pending = PendingRegistration::get();

        if ($pending === null) {
            return 'missing';
        }

        $policy = $this->policy();

        if ((int) ($pending['resends'] ?? 0) >= $policy['max_resends']) {
            return 'limit';
        }

        if ($this->cooldownRemaining() > 0) {
            return 'cooldown';
        }

        $code = $this->newCode($policy['length']);

        $this->sms->send((string) $pending['mobile'], $this->message($code));

        PendingRegistration::merge([
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes($policy['ttl_minutes'])->getTimestamp(),
            'last_sent_at' => now()->getTimestamp(),
            'resends' => (int) ($pending['resends'] ?? 0) + 1,
        ]);

        return 'sent';
    }

    /**
     * Seconds the visitor still has to wait before another code may be sent.
     */
    public function cooldownRemaining(): int
    {
        $pending = PendingRegistration::get();
        $lastSentAt = $pending['last_sent_at'] ?? null;

        if ($lastSentAt === null) {
            return 0;
        }

        return max(0, (int) $lastSentAt + $this->policy()['resend_seconds'] - now()->getTimestamp());
    }

    /**
     * Check the entered passcode and create the account.
     *
     * @throws ValidationException the code is wrong, expired or out of attempts
     * @throws RegistrationConflictException the email, mobile or invite was taken meanwhile
     */
    public function verify(string $code): User
    {
        $pending = PendingRegistration::get();

        if ($pending === null) {
            throw ValidationException::withMessages([
                'code' => __('Start your registration again to receive a new code.'),
            ]);
        }

        $policy = $this->policy();
        $attempts = (int) ($pending['attempts'] ?? 0);

        if ($attempts >= $policy['max_attempts']) {
            PendingRegistration::forget();

            throw ValidationException::withMessages([
                'code' => __('Too many incorrect attempts. Please register again.'),
            ]);
        }

        if ((int) ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            throw ValidationException::withMessages([
                'code' => __('This code has expired. Send a new code.'),
            ]);
        }

        $storedCode = (string) ($pending['code'] ?? '');

        if ($storedCode === '' || ! Hash::check($code, $storedCode)) {
            $attempts++;
            PendingRegistration::merge(['attempts' => $attempts]);

            if ($attempts >= $policy['max_attempts']) {
                PendingRegistration::forget();

                throw ValidationException::withMessages([
                    'code' => __('Too many incorrect attempts. Please register again.'),
                ]);
            }

            throw ValidationException::withMessages([
                'code' => __('The code you entered is incorrect.'),
            ]);
        }

        try {
            $user = $this->createAccount($pending);
        } catch (RegistrationConflictException $exception) {
            // The pending details can never be completed now (the email, mobile
            // or invite went to someone else), so drop them rather than letting
            // the visitor retry a code that is already known to fail.
            PendingRegistration::forget();

            throw $exception;
        }

        PendingRegistration::forget();

        return $user;
    }

    /**
     * Abandon the pending registration (the visitor wants to change a detail).
     */
    public function cancel(): void
    {
        PendingRegistration::forget();
    }

    /**
     * Create the account from a finished (or passcode-free) registration.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RegistrationConflictException
     */
    private function createAccount(array $data): User
    {
        $role = in_array($data['role'] ?? null, [User::ROLE_STUDENT, User::ROLE_TEACHER], true)
            ? $data['role']
            : User::ROLE_STUDENT;

        $input = PendingRegistration::input($data);

        try {
            return DB::transaction(function () use ($data, $role, $input) {
                if (User::query()->where('email', $data['email'])->exists()) {
                    throw new RegistrationConflictException(
                        ['email' => __('This email is already registered.')],
                        $input,
                    );
                }

                if (User::query()->where('mobile', $data['mobile'])->exists()) {
                    throw new RegistrationConflictException(
                        ['mobile' => __('This mobile number is already registered.')],
                        $input,
                    );
                }

                $invite = null;

                if ($role === User::ROLE_TEACHER) {
                    $invite = TeacherInvite::findUsable($data['invite'] ?? null);

                    if ($invite === null) {
                        throw new RegistrationConflictException(
                            ['invite' => __('This teacher onboarding link is invalid, expired, or already used.')],
                            $input,
                        );
                    }
                }

                $user = User::create([
                    'first_name' => trim($data['first_name']),
                    'last_name' => trim($data['last_name']),
                    'email' => $data['email'],
                    'mobile' => $data['mobile'],
                    // The passcode was entered (or verification is disabled), so
                    // the number is proved by the time the row exists.
                    'mobile_verified_at' => now(),
                    'password' => $data['password'],
                ]);

                $user->assignRole($role);

                if ($invite !== null) {
                    $invite->forceFill(['consumed_at' => now()])->save();
                }

                return $user;
            });
        } catch (QueryException $exception) {
            $errors = [];

            if (User::query()->where('email', $data['email'])->exists()) {
                $errors['email'] = __('This email is already registered.');
            }

            if (User::query()->where('mobile', $data['mobile'])->exists()) {
                $errors['mobile'] = __('This mobile number is already registered.');
            }

            if ($errors === []) {
                throw $exception;
            }

            throw new RegistrationConflictException($errors, $input);
        }
    }

    /**
     * A zero-padded numeric passcode of the configured length.
     */
    private function newCode(int $length): string
    {
        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function message(string $code): string
    {
        return str_replace(
            [':app', ':code', ':minutes'],
            [(string) config('app.name'), $code, (string) $this->policy()['ttl_minutes']],
            (string) config('studylikepro.sms.otp_message'),
        );
    }
}
