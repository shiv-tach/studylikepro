<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser hardening: MIME sniffing off, framing limited to us, no
 * referrer leakage to third parties, and a Content-Security-Policy that names
 * every third party the app actually talks to (fonts, Razorpay checkout) while
 * blocking everything else. Alpine and the theme bootstrap script need inline
 * and eval'd JavaScript, so those stay allowed for scripts; everything else is
 * restricted to our own origin.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Daily hosts the lesson room in its own tab, but the browser asks us
        // for permission first, so camera/microphone stay delegated to it.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self "https://*.daily.co"), microphone=(self "https://*.daily.co"), '
            .'display-capture=(self "https://*.daily.co"), geolocation=(), payment=(self "https://checkout.razorpay.com")'
        );

        if ($request->secure()) {
            $age = (int) config('studylikepro.security.hsts_max_age');

            if ($age > 0) {
                $response->headers->set('Strict-Transport-Security', "max-age={$age}; includeSubDomains");
            }
        }

        if (config('studylikepro.security.csp_enabled')) {
            $response->headers->set('Content-Security-Policy', $this->policy());
        }

        return $response;
    }

    private function policy(): string
    {
        $script = ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://checkout.razorpay.com'];
        $style = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $connect = ["'self'", 'https://api.razorpay.com', 'https://lumberjack.razorpay.com'];
        $font = ["'self'", 'data:', 'https://fonts.gstatic.com'];

        // The Vite dev server serves assets and the hot-reload websocket, and its
        // port moves when 5173 is taken. Wildcard ports keep that working without
        // ever loosening the policy in production;
        // `npm run dev` binds it to 127.0.0.1 so the origin always matches.
        if (app()->environment('local')) {
            $dev = [
                'http://localhost:*', 'http://127.0.0.1:*', 'ws://localhost:*', 'ws://127.0.0.1:*',
            ];
            $script = [...$script, ...$dev];
            $style = [...$style, ...$dev];
            $connect = [...$connect, ...$dev];
        }

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            'font-src '.implode(' ', $font),
            "img-src 'self' data: blob: https:",
            'connect-src '.implode(' ', $connect),
            "media-src 'self' blob: https://*.daily.co",
            "frame-src 'self' https://api.razorpay.com https://checkout.razorpay.com",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directives);
    }
}
