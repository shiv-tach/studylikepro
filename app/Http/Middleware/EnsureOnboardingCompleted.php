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
            // Step 1 is the teaching profile, step 2 the weekly availability and
            // step 3 verification. Send the teacher to the first step they owe.
            return match (true) {
                ! $user->teacherProfile?->isComplete()
                    => redirect()->route('teacher.profile')->with('status', 'complete-your-profile'),
                ! TeacherOnboarding::hasAvailability($user->teacherProfile)
                    => redirect()->route('teacher.availability.setup')->with('status', 'complete-your-availability'),
                default
                    => redirect()->route('teacher.verification')->with('status', 'complete-your-verification'),
            };
        }

        return $next($request);
    }
}
