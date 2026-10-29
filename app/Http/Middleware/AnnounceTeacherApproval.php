<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Approval is the real finish line. Until the teacher has seen the "You're
 * live" screen, send them there before the workspace — but only on page loads,
 * so a form post is never swallowed by the announcement.
 */
class AnnounceTeacherApproval
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $profile = $user?->isTeacher() ? $user->teacherProfile : null;

        if ($profile?->owesLiveAnnouncement()
            && $request->isMethod('GET')
            && ! $request->routeIs('teacher.welcome.live', 'teacher.welcome.live.ack', 'logout', 'notifications.*')) {
            return redirect()->route('teacher.welcome.live');
        }

        return $next($request);
    }
}
