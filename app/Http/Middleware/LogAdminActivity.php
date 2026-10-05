<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every state-changing admin console request in the audit trail. Reads
 * stay out of it, and so do failed requests: the log reflects what actually
 * happened.
 */
class LogAdminActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $description = $this->logger->takeDescription();

        $isStateChanging = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        // Anything that did not take effect stays out of the trail too: failed
        // requests and form submissions that bounced back with errors.
        $failedValidation = $response->isRedirection() && $request->session()->has('errors');

        if (! $isStateChanging || $response->getStatusCode() >= 400 || $failedValidation) {
            return $response;
        }

        $route = $request->route();
        $action = $route?->getName() ?? $request->path();

        $this->logger->log(
            actor: $request->user(),
            action: $action,
            subject: $this->resolveSubject($route),
            description: $description,
            properties: [
                'route' => $action,
                'method' => $request->method(),
                'input' => $this->logger->sanitisedInput($request),
            ],
            ipAddress: $request->ip(),
        );

        return $response;
    }

    /**
     * The first route-bound model makes the best subject (booking, payment…).
     */
    private function resolveSubject(?Route $route): ?Model
    {
        foreach ($route?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }
}
