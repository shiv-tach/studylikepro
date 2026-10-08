<?php

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

/**
 * A CSRF token only goes stale when the session that rendered the page is gone:
 * it expired, another tab signed out, or the browser handed the page back from
 * its back/forward cache. Laravel skips the CSRF middleware while testing, so
 * these drive the exception handler directly — the same path a live request
 * takes, including the 419 the framework maps the mismatch onto.
 */
function renderStaleToken(Request $request)
{
    return app(ExceptionHandler::class)->render(
        $request,
        new TokenMismatchException('CSRF token mismatch.'),
    );
}

it('sends a guest with a dead token to sign in instead of showing a 419 page', function () {
    $response = renderStaleToken(Request::create('/login', 'POST'));

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe(route('login'))
        ->and(session('status'))->toBe('Your session has expired. Please try again.');
});

it('sends a signed-in user back to the page that carried the dead token', function () {
    $user = User::factory()->create();

    $request = Request::create(
        '/settings/notifications',
        'POST',
        server: ['HTTP_REFERER' => 'http://localhost/settings'],
    );
    // back() reads the referer off the request bound in the container, and
    // rebinding resets the user resolver, so the resolver goes on afterwards.
    $this->app->instance('request', $request);
    $request->setUserResolver(fn () => $user);

    $response = renderStaleToken($request);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toBe('http://localhost/settings');
});

it('still answers json clients with a 419 they can act on', function () {
    $request = Request::create(
        '/settings/notifications',
        'POST',
        server: ['HTTP_ACCEPT' => 'application/json'],
    );

    $response = renderStaleToken($request);

    expect($response->getStatusCode())->toBe(419)
        ->and($response->getData(true)['message'])->toContain('session has expired');
});
