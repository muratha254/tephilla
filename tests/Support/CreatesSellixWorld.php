<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AccountingCatalog;
use App\Services\SettingsService;
use App\Support\PermissionCatalog;
use App\Support\SubscriptionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait CreatesSellixWorld
{
    use RefreshDatabase;

    protected Company $company;

    protected Branch $branch;

    protected Branch $secondaryBranch;

    protected Customer $customer;

    protected Product $product;

    protected User $admin;

    protected User $cashier;

    protected User $inventoryManager;

    /** @var array<string, Role> */
    protected array $roles = [];

    protected function setUpSellixWorld(): void
    {
        $this->company = Company::query()->create([
            'name' => 'Test Sellix Co',
            'slug' => 'test-sellix-' . Str::lower(Str::random(6)),
            'address' => '1 Test Street',
            'city' => 'Nairobi',
            'country' => 'Kenya',
            'phone' => '0700000000',
            'email' => 'test@sellix.local',
            'currency_code' => config('sellix.currency_code', 'KES'),
            'currency_symbol' => config('sellix.currency_symbol', 'KSh'),
            'date_format' => config('sellix.date_format', 'd/m/Y'),
            'timezone' => config('sellix.timezone', 'Africa/Nairobi'),
            'is_active' => true,
        ]);

        app()->instance('currentCompanyId', $this->company->id);

        $this->branch = Branch::query()->create([
            'company_id' => $this->company->id,
            'name' => 'MAIN',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->secondaryBranch = Branch::query()->create([
            'company_id' => $this->company->id,
            'name' => 'WAREHOUSE',
            'code' => 'WH1',
            'is_default' => false,
            'is_active' => true,
        ]);

        foreach (PermissionCatalog::permissions() as $name => $meta) {
            Permission::query()->create([
                'name' => $name,
                'display_name' => $meta['label'],
                'module' => $meta['module'],
            ]);
        }

        $permissionIds = Permission::query()->pluck('id', 'name');

        foreach (PermissionCatalog::roles() as $name => $meta) {
            $role = Role::query()->create([
                'company_id' => $this->company->id,
                'name' => $name,
                'display_name' => $meta['label'],
                'description' => $meta['description'],
                'is_system' => true,
            ]);

            $ids = [];
            foreach ($meta['permissions'] as $permissionName) {
                if (isset($permissionIds[$permissionName])) {
                    $ids[] = $permissionIds[$permissionName];
                }
            }
            $role->permissions()->sync($ids);
            $this->roles[$name] = $role;
        }

        $this->admin = $this->makeUser('Admin', 'admin@test.local', 'admin', PermissionCatalog::SUPER_ADMIN);
        $this->cashier = $this->makeUser('Cashier', 'cashier@test.local', 'cashier', PermissionCatalog::CASHIER);
        $this->inventoryManager = $this->makeUser(
            'Inventory',
            'inventory@test.local',
            'inventory',
            PermissionCatalog::INVENTORY_MANAGER
        );

        $this->customer = Customer::query()->create([
            'company_id' => $this->company->id,
            'type' => Customer::TYPE_WALK_IN,
            'name' => 'Walk-in Customer',
            'is_walk_in' => true,
            'is_active' => true,
            'loyalty_points' => 0,
        ]);

        $this->product = Product::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Widget',
            'sku' => 'TW-' . Str::upper(Str::random(5)),
            'purchase_price' => 50,
            'selling_price' => 100,
            'manage_stock' => true,
            'allow_negative_stock' => false,
            'is_active' => true,
            'for_sale' => true,
            'tax_inclusive' => true,
        ]);

        ProductBranchStock::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'product_variant_id' => 0,
            'quantity' => 100,
            'average_cost' => 50,
        ]);

        app(AccountingCatalog::class)->ensure($this->company->id);
        app(AccountingCatalog::class)->ensureOperationalAccounts($this->company->id);

        app(SettingsService::class)->set($this->company->id, 'loyalty_points_rate', '0.01', 'loyalty');

        $this->seedTestSubscription();

        app()->instance('currentBranchId', $this->branch->id);
    }

    protected function seedTestSubscription(): void
    {
        $plan = SubscriptionPlan::query()->firstOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'description' => 'Test plan',
                'price' => 0,
                'billing_period' => SubscriptionCatalog::PERIOD_ANNUALLY,
                'max_users' => null,
                'max_branches' => null,
                'features' => SubscriptionCatalog::allFeatureKeys(),
                'is_active' => true,
                'sort_order' => 99,
            ]
        );

        Subscription::query()->updateOrCreate(
            ['company_id' => $this->company->id],
            [
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionCatalog::STATUS_ACTIVE,
                'starts_at' => now()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
            ]
        );

        $this->company->unsetRelation('subscription');
    }

    protected function makeUser(string $name, string $email, string $username, string $roleName): User
    {
        return User::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'role_id' => $this->roles[$roleName]->id,
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    protected function actingAsAdmin()
    {
        return $this->actingAs($this->admin->fresh(['role.permissions']))
            ->withSession(['current_branch_id' => $this->branch->id]);
    }

    protected function actingAsCashier()
    {
        return $this->actingAs($this->cashier->fresh(['role.permissions']))
            ->withSession(['current_branch_id' => $this->branch->id]);
    }

    protected function actingAsInventoryManager()
    {
        return $this->actingAs($this->inventoryManager->fresh(['role.permissions']))
            ->withSession(['current_branch_id' => $this->branch->id]);
    }

    protected function stockQty(?int $branchId = null, ?int $productId = null): float
    {
        $row = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId ?: $this->branch->id)
            ->where('product_id', $productId ?: $this->product->id)
            ->first();

        return $row ? (float) $row->quantity : 0.0;
    }
}
