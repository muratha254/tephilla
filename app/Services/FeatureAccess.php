<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\SubscriptionCatalog;

class FeatureAccess
{
    public function currentSubscription(?User $user = null): ?Subscription
    {
        $user = $user ?: auth()->user();
        if (! $user || $user->isSystemOwner() || ! $user->company_id) {
            return null;
        }

        $company = $user->relationLoaded('company') ? $user->company : $user->company()->first();
        if (! $company) {
            return null;
        }

        return $company->subscription()->with('plan')->first();
    }

    public function allows(string $feature, ?User $user = null): bool
    {
        $user = $user ?: auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->isSystemOwner()) {
            return false;
        }

        if (in_array($feature, SubscriptionCatalog::coreModules(), true)) {
            return true;
        }

        $subscription = $this->currentSubscription($user);
        if (! $subscription || ! $subscription->allowsAccess()) {
            return false;
        }

        return (bool) $subscription->plan;
    }

    public function allowsPermission(string $permission, ?User $user = null): bool
    {
        $meta = PermissionCatalog::permissions()[$permission] ?? null;
        $module = $meta['module'] ?? null;
        if (! $module) {
            return true;
        }

        return $this->allows($module, $user);
    }

    public function warningMessage(?User $user = null): ?string
    {
        $subscription = $this->currentSubscription($user);
        if (! $subscription) {
            return null;
        }

        $status = $subscription->effectiveStatus();
        $days = $subscription->daysRemaining();
        $expiry = optional($subscription->expires_at)->format('d M Y');

        if ($status === SubscriptionCatalog::STATUS_EXPIRED) {
            return 'Your subscription has expired.';
        }

        if (! in_array($status, [
            SubscriptionCatalog::STATUS_EXPIRING_SOON,
            SubscriptionCatalog::STATUS_TRIAL,
            SubscriptionCatalog::STATUS_ACTIVE,
        ], true)) {
            return null;
        }

        if ($days < 0) {
            return 'Your subscription has expired.';
        }
        if ($days === 0) {
            return 'Your subscription expires today' . ($expiry ? ' (' . $expiry . ')' : '') . '.';
        }
        if ($days === 1) {
            return 'Your subscription expires tomorrow.';
        }
        if ($days <= (int) config('sellix.subscription_expiring_days', 7)) {
            return 'Your subscription expires in ' . $days . ' days.';
        }

        return null;
    }
}
