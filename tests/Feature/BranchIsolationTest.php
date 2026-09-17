<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\Sale;
use App\Services\InventoryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use CreatesSellixWorld;

    protected Product $productA;

    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();

        $this->productA = Product::query()->create([
            'company_id' => $this->company->id,
            'name' => 'TEST-BRANCH-A-PRODUCT',
            'sku' => 'TBA-' . Str::upper(Str::random(4)),
            'purchase_price' => 10,
            'selling_price' => 20,
            'manage_stock' => true,
            'allow_negative_stock' => false,
            'is_active' => true,
            'for_sale' => true,
            'tax_inclusive' => true,
        ]);

        ProductBranchStock::query()->withoutGlobalScope('branch')->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'product_id' => $this->productA->id,
            'product_variant_id' => 0,
            'quantity' => 10,
            'average_cost' => 10,
        ]);

        $this->productB = Product::query()->create([
            'company_id' => $this->company->id,
            'name' => 'TEST-BRANCH-B-PRODUCT',
            'sku' => 'TBB-' . Str::upper(Str::random(4)),
            'purchase_price' => 10,
            'selling_price' => 20,
            'manage_stock' => true,
            'allow_negative_stock' => false,
            'is_active' => true,
            'for_sale' => true,
            'tax_inclusive' => true,
        ]);

        ProductBranchStock::query()->withoutGlobalScope('branch')->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->secondaryBranch->id,
            'product_id' => $this->productB->id,
            'product_variant_id' => 0,
            'quantity' => 20,
            'average_cost' => 10,
        ]);
    }

    public function test_items_list_only_shows_products_available_at_active_branch(): void
    {
        $this->actingAsAdmin()
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('TEST-BRANCH-A-PRODUCT')
            ->assertDontSee('TEST-BRANCH-B-PRODUCT');

        $this->actingAsAdmin()
            ->withSession(['current_branch_id' => $this->secondaryBranch->id])
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('TEST-BRANCH-B-PRODUCT')
            ->assertDontSee('TEST-BRANCH-A-PRODUCT');
    }

    public function test_items_list_shows_active_branch_stock_only(): void
    {
        ProductBranchStock::query()->withoutGlobalScope('branch')->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->secondaryBranch->id,
            'product_id' => $this->productA->id,
            'product_variant_id' => 0,
            'quantity' => 20,
            'average_cost' => 10,
        ]);

        $this->actingAsAdmin()
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('TEST-BRANCH-A-PRODUCT');

        app()->instance('currentCompanyId', $this->company->id);
        app()->instance('currentBranchId', $this->branch->id);
        $this->assertSame(10.0, $this->productA->fresh()->quantityAtBranch($this->branch->id));

        app()->instance('currentBranchId', $this->secondaryBranch->id);
        $this->assertSame(20.0, $this->productA->fresh()->quantityAtBranch($this->secondaryBranch->id));
        $this->assertNotSame(30.0, $this->productA->fresh()->quantityAtBranch($this->branch->id));
    }

    public function test_pos_catalog_is_branch_scoped(): void
    {
        $this->actingAsAdmin()
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('pos.catalog'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'TEST-BRANCH-A-PRODUCT'])
            ->assertJsonMissing(['name' => 'TEST-BRANCH-B-PRODUCT']);

        $this->actingAsAdmin()
            ->withSession(['current_branch_id' => $this->secondaryBranch->id])
            ->get(route('pos.catalog'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'TEST-BRANCH-B-PRODUCT'])
            ->assertJsonMissing(['name' => 'TEST-BRANCH-A-PRODUCT']);
    }

    public function test_sale_deducts_only_active_branch_stock(): void
    {
        app()->instance('currentCompanyId', $this->company->id);
        app()->instance('currentBranchId', $this->branch->id);

        app(InventoryService::class)->apply([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'product_id' => $this->productA->id,
            'type' => 'sale',
            'quantity_out' => 3,
            'unit_cost' => 10,
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame(7.0, $this->stockQty($this->branch->id, $this->productA->id));
        $this->assertSame(20.0, $this->stockQty($this->secondaryBranch->id, $this->productB->id));
        $this->assertSame(0.0, $this->stockQty($this->secondaryBranch->id, $this->productA->id));
    }

    public function test_purchase_style_receive_increases_only_target_branch_stock(): void
    {
        app()->instance('currentCompanyId', $this->company->id);

        app(InventoryService::class)->apply([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'product_id' => $this->productA->id,
            'type' => 'purchase',
            'quantity_in' => 5,
            'unit_cost' => 10,
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame(15.0, $this->stockQty($this->branch->id, $this->productA->id));
        $this->assertSame(0.0, $this->stockQty($this->secondaryBranch->id, $this->productA->id));
        $this->assertSame(20.0, $this->stockQty($this->secondaryBranch->id, $this->productB->id));
    }

    public function test_transfer_moves_stock_between_branches_only(): void
    {
        app()->instance('currentCompanyId', $this->company->id);

        app(InventoryService::class)->apply([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'product_id' => $this->productA->id,
            'type' => 'transfer_out',
            'quantity_out' => 4,
            'unit_cost' => 10,
            'user_id' => $this->admin->id,
        ]);

        app(InventoryService::class)->apply([
            'company_id' => $this->company->id,
            'branch_id' => $this->secondaryBranch->id,
            'product_id' => $this->productA->id,
            'type' => 'transfer_in',
            'quantity_in' => 4,
            'unit_cost' => 10,
            'user_id' => $this->admin->id,
        ]);

        $this->assertSame(6.0, $this->stockQty($this->branch->id, $this->productA->id));
        $this->assertSame(4.0, $this->stockQty($this->secondaryBranch->id, $this->productA->id));
        $this->assertSame(20.0, $this->stockQty($this->secondaryBranch->id, $this->productB->id));
    }

    public function test_branch_user_cannot_open_other_branch_product(): void
    {
        $this->actingAsCashier()
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('products.show', $this->productB))
            ->assertForbidden();
    }

    public function test_super_admin_switch_changes_items_list(): void
    {
        $this->actingAsAdmin()
            ->withSession(['current_branch_id' => $this->branch->id])
            ->post(route('branch.switch'), ['branch_id' => $this->secondaryBranch->id])
            ->assertRedirect();

        $this->withSession(['current_branch_id' => $this->secondaryBranch->id])
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('TEST-BRANCH-B-PRODUCT')
            ->assertDontSee('TEST-BRANCH-A-PRODUCT');
    }

    public function test_non_admin_cannot_see_other_branch_sales_or_stock(): void
    {
        $otherCustomer = Customer::query()->withoutGlobalScope('branch')->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->secondaryBranch->id,
            'type' => Customer::TYPE_REGULAR,
            'name' => 'Branch B Customer',
            'is_walk_in' => false,
            'is_active' => true,
        ]);

        $otherSale = Sale::query()->withoutGlobalScope('branch')->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->secondaryBranch->id,
            'customer_id' => $otherCustomer->id,
            'user_id' => $this->admin->id,
            'number' => 'SL-B-001',
            'status' => Sale::STATUS_COMPLETED,
            'sale_date' => Carbon::today()->toDateString(),
            'subtotal' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100,
            'paid_amount' => 100,
            'balance' => 0,
        ]);

        $this->actingAsCashier();
        app()->instance('currentCompanyId', $this->company->id);
        app()->instance('currentBranchId', $this->branch->id);

        $this->assertNull(Sale::query()->find($otherSale->id));
        $this->assertNull(Customer::query()->find($otherCustomer->id));
        $this->assertNull(
            ProductBranchStock::query()
                ->where('branch_id', $this->secondaryBranch->id)
                ->where('product_id', $this->productB->id)
                ->first()
        );
    }

    public function test_cashier_cannot_switch_to_another_branch(): void
    {
        $this->actingAsCashier()
            ->post(route('branch.switch'), ['branch_id' => $this->secondaryBranch->id])
            ->assertForbidden();
    }
}
