<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\RegistrationConflictException;
use App\Exceptions\SmsDeliveryException;
use App\Http\Controllers\Controller;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Rules\SriLankanMobile;
use App\Services\MobileRegistrationService;
use App\Support\PendingRegistration;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        // Opening the form always starts a fresh attempt: any details waiting
        // for a passcode are superseded by what is typed here.
        PendingRegistration::forget();

        $invite = null;
        $inviteToken = null;
        $inviteError = null;

        // The invite may arrive in the URL, or be replayed by the "change your
        // number" button on the verify screen (which returns with old input).
        $token = $request->query('invite') ?? $request->old('invite');

        if (! blank($token)) {
            $inviteToken = (string) $token;
            $invite = TeacherInvite::findUsable($inviteToken);

            if ($invite === null) {
                $inviteError = 'This teacher onboarding link is invalid, expired, or already used.';
            }
        }

        return view('auth.register', [
            'invite' => $invite,
            'inviteToken' => $inviteToken,
            'inviteError' => $inviteError,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * The account is not created here: the details are held in the session and
     * SMSLenz texts a passcode. MobileVerificationController creates the user
     * once that code is entered (or straight away when an admin has switched
     * verification off).
     *
     * @throws ValidationException
     */
    public function store(Request $request, MobileRegistrationService $registration): RedirectResponse
    {
        // Compare and store the number in its canonical local form so
        // "076 982 88 74" and "+94 76 982 8874" are the same account.
        $request->merge(['mobile' => PhoneNumber::normalize($request->input('mobile'))]);

        $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'mobile' => ['required', 'string', new SriLankanMobile, Rule::unique(User::class, 'mobile')],
            'role' => ['nullable', 'string', Rule::in([User::ROLE_STUDENT, User::ROLE_TEACHER])],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [], [
            'mobile' => __('mobile number'),
        ]);

        // Public registration always creates a student; only an invite may grant the teacher role.
        $role = $request->string('role')->toString() ?: User::ROLE_STUDENT;
        $inviteToken = $request->input('invite');
        $inviteToken = is_string($inviteToken) ? $inviteToken : null;

        if ($role === User::ROLE_TEACHER && TeacherInvite::findUsable($inviteToken) === null) {
            throw ValidationException::withMessages([
                'invite' => 'Teacher accounts can only be created through a valid onboarding link.',
            ]);
        }

        try {
            $user = $registration->begin([
                'first_name' => $request->string('first_name')->toString(),
                'last_name' => $request->string('last_name')->toString(),
                'email' => (string) $request->input('email'),
                'mobile' => (string) $request->input('mobile'),
                'password' => (string) $request->input('password'),
                'role' => $role,
                'invite' => $inviteToken,
            ]);
        } catch (RegistrationConflictException $exception) {
            return redirect()
                ->route('register')
                ->withErrors($exception->errors)
                ->withInput($exception->input);
        } catch (SmsDeliveryException) {
            // The pending registration survives, so the verify screen can offer
            // a resend instead of making the visitor type everything again.
            return redirect()
                ->route('register.verify.show')
                ->with('status', 'otp-send-failed');
        }

        if ($user === null) {
            return redirect()
                ->route('register.verify.show')
                ->with('status', 'otp-sent');
        }

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect(route('dashboard', absolute: false));
    }
}
