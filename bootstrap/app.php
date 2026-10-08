<?php

use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Http\Middleware\EnsureUserIsNotSuspended;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Adds the browser hardening headers to every response, API and web.
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'onboarded' => EnsureOnboardingCompleted::class,
            'not-suspended' => EnsureUserIsNotSuspended::class,
        ]);

        // Payment providers post signed callbacks with no session or CSRF token.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'payments/*/return',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A rejected CSRF token is not an error the user can act on: it only
        // means the session the page was rendered with is gone — it expired,
        // another tab logged out, or the browser restored the page from its
        // back/forward cache. Send them to a screen holding a fresh token
        // instead of Laravel's bare "419 Page Expired" page. A token mismatch
        // reaches this callback as a 419: the framework rewrites it before
        // render callbacks run.
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Your session has expired. Please refresh and try again.'),
                ], 419);
            }

            $status = __('Your session has expired. Please try again.');

            // A signed-in user only needs the page they were on back, which
            // renders with a new token; a guest has to restart at sign-in.
            return $request->user() === null
                ? redirect()->route('login')->with('status', $status)
                : redirect()->back()->with('status', $status);
        });
    })->create();
