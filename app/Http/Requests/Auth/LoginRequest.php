<?php

namespace App\Http\Requests\Auth;

use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim the identifier so a pasted number or address still matches.
     */
    protected function prepareForValidation(): void
    {
        $login = $this->input('login');

        if (is_string($login)) {
            $this->merge(['login' => trim($login)]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `login` is one field holding either an email address or a Sri Lankan
     * mobile number; its shape decides which column is checked.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->credentials();

        if ($credentials === null) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        if (! Auth::attempt($credentials + ['password' => $this->input('password')], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // A suspended account cannot sign in at all — even with the right password.
        if (Auth::user()?->isSuspended()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'login' => __('This account is suspended. Contact support if you believe this is a mistake.'),
            ]);
        }
    }

    /**
     * The column/value pair the identifier points at, or null when it is
     * neither an email address nor a Sri Lankan mobile number.
     *
     * @return array{email: string}|array{mobile: string}|null
     */
    private function credentials(): ?array
    {
        $login = trim((string) $this->input('login'));

        if ($login === '') {
            return null;
        }

        if (str_contains($login, '@')) {
            return ['email' => Str::lower($login)];
        }

        $mobile = PhoneNumber::normalize($login);

        return $mobile === null ? null : ['mobile' => $mobile];
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Keyed on the canonical identifier so "076 982 88 74" and
     * "+94 76 982 8874" share one bucket.
     */
    public function throttleKey(): string
    {
        $credentials = $this->credentials();

        $identifier = $credentials === null
            ? Str::lower((string) $this->input('login'))
            : (string) reset($credentials);

        return Str::transliterate($identifier.'|'.$this->ip());
    }
}
