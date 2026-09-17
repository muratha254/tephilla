<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized');
        }

        if (! $user->is_active) {
            abort(403, 'This account is inactive.');
        }

        if ($user->hasPermission($permission)) {
            return $next($request);
        }

        abort(403, 'You do not have the required permission.');
    }
}
