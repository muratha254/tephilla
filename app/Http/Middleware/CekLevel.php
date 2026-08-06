<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CekLevel
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param mixed $level  [1. admin | 2. kasir]
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$level)
    {
        $user = auth()->user();
        if ($user && ($user->hasRole('admin') || in_array($user->level, $level))) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
