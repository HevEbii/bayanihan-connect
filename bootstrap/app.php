<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
        $middleware->appendToGroup('web', EnsureUserIsActive::class);

        // Behind a host's gateway or load balancer (e.g. Laravel Cloud) set TRUSTED_PROXIES=* so https links
        // and visitor IPs are read correctly. Left unset on a plain server, where trusting it would let
        // visitors fake their IP address.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Denied actions are logged for audit (FR08); the user only sees a generic 403 page.
        $logDenied = function (Throwable $e, Request $request) {
            if ($request->user()) {
                Audit::record('access.denied', 'Denied: '.$request->method().' /'.$request->path());
            }
        };
        $exceptions->render(function (AuthorizationException $e, Request $request) use ($logDenied) {
            $logDenied($e, $request);
        });
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($logDenied) {
            $logDenied($e, $request);
        });
        // A form left open too long has a stale security token: send the user back instead of a bare 419 page.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            return redirect($request->user() ? url()->previous() : route('login'))
                ->withInput($request->except('password', '_token'))
                ->withErrors(['email' => 'Your session expired. Please try again.']);
        });
    })->create();
