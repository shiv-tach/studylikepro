<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The workspace is for approved teachers. Someone whose application is still in
 * review (or was rejected) is sent to the approval status page instead of a
 * dashboard that looks live but cannot receive a booking.
 */
class EnsureTeacherApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isTeacher()) {
            return $next($request);
        }

        // Setup is not finished: the onboarding gate owns that redirect.
        if (! $user->hasCompletedOnboarding()) {
            return $next($request);
        }

        $profile = $user->teacherProfile;

        if ($profile?->isApproved()) {
            return $next($request);
        }

        return redirect()
            ->route('teacher.approval.status')
            ->with('status', $profile?->isRejected() ? 'approval-action' : 'approval-pending');
    }
}
