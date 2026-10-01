<?php

namespace Tests\Feature;

use App\Models\Colour;
use App\Models\Folding;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Support\PermissionCatalog;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class FoldingAndBranchAccessTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
    }

    public function test_folding_increases_finished_stock_without_consuming_materials(): void
    {
        $colour = Colour::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Black',
            'is_active' => true,
        ]);

        $before = $this->stockQty();

        $response = $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => now()->toDateString(),
            'product_id' => $this->product->id,
            'colour_id' => $colour->id,
            'quantity' => 20,
            'employee_name' => 'Production Staff',
        ]);

        $response->assertRedirect(route('folding.index'));
        $this->assertSame(1, Folding::query()->count());
        $variant = ProductVariant::query()->where('colour_id', $colour->id)->first();
        $this->assertNotNull($variant);

        $finished = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('product_id', $this->product->id)
            ->where('product_variant_id', $variant->id)
            ->first();
        $this->assertEquals(20, (float) $finished->quantity);
        $this->assertEquals($before, $this->stockQty());
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::ISSUE)->count());
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::FOLDING)->count());
    }

    public function test_decimal_kilogram_folding_keeps_the_fraction(): void
    {
        $unit = Unit::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Kilogram',
            'short_name' => 'KG',
            'multiplier' => 1,
            'is_active' => true,
        ]);
        $nails = Product::query()->create([
            'company_id' => $this->company->id,
            'unit_id' => $unit->id,
            'name' => 'Nails',
            'sku' => 'NAILS-1',
            'purchase_price' => 10,
            'selling_price' => 15,
            'manage_stock' => true,
            'allow_negative_stock' => false,
            'is_active' => true,
            'for_sale' => true,
        ]);

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => now()->toDateString(),
            'product_id' => $nails->id,
            'quantity' => 318.5,
            'employee_name' => 'Store',
        ])->assertRedirect(route('folding.index'));

        $row = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('product_id', $nails->id)
            ->where('product_variant_id', 0)
            ->first();

        $this->assertEquals(318.5, (float) $row->quantity);
    }

    public function test_sale_is_rejected_when_stock_is_insufficient_and_stock_is_unchanged(): void
    {
        $before = $this->stockQty();

        $response = $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 500],
            ],
            'payment_amount' => 50000,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Insufficient stock for Test Widget. Available: 100. Requested: 500.']);
        $this->assertEquals($before, $this->stockQty());
    }

    public function test_colour_is_required_when_the_product_has_variants(): void
    {
        $colour = Colour::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Maroon Red',
            'is_active' => true,
        ]);
        ProductVariant::query()->create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'colour_id' => $colour->id,
            'color' => $colour->name,
            'is_active' => true,
        ]);

        $response = $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'payment_amount' => 100,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_switch_only_among_assigned_branches(): void
    {
        $staff = $this->makeUser('Folder', 'folder@test.local', 'folder', PermissionCatalog::PRODUCTION_STAFF);
        $staff->branches()->sync([$this->branch->id, $this->secondaryBranch->id]);

        $this->actingAs($staff->fresh(['role.permissions']))
            ->withSession(['current_branch_id' => $this->branch->id])
            ->post(route('branch.switch'), ['branch_id' => $this->secondaryBranch->id])
            ->assertRedirect();

        $other = \App\Models\Branch::query()->create([
            'company_id' => $this->company->id,
            'name' => 'SHOP',
            'code' => 'SHOP',
            'is_active' => true,
        ]);

        $this->actingAs($staff->fresh(['role.permissions']))
            ->withSession(['current_branch_id' => $this->branch->id])
            ->post(route('branch.switch'), ['branch_id' => $other->id])
            ->assertStatus(403);
    }
}
