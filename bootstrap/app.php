<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'checklogin' => \App\Http\Middleware\EnsureLoggedIn::class,
            'auth.custom' => \App\Http\Middleware\EnsureLoggedIn::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        $handle419 = function (Request $request) {
            // Stale CSRF must not force a logout when the login session is still valid.
            $stillLoggedIn = (bool) $request->session()->get('loggedin');
            $message = $stillLoggedIn
                ? 'Your form token expired. Please submit again.'
                : 'Your session expired. Please log in again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 419);
            }

            if ($stillLoggedIn) {
                return redirect()
                    ->back()
                    ->withInput($request->except('_token', 'password', 'password_confirmation'))
                    ->with('error', $message);
            }

            return redirect()
                ->to(route('login', absolute: true))
                ->withInput($request->except('_token', 'password', 'password_confirmation'))
                ->with('error', $message);
        };

        // TokenMismatchException is converted to HttpException(419) before render callbacks,
        // so handle the final 419 response here.
        $exceptions->respond(function ($response, \Throwable $e, Request $request) use ($handle419) {
            if ($response->getStatusCode() === 405 && ! $request->expectsJson()) {
                return redirect()
                    ->route(session('loggedin') ? 'dashboard' : 'login')
                    ->with('error', 'That action is not available that way. Please use the buttons on the page.');
            }

            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            return $handle419($request);
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($handle419) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            return $handle419($request);
        });
    })->create();
