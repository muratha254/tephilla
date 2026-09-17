<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Services\StockAlertService;
use Illuminate\Support\Str;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class OutOfStockNotificationTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
    }

    public function test_header_shows_out_of_stock_badge_for_active_branch(): void
    {
        $product = Product::query()->create([
            'company_id' => $this->company->id,
            'name' => 'ZERO-STOCK-ITEM',
            'sku' => 'ZS-' . Str::upper(Str::random(4)),
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
            'product_id' => $product->id,
            'product_variant_id' => 0,
            'quantity' => 0,
            'average_cost' => 10,
        ]);

        $this->actingAsAdmin()
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('sx-notify-badge', false)
            ->assertSee('ZERO-STOCK-ITEM')
            ->assertSee('out of stock');
    }

    public function test_out_of_stock_count_is_branch_scoped(): void
    {
        $product = Product::query()->create([
            'company_id' => $this->company->id,
            'name' => 'BRANCH-B-ZERO',
            'sku' => 'ZB-' . Str::upper(Str::random(4)),
            'purchase_price' => 10,
            'selling_price' => 20,
            'manage_stock' => true,
            'is_active' => true,
            'for_sale' => true,
            'manage_stock' => true,
            'tax_inclusive' => true,
        ]);

        ProductBranchStock::query()->withoutGlobalScope('branch')->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->secondaryBranch->id,
            'product_id' => $product->id,
            'product_variant_id' => 0,
            'quantity' => 0,
            'average_cost' => 10,
        ]);

        $alerts = app(StockAlertService::class);

        $this->assertSame(0, $alerts->outOfStockCount($this->branch->id));
        $this->assertSame(1, $alerts->outOfStockCount($this->secondaryBranch->id));
    }

    public function test_stock_alert_page_lists_zero_qty_items(): void
    {
        $product = Product::query()->create([
            'company_id' => $this->company->id,
            'name' => 'ALERT-ZERO-ITEM',
            'sku' => 'AZ-' . Str::upper(Str::random(4)),
            'purchase_price' => 10,
            'selling_price' => 20,
            'manage_stock' => true,
            'reorder_level' => 0,
            'is_active' => true,
            'for_sale' => true,
            'tax_inclusive' => true,
        ]);

        ProductBranchStock::query()->withoutGlobalScope('branch')->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'product_variant_id' => 0,
            'quantity' => 0,
            'average_cost' => 10,
        ]);

        $this->actingAsAdmin()
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('stock.alert'))
            ->assertOk()
            ->assertSee('ALERT-ZERO-ITEM')
            ->assertSee('Out of stock');
    }
}
