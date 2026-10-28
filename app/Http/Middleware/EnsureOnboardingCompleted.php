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

        // Approval is the real finish line. Until the teacher has seen the
        // "You're live" screen, send them there before the workspace — but only
        // on page loads, so a form post is never swallowed by the announcement.
        $profile = $user->teacherProfile;

        if ($profile?->owesLiveAnnouncement()
            && $request->isMethod('GET')
            && ! $request->routeIs('teacher.welcome.live', 'teacher.welcome.live.ack', 'logout', 'notifications.*')) {
            return redirect()->route('teacher.welcome.live');
        }

        return $next($request);
    }
}
