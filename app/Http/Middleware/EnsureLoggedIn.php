<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureLoggedIn
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->get('loggedin')) {
            return redirect()->route('login')->with('error', 'Please log in to continue.');
        }
        return $next($request);
    }
}