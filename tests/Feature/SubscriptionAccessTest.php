<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\SubscriptionCatalog;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
    }

    public function test_active_subscription_can_open_dashboard(): void
    {
        $this->actingAsAdmin()
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_expired_subscription_is_blocked_from_pos(): void
    {
        $this->company->subscription->update([
            'status' => SubscriptionCatalog::STATUS_ACTIVE,
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAsAdmin()
            ->get(route('pos.index'))
            ->assertRedirect(route('subscription.blocked', ['reason' => SubscriptionCatalog::STATUS_EXPIRED]));
    }

    public function test_suspended_subscription_is_blocked(): void
    {
        $this->company->subscription->update([
            'status' => SubscriptionCatalog::STATUS_SUSPENDED,
            'expires_at' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAsAdmin()
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.blocked', ['reason' => SubscriptionCatalog::STATUS_SUSPENDED]));
    }

    public function test_user_count_is_not_limited_by_plan(): void
    {
        $this->company->subscription->plan->update(['max_users' => 1]);
        $this->company->unsetRelation('subscription');

        $this->actingAsAdmin()
            ->post(route('users.store'), [
                'username' => 'extra',
                'email' => 'extra@test.local',
                'branch_id' => $this->branch->id,
                'role_id' => $this->roles[PermissionCatalog::CASHIER]->id,
                'password' => 'secret',
                'password_confirmation' => 'secret',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'extra@test.local']);
    }

    public function test_branch_limit_is_enforced(): void
    {
        $this->company->subscription->plan->update(['max_branches' => 1]);
        $this->company->unsetRelation('subscription');

        $this->actingAsAdmin()
            ->post(route('settings.branches.store'), [
                'name' => 'Shop 2',
                'code' => 'S2',
                'is_active' => 1,
            ])
            ->assertSessionHas('error');
    }

    public function test_tenant_cannot_open_owner_dashboard(): void
    {
        $this->actingAsAdmin()
            ->get(route('owner.dashboard'))
            ->assertForbidden();
    }

    public function test_system_owner_can_open_owner_dashboard_and_not_pos(): void
    {
        $role = Role::query()->withoutGlobalScope('company')->create([
            'company_id' => null,
            'name' => PermissionCatalog::SYSTEM_OWNER,
            'display_name' => 'System Owner',
            'is_system' => true,
        ]);
        $owner = User::query()->create([
            'company_id' => null,
            'branch_id' => null,
            'role_id' => $role->id,
            'name' => 'Owner',
            'email' => 'owner-test@test.local',
            'username' => 'owner-test',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $this->actingAs($owner->fresh(['role']))
            ->get(route('owner.dashboard'))
            ->assertOk();

        $this->actingAs($owner->fresh(['role']))
            ->get(route('pos.index'))
            ->assertRedirect(route('owner.dashboard'));
    }

    public function test_active_plan_allows_all_roles_and_modules(): void
    {
        $plan = $this->company->subscription->plan;
        $plan->update(['features' => ['pos']]);
        $this->company->unsetRelation('subscription');

        $admin = $this->admin->fresh(['role.permissions', 'company.subscription.plan']);
        $this->assertTrue($admin->hasPermission('reports.view'));
        $this->assertTrue($admin->hasPermission('hr.view'));
        $this->assertTrue($admin->hasPermission('dashboard.view'));
        $this->assertTrue($admin->hasPermission('users.view'));
        $this->assertTrue($this->cashier->fresh(['role.permissions', 'company.subscription.plan'])->hasPermission('pos.view'));
    }

    public function test_products_menu_appears_when_plan_has_products_but_not_manufacturing(): void
    {
        $plan = $this->company->subscription->plan;
        $plan->update(['features' => ['pos', 'products', 'inventory', 'sales', 'customers', 'payments']]);
        $this->company->unsetRelation('subscription');

        $this->actingAsAdmin()
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Items/Products');
    }
}
