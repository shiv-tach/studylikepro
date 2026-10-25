<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\RegistrationConflictException;
use App\Exceptions\SmsDeliveryException;
use App\Http\Controllers\Controller;
use App\Services\MobileRegistrationService;
use App\Support\PendingRegistration;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The second step of registration: the visitor proves the mobile number with
 * the code SMSLenz texted them. Nothing is written to `users` until the code
 * matches, so an abandoned registration leaves no account behind.
 */
class MobileVerificationController extends Controller
{
    public function __construct(private readonly MobileRegistrationService $registration) {}

    public function show(): View|RedirectResponse
    {
        $pending = PendingRegistration::get();

        if ($pending === null) {
            return redirect()->route('register');
        }

        $policy = $this->registration->policy();

        return view('auth.verify-mobile', [
            'mobile' => PhoneNumber::mask((string) $pending['mobile']),
            'length' => $policy['length'],
            // A send that failed left no cooldown, so the button works straight away.
            'cooldown' => $this->registration->cooldownRemaining(),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $policy = $this->registration->policy();

        $validated = $request->validate([
            'code' => ['required', 'digits:'.$policy['length']],
        ], [], [
            'code' => __('verification code'),
        ]);

        try {
            $user = $this->registration->verify((string) $validated['code']);
        } catch (RegistrationConflictException $exception) {
            return redirect()
                ->route('register')
                ->withErrors($exception->errors)
                ->withInput($exception->input);
        }

        event(new Registered($user));

        Auth::login($user);

        // A fresh session id after sign-in, as the login controller does.
        $request->session()->regenerate();

        return redirect(route('dashboard', absolute: false));
    }

    public function resend(): RedirectResponse
    {
        try {
            $status = $this->registration->resend();
        } catch (SmsDeliveryException) {
            return back()->with('status', 'otp-send-failed');
        }

        return match ($status) {
            'sent' => back()->with('status', 'otp-resent'),
            'missing' => redirect()->route('register'),
            'cooldown' => back()->withErrors([
                'code' => __('Please wait a moment before requesting another code.'),
            ]),
            'limit' => back()->withErrors([
                'code' => __('You have requested too many codes. Please register again.'),
            ]),
            default => back(),
        };
    }

    /**
     * Go back to the form, replaying what the visitor already typed.
     */
    public function cancel(): RedirectResponse
    {
        $pending = PendingRegistration::get();

        $this->registration->cancel();

        return redirect()
            ->route('register')
            ->withInput($pending === null ? [] : PendingRegistration::input($pending));
    }
}
