<?php

namespace App\Http\Middleware;

use App\Support\TeacherOnboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    /**
     * Send users who have not finished onboarding to the step they still owe.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isStudent() && ! $user->hasCompletedOnboarding()) {
            return redirect()
                ->route('student.onboarding.show')
                ->with('status', 'complete-your-onboarding');
        }

        if ($user->isTeacher() && ! $user->hasCompletedOnboarding()) {
            // The wizard resumes at the first step the teacher still owes —
            // including a step with an unfinished draft.
            $user->loadMissing('onboardingDraft');

            return redirect()
                ->route(TeacherOnboarding::nextRouteName($user->teacherProfile, $user->onboardingDraft))
                ->with('status', TeacherOnboarding::nextStatus($user->teacherProfile, $user->onboardingDraft));
        }

        return $next($request);
    }
}
