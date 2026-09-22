<?php

namespace App\Http\Middleware;

use App\Support\SubscriptionCatalog;
use Closure;
use Illuminate\Http\Request;

class EnsureSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        if ($user->isSystemOwner()) {
            if ($request->routeIs('owner.*', 'logout', 'subscription.*')) {
                return $next($request);
            }

            return redirect()->route('owner.dashboard');
        }

        if (! $user->company_id) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'This account is not linked to a business.');
        }

        $company = $user->company;
        if (! $company || ! $company->is_active) {
            return redirect()->route('subscription.blocked', ['reason' => SubscriptionCatalog::STATUS_DEACTIVATED]);
        }

        $subscription = $company->subscription()->with('plan')->first();
        if (! $subscription || ! $subscription->allowsAccess()) {
            $reason = $subscription
                ? $subscription->effectiveStatus()
                : SubscriptionCatalog::STATUS_EXPIRED;

            return redirect()->route('subscription.blocked', ['reason' => $reason]);
        }

        return $next($request);
    }

    protected function shouldBypass(Request $request): bool
    {
        return $request->routeIs(
            'login',
            'register',
            'register.*',
            'logout',
            'password.*',
            'password.confirm',
            'two-factor.*',
            'subscription.blocked',
            'billing.index',
            'billing.invoices.show',
            'owner.*'
        );
    }
}
