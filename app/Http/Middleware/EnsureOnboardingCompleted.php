<?php

namespace App\Http\Middleware;

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
            // Step 1 is the teaching profile; step 2 is verification. Teachers who
            // saved their profile but never submitted documents are sent to step 2.
            return $user->teacherProfile?->isComplete()
                ? redirect()->route('teacher.verification')->with('status', 'complete-your-verification')
                : redirect()->route('teacher.profile')->with('status', 'complete-your-profile');
        }

        return $next($request);
    }
}
