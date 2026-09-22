<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\Role;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\SubscriptionCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use CreatesSellixWorld;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
        $this->owner = $this->makeSystemOwner();
    }

    public function test_guest_is_sent_to_login_for_owner_and_dashboard(): void
    {
        $this->get(route('owner.dashboard'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('owner.businesses.index'))->assertRedirect(route('login'));
    }

    public function test_owner_login_opens_owner_console_not_pos(): void
    {
        $this->from(route('login'))
            ->post('/login', ['email' => 'owner-cycle@test.local', 'password' => 'secret'])
            ->assertRedirect(route('owner.dashboard'));

        $this->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('System Owner Dashboard')
            ->assertSee('Total Businesses')
            ->assertSee('Active');

        $this->get(route('pos.index'))->assertRedirect(route('owner.dashboard'));
        $this->get(route('products.index'))->assertRedirect(route('owner.dashboard'));
    }

    public function test_plan_validation_and_crud(): void
    {
        $this->actingAsOwner();

        $this->post(route('owner.plans.store'), [
            'price' => -1,
            'billing_period' => 'not-a-period',
        ])->assertSessionHasErrors(['name', 'price', 'billing_period']);

        $this->post(route('owner.plans.store'), [
            'name' => 'Starter QA',
            'description' => 'QA plan',
            'price' => 1000,
            'billing_period' => SubscriptionCatalog::PERIOD_MONTHLY,
            'max_users' => 2,
            'max_branches' => 1,
            'features' => ['pos', 'products', 'inventory', 'sales', 'customers', 'payments'],
            'is_active' => 1,
            'sort_order' => 5,
        ])->assertRedirect(route('owner.plans.index'));

        $plan = SubscriptionPlan::query()->where('name', 'Starter QA')->first();
        $this->assertNotNull($plan);
        $this->assertNull($plan->max_users);
        $this->assertSame(1, (int) $plan->max_branches);
        $this->assertContains('pos', $plan->featureList());
        $this->assertContains('hr', $plan->featureList());

        $this->get(route('owner.plans.edit', $plan))->assertOk()->assertSee('Starter QA');

        $this->put(route('owner.plans.update', $plan), [
            'name' => 'Starter QA Plus',
            'description' => 'Edited',
            'price' => 1500,
            'billing_period' => SubscriptionCatalog::PERIOD_QUARTERLY,
            'max_users' => 3,
            'max_branches' => 1,
            'features' => ['pos', 'products', 'inventory', 'sales', 'customers', 'payments', 'reports'],
            'is_active' => 1,
            'sort_order' => 5,
        ])->assertRedirect(route('owner.plans.index'));

        $this->assertSame('Starter QA Plus', $plan->fresh()->name);
        $this->assertSame(SubscriptionCatalog::PERIOD_QUARTERLY, $plan->fresh()->billing_period);
    }

    public function test_create_business_assign_subscription_and_full_owner_actions(): void
    {
        $this->actingAsOwner();
        $plan = $this->makeLimitedPlan();

        $this->post(route('owner.businesses.store'), [
            'name' => 'QA Shop One',
            'owner_name' => 'Jane Owner',
            'email' => 'qa-shop@test.local',
            'phone' => '0700111222',
            'address' => 'Nairobi',
            'plan_id' => $plan->id,
            'status' => SubscriptionCatalog::STATUS_ACTIVE,
            'starts_at' => now()->toDateString(),
            'admin_name' => 'Jane Admin',
            'admin_email' => 'jane-admin@test.local',
            'admin_username' => 'janeadmin',
            'admin_password' => 'secret',
            'admin_password_confirmation' => 'secret',
            'notes' => 'Created in QA',
        ])->assertSessionHasNoErrors();

        $company = Company::query()->where('email', 'qa-shop@test.local')->first();
        $this->assertNotNull($company);
        $this->assertNotNull($company->subscription);
        $this->assertSame($plan->id, (int) $company->subscription->subscription_plan_id);
        $this->assertSame(SubscriptionCatalog::STATUS_ACTIVE, $company->subscription->effectiveStatus());

        $this->get(route('owner.businesses.index'))
            ->assertOk()
            ->assertSee('QA Shop One');

        $this->get(route('owner.businesses.show', $company))
            ->assertOk()
            ->assertSee('QA Shop One')
            ->assertSee('Subscription history')
            ->assertSee('Jane Admin');

        $this->assertDatabaseHas('subscription_history', [
            'company_id' => $company->id,
            'action' => SubscriptionCatalog::ACTION_CREATED,
        ]);

        $oldExpiry = $company->subscription->expires_at->toDateString();
        $this->post(route('owner.businesses.renew', $company), [
            'plan_id' => $plan->id,
            'notes' => 'Renewed in QA',
            'amount' => 1000,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
            'reference' => 'QA-REN-1',
        ])->assertRedirect();
        $this->assertTrue($company->subscription->fresh()->expires_at->gt($oldExpiry));

        $this->post(route('owner.businesses.extend', $company), [
            'days' => 10,
            'notes' => 'Grace days',
        ])->assertRedirect();

        $enterprise = SubscriptionPlan::query()->where('slug', 'enterprise')->first();
        $this->post(route('owner.businesses.change-plan', $company), [
            'plan_id' => $enterprise->id,
            'notes' => 'Upgrade',
        ])->assertRedirect();
        $this->assertSame($enterprise->id, (int) $company->subscription->fresh()->subscription_plan_id);

        $this->post(route('owner.businesses.suspend', $company), ['notes' => 'QA suspend'])
            ->assertRedirect();
        $this->assertSame(SubscriptionCatalog::STATUS_SUSPENDED, $company->subscription->fresh()->effectiveStatus());

        $this->post(route('owner.businesses.activate', $company), ['notes' => 'QA activate'])
            ->assertRedirect();
        $this->assertSame(SubscriptionCatalog::STATUS_ACTIVE, $company->subscription->fresh()->effectiveStatus());

        $this->assertGreaterThan(1, SubscriptionHistory::query()->where('company_id', $company->id)->count());
        $this->assertDatabaseHas('subscription_payments', [
            'company_id' => $company->id,
            'reference' => 'QA-REN-1',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'subscriptions',
            'action' => 'renew',
        ]);
    }

    public function test_tenant_active_expiring_expired_suspended_and_owner_still_works(): void
    {
        $admin = $this->admin->fresh(['role.permissions', 'company']);
        $subscription = $this->company->subscription;

        $this->actingAs($admin)->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');

        $subscription->update(['expires_at' => now()->addDays(3)->toDateString()]);
        $this->actingAs($admin->fresh(['role.permissions', 'company']))
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your subscription expires in 3 days');

        $subscription->update(['expires_at' => now()->subDay()->toDateString(), 'status' => SubscriptionCatalog::STATUS_ACTIVE]);
        $this->actingAs($admin->fresh(['role.permissions', 'company']))
            ->get(route('pos.index'))
            ->assertRedirect(route('subscription.blocked', ['reason' => SubscriptionCatalog::STATUS_EXPIRED]));

        $this->actingAs($admin->fresh(['role.permissions', 'company']))
            ->get(route('subscription.blocked', ['reason' => SubscriptionCatalog::STATUS_EXPIRED]))
            ->assertOk()
            ->assertSee('Subscription expired');

        $subscription->update([
            'status' => SubscriptionCatalog::STATUS_SUSPENDED,
            'expires_at' => now()->addMonth()->toDateString(),
        ]);
        $this->actingAs($admin->fresh(['role.permissions', 'company']))
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.blocked', ['reason' => SubscriptionCatalog::STATUS_SUSPENDED]));

        $this->actingAs($admin->fresh(['role.permissions', 'company']))
            ->get(route('subscription.blocked', ['reason' => SubscriptionCatalog::STATUS_SUSPENDED]))
            ->assertOk()
            ->assertSee('Account suspended');

        $this->actingAsOwner()
            ->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('System Owner Dashboard');

        $this->actingAsOwner()
            ->post(route('owner.businesses.activate', $this->company), ['notes' => 'Lift suspend'])
            ->assertRedirect();

        $this->actingAs($admin->fresh(['role.permissions', 'company']))
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_branch_limit_is_enforced_and_modules_are_open(): void
    {
        $this->company->subscription->plan->update([
            'max_users' => 1,
            'max_branches' => Branch::query()->where('company_id', $this->company->id)->count(),
            'features' => ['pos'],
        ]);
        $this->company->unsetRelation('subscription');

        $admin = $this->admin->fresh(['role.permissions', 'company']);

        $this->actingAs($admin)->post(route('users.store'), [
            'username' => 'anotheruser',
            'email' => 'anotheruser@test.local',
            'branch_id' => $this->branch->id,
            'role_id' => $this->roles[PermissionCatalog::CASHIER]->id,
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ])->assertRedirect(route('users.index'));

        $this->actingAs($admin)->post(route('settings.branches.store'), [
            'name' => 'Blocked Shop',
            'code' => 'BLK',
            'system_mode' => 'Touch Mode(Normal)',
            'phone' => '0700000000',
            'include_catering_levy' => 'No',
            'auto_receipt_amt_pos' => 'No',
            'list_on_login' => 'Yes',
            'is_active' => 'Yes',
        ])->assertSessionHas('error');

        $this->actingAs($admin)->get(route('reports.sales.sales'))->assertOk();
        $this->actingAs($admin)->get(route('manufacturing.bom.index'))->assertOk();
        $this->actingAs($admin)->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('products.index'))
            ->assertOk();
    }

    public function test_tenant_cannot_open_owner_pages_or_other_company_data(): void
    {
        $other = Company::query()->create([
            'name' => 'Other Co',
            'slug' => 'other-co-' . Str::lower(Str::random(4)),
            'is_active' => true,
            'currency_code' => 'KES',
            'currency_symbol' => 'KSh',
        ]);
        $secret = Product::query()->withoutGlobalScopes()->create([
            'company_id' => $other->id,
            'name' => 'SECRET-OTHER-COMPANY-ITEM',
            'sku' => 'SEC-' . Str::upper(Str::random(4)),
            'purchase_price' => 1,
            'selling_price' => 2,
            'manage_stock' => false,
            'is_active' => true,
            'for_sale' => true,
        ]);

        $this->actingAsAdmin()
            ->get(route('owner.dashboard'))
            ->assertForbidden();
        $this->actingAsAdmin()
            ->get(route('owner.businesses.index'))
            ->assertForbidden();
        $this->actingAsAdmin()
            ->post(route('owner.businesses.suspend', $this->company))
            ->assertForbidden();

        $this->actingAsAdmin()
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('SECRET-OTHER-COMPANY-ITEM');

        $this->actingAsAdmin()->get(route('products.show', $secret))->assertNotFound();
    }

    public function test_business_create_validation_errors(): void
    {
        $this->actingAsOwner();
        $this->post(route('owner.businesses.store'), [
            'name' => '',
            'admin_password' => 'x',
        ])->assertSessionHasErrors(['name', 'owner_name', 'email', 'plan_id', 'admin_email', 'admin_username', 'admin_password']);
    }

    protected function actingAsOwner()
    {
        return $this->actingAs($this->owner->fresh(['role']));
    }

    protected function makeSystemOwner(): User
    {
        $role = Role::query()->withoutGlobalScope('company')->firstOrCreate(
            ['name' => PermissionCatalog::SYSTEM_OWNER, 'company_id' => null],
            [
                'display_name' => 'System Owner',
                'description' => 'Platform',
                'is_system' => true,
            ]
        );

        return User::query()->create([
            'company_id' => null,
            'branch_id' => null,
            'role_id' => $role->id,
            'name' => 'Cycle Owner',
            'email' => 'owner-cycle@test.local',
            'username' => 'ownercycle',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);
    }

    protected function makeLimitedPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::query()->create([
            'name' => 'Limited QA',
            'slug' => 'limited-qa-' . Str::lower(Str::random(4)),
            'description' => 'Limits',
            'price' => 500,
            'billing_period' => SubscriptionCatalog::PERIOD_MONTHLY,
            'max_users' => 5,
            'max_branches' => 2,
            'features' => ['pos', 'products', 'inventory', 'sales', 'customers', 'payments'],
            'is_active' => true,
        ]);
    }
}
