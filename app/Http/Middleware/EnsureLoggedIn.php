<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoggedIn
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('loggedin')) {
            return redirect()->route('login')->with('error', 'Please log in to continue.');
        }

        $response = $next($request);

        // Avoid bfcache serving pages with a stale CSRF token after idle time.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}