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
        // TokenMismatchException is converted to HttpException(419) before render callbacks,
        // so handle the final 419 response here.
        $exceptions->respond(function ($response, \Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            $message = 'Your session expired. Please try again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 419);
            }

            return redirect()
                ->to(route('login', absolute: true))
                ->withInput($request->except('_token', 'password', 'password_confirmation'))
                ->with('error', $message);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            $message = 'Your session expired. Please try again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 419);
            }

            return redirect()
                ->to(route('login', absolute: true))
                ->withInput($request->except('_token', 'password', 'password_confirmation'))
                ->with('error', $message);
        });
    })->create();
