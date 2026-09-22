<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\SubscriptionCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SystemOwnerBootstrapper
{
    public function ensurePlans(): void
    {
        $defaults = [
            [
                'name' => 'Trial',
                'slug' => 'trial',
                'description' => '14-day evaluation. One shop. All roles and modules included.',
                'price' => 0,
                'billing_period' => SubscriptionCatalog::PERIOD_CUSTOM,
                'duration_days' => 14,
                'max_users' => null,
                'max_branches' => 1,
                'features' => SubscriptionCatalog::allFeatureKeys(),
                'sort_order' => 1,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'One shop. All roles and modules included.',
                'price' => 2500,
                'billing_period' => SubscriptionCatalog::PERIOD_MONTHLY,
                'duration_days' => null,
                'max_users' => null,
                'max_branches' => 1,
                'features' => SubscriptionCatalog::allFeatureKeys(),
                'sort_order' => 2,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Up to 5 shops. All roles and modules included.',
                'price' => 6500,
                'billing_period' => SubscriptionCatalog::PERIOD_MONTHLY,
                'duration_days' => null,
                'max_users' => null,
                'max_branches' => 5,
                'features' => SubscriptionCatalog::allFeatureKeys(),
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited shops. All roles and modules included.',
                'price' => 18000,
                'billing_period' => SubscriptionCatalog::PERIOD_ANNUALLY,
                'duration_days' => null,
                'max_users' => null,
                'max_branches' => null,
                'features' => SubscriptionCatalog::allFeatureKeys(),
                'sort_order' => 4,
            ],
        ];

        foreach ($defaults as $row) {
            SubscriptionPlan::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row + ['is_active' => true]
            );
        }
    }

    public function ensureOwnerUser(): User
    {
        $role = Role::query()->withoutGlobalScope('company')->firstOrCreate(
            ['name' => PermissionCatalog::SYSTEM_OWNER, 'company_id' => null],
            [
                'display_name' => 'System Owner',
                'description' => 'Platform administrator. Manages all business subscriptions.',
                'is_system' => true,
            ]
        );

        $user = User::query()->withTrashed()->where('email', 'owner@mail.com')->first();
        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }
            $user->update([
                'company_id' => null,
                'branch_id' => null,
                'role_id' => $role->id,
                'is_active' => true,
            ]);

            return $user->fresh();
        }

        return User::query()->create([
            'company_id' => null,
            'branch_id' => null,
            'role_id' => $role->id,
            'name' => 'System Owner',
            'email' => 'owner@mail.com',
            'username' => 'owner',
            'password' => Hash::make('1234'),
            'is_active' => true,
        ]);
    }
}
