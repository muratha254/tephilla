<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictToLoginOnly
{
    /**
     * Allow only the login page and its form submission.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('/') || $request->is('login') || $request->is('logout')) {
            return $next($request);
        }

        return redirect()->route('login');
    }
}
