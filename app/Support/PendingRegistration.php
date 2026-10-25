<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * The registration waiting for its SMS passcode.
 *
 * A visitor's details are held in the session between submitting the register
 * form and entering the code, so no `users` row exists until the number is
 * proved. Only the password and the passcode are stored, and both are hashed.
 *
 * Key: `register.pending`.
 */
final class PendingRegistration
{
    public const KEY = 'register.pending';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function put(array $data): void
    {
        session([self::KEY => $data]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $data = session(self::KEY);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function merge(array $data): void
    {
        $pending = self::get();

        if ($pending === null) {
            return;
        }

        self::put(array_merge($pending, $data));
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * The registration values the form can safely replay, never the password
     * or the passcode.
     *
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>
     */
    public static function input(array $pending): array
    {
        $input = Arr::only($pending, ['first_name', 'last_name', 'email', 'role', 'invite']);

        if (isset($pending['mobile'])) {
            $input['mobile'] = PhoneNumber::format((string) $pending['mobile']);
        }

        return $input;
    }
}
