<?php

namespace App\Services;

use App\Exceptions\SubscriptionLimitException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionLimitGuard
{
    public function assertCanCreateUser(Company $company): void
    {
        // Plans do not limit users or roles. Only shop/branch counts are capped.
    }

    public function assertCanCreateBranch(Company $company): void
    {
        $max = optional(optional($company->subscription)->plan)->max_branches;
        if (! $max) {
            return;
        }

        $count = Branch::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->count();

        if ($count >= (int) $max) {
            throw new SubscriptionLimitException(
                'Your current subscription allows a maximum of ' . $max . ' shop' . ($max === 1 ? '' : 's') . '/branches. Please upgrade your subscription or contact the System Owner.'
            );
        }
    }

    public function usage(Company $company): array
    {
        $plan = optional($company->subscription)->plan;

        return [
            'users' => User::query()->where('company_id', $company->id)->where('is_active', true)->count(),
            'max_users' => null,
            'branches' => Branch::query()->withoutGlobalScope('company')->where('company_id', $company->id)->count(),
            'max_branches' => $plan->max_branches ?? null,
        ];
    }
}
