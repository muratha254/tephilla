<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // If user is an admin, always allow access
        if ($user->hasRole(User::ROLE_ADMIN)) {
            return $next($request);
        }

        $permission = strtolower($permission);
        // Support module.action format, e.g., inventory.create
        if (strpos($permission, '.') !== false) {
            [$module, $action] = explode('.', $permission, 2);
            if (method_exists($user, 'hasModulePermission') && $user->hasModulePermission($module, $action)) {
                return $next($request);
            }
        } else {
            $map = [
                'create' => 'can_create',
                'read' => 'can_read',
                'update' => 'can_update',
                'delete' => 'can_delete',
            ];
            $key = $map[$permission] ?? null;
            if ($key && (bool) ($user->{$key} ?? false)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have the required permission.');
    }
}



