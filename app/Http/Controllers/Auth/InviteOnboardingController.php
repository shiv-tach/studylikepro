<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\RegistrationConflictException;
use App\Exceptions\SmsDeliveryException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SriLankanMobile;
use App\Services\MobileRegistrationService;
use App\Support\PendingRegistration;
use App\Support\PendingTeacherSignup;
use App\Support\PhoneNumber;
use App\Support\TeacherSurvey;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * The last step of the invite survey: the account itself.
 *
 * The nine survey questions run as a guest (see `TeacherSurveyController`) and
 * their answers sit in the session. This step asks for the identity, the email,
 * the mobile number and a password, texts an SMS passcode, and only when that
 * code matches is the `users` row created — with the survey answers applied to
 * its profile by `InviteOnboardingService`.
 */
class InviteOnboardingController extends Controller
{
    public function __construct(private readonly MobileRegistrationService $registration) {}

    /**
     * Step 6: identity + password, or the SMS code when one was already sent.
     */
    public function editAccount(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::isComplete()) {
            return redirect()
                ->route(PendingTeacherSignup::nextRouteName())
                ->with('status', 'complete-your-survey');
        }

        $pending = PendingRegistration::get();
        $policy = $this->registration->policy();

        $answers = PendingTeacherSignup::answers();

        return view('join.account', [
            'pending' => $pending,
            'mobile' => $pending !== null ? PhoneNumber::mask((string) $pending['mobile']) : null,
            'length' => $policy['length'],
            'cooldown' => $this->registration->cooldownRemaining(),
            'otpEnabled' => $policy['enabled'],
            'steps' => TeacherSurvey::steps($answers),
            'summary' => TeacherSurvey::summary($answers),
            'answered' => TeacherSurvey::answeredCount($answers),
            'questions' => count(TeacherSurvey::ANSWER_STEPS),
            'guest' => true,
            'currentStep' => TeacherSurvey::GROUP_ACCOUNT,
        ]);
    }

    /**
     * Save the account details and text the passcode.
     */
    public function storeAccount(Request $request): RedirectResponse
    {
        if (! PendingTeacherSignup::isComplete()) {
            return redirect()->route(PendingTeacherSignup::nextRouteName());
        }

        // Compare and store the number in its canonical local form so
        // "076 982 88 74" and "+94 76 982 8874" are the same account.
        $request->merge(['mobile' => PhoneNumber::normalize($request->input('mobile'))]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'mobile' => ['required', 'string', new SriLankanMobile, Rule::unique(User::class, 'mobile')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // The account cannot be registered before the terms are accepted.
            'agree' => ['accepted'],
        ], [
            'agree.accepted' => __('Please accept the terms and policies before creating your account.'),
        ], [
            'mobile' => __('mobile number'),
        ]);

        try {
            $user = $this->registration->begin([
                'first_name' => (string) $validated['first_name'],
                'last_name' => (string) $validated['last_name'],
                'email' => (string) $validated['email'],
                'mobile' => (string) $validated['mobile'],
                'password' => (string) $request->input('password'),
                'role' => User::ROLE_TEACHER,
                'invite' => PendingTeacherSignup::token(),
                'onboarding' => PendingTeacherSignup::onboardingPayload(),
            ]);
        } catch (RegistrationConflictException $exception) {
            return redirect()
                ->route('join.account')
                ->withErrors($exception->errors)
                ->withInput($exception->input);
        } catch (SmsDeliveryException) {
            // The pending registration survives, so the step can offer a resend
            // instead of making the guest type the details again.
            return redirect()
                ->route('join.account')
                ->with('status', 'otp-send-failed');
        }

        if ($user === null) {
            return redirect()
                ->route('join.account')
                ->with('status', 'otp-sent');
        }

        // Verification is switched off: the account is already complete.
        return $this->complete($request, $user);
    }

    /**
     * Check the passcode; the account is created here.
     */
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
                ->route('join.account')
                ->withErrors($exception->errors)
                ->withInput($exception->input);
        }

        return $this->complete($request, $user);
    }

    public function resend(): RedirectResponse
    {
        try {
            $status = $this->registration->resend();
        } catch (SmsDeliveryException) {
            return redirect()->route('join.account')->with('status', 'otp-send-failed');
        }

        return match ($status) {
            'sent' => redirect()->route('join.account')->with('status', 'otp-resent'),
            'missing' => redirect()->route('join.account'),
            'cooldown' => redirect()->route('join.account')->withErrors([
                'code' => __('Please wait a moment before requesting another code.'),
            ]),
            'limit' => redirect()->route('join.account')->withErrors([
                'code' => __('You have requested too many codes. Please start again.'),
            ]),
            default => redirect()->route('join.account'),
        };
    }

    /**
     * "The number or email is wrong": drop the passcode and show the form again.
     * The onboarding answers are kept.
     */
    public function editDetails(): RedirectResponse
    {
        $pending = PendingRegistration::get();

        $this->registration->cancel();

        return redirect()
            ->route('join.account')
            ->withInput($pending === null ? [] : PendingRegistration::input($pending));
    }

    /**
     * Sign the new teacher in and hand them the approval status page: the
     * survey is the whole application, so the only thing still owed is our
     * team's decision.
     */
    private function complete(Request $request, User $user): RedirectResponse
    {
        // Build the recap before the survey session is dropped: the status page
        // celebrates exactly what this teacher just answered.
        $summary = TeacherSurvey::summary(PendingTeacherSignup::answers());

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        PendingTeacherSignup::forget();

        return redirect()
            ->route('teacher.approval.status')
            ->with('status', 'welcome-to-teacher-setup')
            ->with('join_summary', $summary);
    }
}
