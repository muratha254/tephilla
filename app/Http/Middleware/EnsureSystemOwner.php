<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isSystemOwner()) {
            abort(403, 'Only the System Owner can access this area.');
        }

        if (! $user->is_active) {
            abort(403, 'This account is inactive.');
        }

        return $next($request);
    }
}
