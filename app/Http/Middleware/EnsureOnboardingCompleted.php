<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    /**
     * Send users who have not finished their profile to the onboarding form.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isStudent() && ! $user->hasCompletedOnboarding()) {
            return redirect()
                ->route('student.profile')
                ->with('status', 'complete-your-profile');
        }

        if ($user->isTeacher() && ! $user->hasCompletedOnboarding()) {
            return redirect()
                ->route('teacher.profile')
                ->with('status', 'complete-your-profile');
        }

        return $next($request);
    }
}
