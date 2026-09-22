<?php

namespace App\Http\Middleware;

use App\Services\FeatureAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if ($user && $user->isSystemOwner()) {
            abort(403, 'System Owner accounts cannot open a business workspace.');
        }

        if (! app(FeatureAccess::class)->allows($feature, $user)) {
            abort(403, 'Your current subscription does not include this module. Please upgrade or contact the System Owner.');
        }

        return $next($request);
    }
}
