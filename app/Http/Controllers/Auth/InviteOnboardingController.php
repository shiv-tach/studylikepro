<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\RegistrationConflictException;
use App\Exceptions\SmsDeliveryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherOnboardingAboutRequest;
use App\Http\Requests\TeacherOnboardingDraftRequest;
use App\Http\Requests\TeacherOnboardingPreferencesRequest;
use App\Http\Requests\WeeklyAvailabilityRequest;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Rules\SriLankanMobile;
use App\Services\InviteOnboardingService;
use App\Services\MobileRegistrationService;
use App\Support\AvailabilitySetupConfig;
use App\Support\PendingRegistration;
use App\Support\PendingTeacherSignup;
use App\Support\PhoneNumber;
use App\Support\TeacherOnboarding;
use App\Support\TeacherProfileConfig;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * The teacher onboarding wizard as a *guest*.
 *
 * An invite link opens this instead of a sign-up form: the visitor answers the
 * same steps an invited teacher answers, but nothing is stored against a user
 * yet — the answers live in the session. The last step asks for the account
 * details and password, texts an SMS passcode, and only when the code matches
 * is the `users` row created, with the profile, weekly hours and photo the
 * guest already filled in (see `InviteOnboardingService`).
 */
class InviteOnboardingController extends Controller
{
    public function __construct(private readonly MobileRegistrationService $registration) {}

    /**
     * Step 1: what setup needs, and where to continue.
     */
    public function welcome(): View
    {
        $draft = PendingTeacherSignup::draft();
        $invite = PendingTeacherSignup::invite();

        return view('join.welcome', [
            'steps' => TeacherOnboarding::guestSteps(PendingTeacherSignup::answers()),
            'resume' => PendingTeacherSignup::nextRouteName(),
            'resumeUrl' => route(PendingTeacherSignup::nextRouteName()),
            'hasStarted' => $draft !== null,
            'draftSavedAt' => PendingTeacherSignup::savedAt()
                ? display_datetime(PendingTeacherSignup::savedAt())
                : null,
            'inviteExpiresAt' => $invite?->expires_at ? display_datetime($invite->expires_at) : null,
            'guest' => true,
            'currentStep' => 1,
        ]);
    }

    /**
     * Step 2: "About you".
     */
    public function editAbout(Request $request): View|RedirectResponse
    {
        return view('teacher.onboarding.about', [
            'profile' => $this->blankProfile(),
            'config' => TeacherProfileConfig::build(
                null,
                $this->blankProfile(),
                PendingTeacherSignup::draft(),
                $this->errorBag($request),
                'classic',
                'onboarding',
                TeacherOnboarding::STEP_ABOUT,
                null,
                guest: true,
            ),
            'guest' => true,
            'currentStep' => 2,
        ]);
    }

    public function updateAbout(TeacherOnboardingAboutRequest $request): RedirectResponse
    {
        PendingTeacherSignup::putStep(TeacherOnboarding::STEP_ABOUT, $request->validated());

        return redirect()
            ->route('join.preferences')
            ->with('status', 'about-saved');
    }

