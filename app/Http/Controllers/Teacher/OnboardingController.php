<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherOnboardingDraftRequest;
use App\Models\TeacherOnboardingDraft;
use App\Services\TeacherOnboardingService;
use App\Support\TeacherApproval;
use App\Support\TeacherOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The teacher onboarding wizard: the welcome/resume screen, the confirmation
 * screen after submitting, and the draft endpoint every step autosaves into.
 *
 * The step screens themselves live on their own controllers (profile,
 * availability, verification) so their existing save logic — and every test
 * around it — keeps working.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly TeacherOnboardingService $onboarding) {}

    /**
     * The welcome screen: what setup needs, and where to resume.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route('teacher.dashboard');
        }

        $user->loadMissing(['teacherProfile.subjects', 'onboardingDraft']);

        $profile = $user->teacherProfile;
        $draft = $user->onboardingDraft;
        $steps = TeacherOnboarding::steps($profile, $draft);
        $resume = TeacherOnboarding::resumeStep($profile, $draft);

        $resumeUrl = match ($resume) {
            TeacherOnboarding::STEP_ABOUT => route('teacher.onboarding.profile'),
            TeacherOnboarding::STEP_PREFERENCES => route('teacher.onboarding.preferences'),
            TeacherOnboarding::STEP_AVAILABILITY => route('teacher.onboarding.availability'),
            TeacherOnboarding::STEP_VERIFICATION => route('teacher.onboarding.verification'),
            default => route('teacher.onboarding.profile'),
        };

        return view('teacher.onboarding.welcome', [
            'profile' => $profile,
            'draft' => $draft,
            'steps' => $steps,
            'resume' => $resume,
            'resumeUrl' => $resumeUrl,
            'hasStarted' => $profile !== null || ($draft !== null && ! $draft->isEmpty()),
            'firstName' => $user->displayName(),
            // Present only on the screen that follows the invite survey: what
            // the teacher just answered, shown as the celebration.
            'joinSummary' => $request->session()->get('join_summary'),
            'draftSavedAt' => $draft?->saved_at
                ? display_datetime($draft->saved_at)
                : null,
            'timezone' => $profile?->timezone ?: config('studylikepro.default_display_timezone'),
        ]);
    }

    /**
     * The approval status page: what the teacher sees after submitting, and the
     * page the pending-approval workspace points at. It has three phases —
     * in review, live, and action needed — so submitting never reads as "done".
     */
    public function submitted(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $profile = $user->teacherProfile;

        if (! $profile?->hasSubmittedVerification()) {
            return redirect()->route('teacher.onboarding.verification');
        }

        $profile->loadMissing(['documents', 'subjects']);

        $stage = TeacherApproval::stage($profile);
        $expectedDecisionAt = TeacherApproval::expectedDecisionAt($profile);

        return view('teacher.onboarding.submitted', [
            'profile' => $profile,
            'stage' => $stage,
            'submittedAt' => display_datetime($profile->submitted_at),
            'expectedDecisionAt' => $expectedDecisionAt ? display_datetime($expectedDecisionAt) : null,
            'isOverdue' => TeacherApproval::isOverdue($profile),
            'steps' => TeacherOnboarding::steps($profile, $user->onboardingDraft),
            'subjects' => $profile->subjects
                ->map(fn ($subject) => [
                    'name' => $subject->name,
                    'approved' => $subject->pivot->status === ApprovalStatus::Approved,
                ])
                ->values(),
            'documentCount' => $profile->documents->count(),
        ]);
    }

    /**
     * Autosave one step of the wizard. The request only checks types and
     * maximum sizes — half-typed answers are expected.
     */
    public function saveDraft(TeacherOnboardingDraftRequest $request): JsonResponse
    {
        $user = $request->user();
        $step = (string) $request->validated('step');
        $data = (array) $request->validated('data');

        /** @var TeacherOnboardingDraft $draft */
        $draft = $this->onboarding->storeDraft($user, $step, $data);

        return response()->json([
            'step' => $step,
            'savedAt' => $draft->saved_at?->toIso8601String(),
            'label' => $draft->saved_at ? display_datetime($draft->saved_at) : null,
        ]);
    }

    /**
     * Drop one step's draft, or the whole draft when no step is named.
     */
    public function destroyDraft(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'step' => ['nullable', 'string', \Illuminate\Validation\Rule::in(TeacherOnboarding::DATA_STEPS)],
        ]);

        $step = $validated['step'] ?? null;

        $this->onboarding->clearDraft($request->user(), $step);

        if ($request->wantsJson()) {
            return response()->json(['cleared' => $step]);
        }

        return back()->with('status', 'onboarding-draft-cleared');
    }
}
