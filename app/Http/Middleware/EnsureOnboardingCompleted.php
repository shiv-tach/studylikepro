<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    /**
     * Send users who have not finished onboarding to the page that finishes it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isStudent() && ! $user->hasCompletedOnboarding()) {
            return redirect()
                ->route('student.onboarding.show')
                ->with('status', 'complete-your-onboarding');
        }

        // A teacher's setup is the workspace itself: the profile page fills in
        // the story fields, and the verification page submits the application.
        if ($user->isTeacher() && ! $user->hasCompletedOnboarding()) {
            $hasProfile = $user->teacherProfile !== null;

            return redirect()
                ->route($hasProfile ? 'teacher.verification' : 'teacher.profile')
                ->with('status', $hasProfile ? 'complete-your-verification' : 'complete-your-profile');
        }

        return $next($request);
    }
}