    /**
     * Step 3: "Teaching preferences".
     */
    public function editPreferences(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherOnboarding::STEP_ABOUT)) {
            return redirect()
                ->route('join.profile')
                ->with('status', 'complete-your-profile');
        }

        return view('teacher.onboarding.preferences', [
            'profile' => $this->blankProfile(),
            'config' => TeacherProfileConfig::build(
                null,
                $this->blankProfile(),
                PendingTeacherSignup::draft(),
                $this->errorBag($request),
                'classic',
                'onboarding',
                TeacherOnboarding::STEP_PREFERENCES,
                null,
                guest: true,
            ),
            'guest' => true,
            'currentStep' => 3,
        ]);
    }

    public function updatePreferences(TeacherOnboardingPreferencesRequest $request): RedirectResponse
    {
        PendingTeacherSignup::putStep(
            TeacherOnboarding::STEP_PREFERENCES,
            $request->safe()->except(['avatar']),
        );

        $this->storePendingAvatar($request);

        return redirect()
            ->route('join.availability')
            ->with('status', 'preferences-saved');
    }

    /**
     * Step 4: "Weekly availability".
     */
    public function editAvailability(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherOnboarding::STEP_PREFERENCES)) {
            return redirect()
                ->route('join.preferences')
                ->with('status', 'complete-your-preferences');
        }

        $profile = $this->blankProfile();

        return view('teacher.onboarding.availability', [
            'profile' => $profile,
            'config' => AvailabilitySetupConfig::build(
                $profile,
                $this->errorBag($request),
                'classic',
                PendingTeacherSignup::draft(),
                guest: true,
            ),
            'guest' => true,
            'currentStep' => 4,
        ]);
    }

    public function updateAvailability(WeeklyAvailabilityRequest $request): RedirectResponse
    {
        PendingTeacherSignup::putStep(TeacherOnboarding::STEP_AVAILABILITY, [
            'days' => $request->validated('days'),
        ]);

        return redirect()->route('join.account');
    }

    /**
     * Autosave one step while the visitor is still typing. Nothing is validated
     * beyond types: half-typed answers are the whole point.
     */
    public function saveDraft(TeacherOnboardingDraftRequest $request): JsonResponse
    {
        $step = (string) $request->validated('step');
        $data = (array) $request->validated('data');

        abort_unless(in_array($step, PendingTeacherSignup::ANSWER_STEPS, true), 422);

        $data = $step === TeacherOnboarding::STEP_AVAILABILITY
            ? $this->normaliseWeek($data)
            : $data;

        // A week with no usable range must never replace one that was already
        // submitted, or the account would be created without bookable hours.
        if ($data !== []) {
            PendingTeacherSignup::putDraft($step, $data);
        }

        return response()->json([
            'step' => $step,
            'savedAt' => PendingTeacherSignup::savedAt()?->toIso8601String(),
            'label' => PendingTeacherSignup::savedAt()
                ? display_datetime(PendingTeacherSignup::savedAt())
                : null,
        ]);
    }

    /**
     * Step 5: identity + password, or the SMS code when one was already sent.
     */
    public function editAccount(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::isComplete()) {
            return redirect()
                ->route(PendingTeacherSignup::nextRouteName())
                ->with('status', 'complete-your-availability');
        }

        $pending = PendingRegistration::get();
        $policy = $this->registration->policy();

        return view('join.account', [
            'pending' => $pending,
            'mobile' => $pending !== null ? PhoneNumber::mask((string) $pending['mobile']) : null,
            'length' => $policy['length'],
            'cooldown' => $this->registration->cooldownRemaining(),
            'otpEnabled' => $policy['enabled'],
            'steps' => TeacherOnboarding::guestSteps(PendingTeacherSignup::answers()),
            'guest' => true,
            'currentStep' => 5,
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
        ], [], [
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
     * Sign the new teacher in and hand them the signed-in wizard, which now
     * resumes at the verification step (ID documents + subjects).
     */
    private function complete(Request $request, User $user): RedirectResponse
    {
        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        PendingTeacherSignup::forget();

        return redirect()
            ->route('teacher.onboarding.verification')
            ->with('status', 'welcome-to-teacher-setup');
    }

    /**
     * A photo is validated like any other upload, but a guest has no user to
     * attach it to yet: park it and let `InviteOnboardingService` move it.
     */
    private function storePendingAvatar(Request $request): void
    {
        if (! $request->hasFile('avatar')) {
            return;
        }

        $current = PendingTeacherSignup::avatarPath();

        if ($current !== null) {
            Storage::disk('public')->delete($current);
        }

        PendingTeacherSignup::putAvatar(
            $request->file('avatar')->store(InviteOnboardingService::PENDING_AVATAR_DIRECTORY, 'public'),
        );
    }

    /**
     * The week arrives from the Vue editor with `start`/`end` keys, while the
     * submitted form and `WeeklyAvailabilityService` use `start_time`/`end_time`.
     * Store the canonical shape, and nothing at all when no range is usable.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normaliseWeek(array $data): array
    {
        $days = $data['days'] ?? null;

        if (! is_array($days)) {
            return [];
        }

        $saved = 0;
        $normalised = [];

        foreach (array_values($days) as $day) {
            if (! is_array($day)) {
                continue;
            }

            $enabled = filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $ranges = [];

            foreach ((array) ($day['ranges'] ?? []) as $range) {
                if (! is_array($range)) {
                    continue;
                }

                $start = $range['start_time'] ?? $range['start'] ?? null;
                $end = $range['end_time'] ?? $range['end'] ?? null;

                if (blank($start) || blank($end)) {
                    continue;
                }

                $ranges[] = ['start_time' => (string) $start, 'end_time' => (string) $end];
            }

            if ($enabled && $ranges !== []) {
                $saved += count($ranges);
            }

            $normalised[] = [
                'value' => (int) ($day['value'] ?? 0),
                'enabled' => $enabled,
                'ranges' => $ranges,
            ];
        }

        return $saved > 0 ? ['days' => $normalised] : [];
    }

    /**
     * The wizard views expect a profile; a guest has none yet, so an unsaved
     * one stands in with the platform's default timezone and lesson length.
     */
    private function blankProfile(): TeacherProfile
    {
        return new TeacherProfile([
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
    }

    private function errorBag(Request $request): MessageBag
    {
        return $request->session()->get('errors')?->getBag('default') ?? new MessageBag;
    }
}
