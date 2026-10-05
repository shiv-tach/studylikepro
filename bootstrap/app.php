<?php

use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Http\Middleware\EnsureUserIsNotSuspended;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\RoleMiddleware;

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
        //
    })->create();
