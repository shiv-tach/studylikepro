<?php

namespace App\Http\Middleware;

use App\Support\PendingTeacherSignup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the guest half of the invite onboarding wizard.
 *
 * The wizard's answers live in the session, so every step needs the invite the
 * visitor arrived with. When it is missing — or the link expired or was used in
 * another tab — the visitor is sent back to the entry point with the same
 * message the register page shows, instead of a bare 403.
 */
class EnsureInviteSignupStarted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PendingTeacherSignup::invite() !== null) {
            return $next($request);
        }

        $token = PendingTeacherSignup::token();

        PendingTeacherSignup::forget();

        // A token that no longer works still shows *why* on the entry page.
        return blank($token)
            ? redirect()->route('register')
            : redirect()->route('register', ['invite' => $token]);
    }
}
