<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (in_array('*', $roles, true)) {
            return $next($request);
        }

        if (method_exists($user, 'hasAnyRole')) {
            if ($user->hasAnyRole($roles)) {
                return $next($request);
            }
        } else {
            if (in_array(strtolower((string) $user->role), array_map('strtolower', $roles), true)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this resource.');
    }
}

















