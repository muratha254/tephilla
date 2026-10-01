<?php

namespace Tests\Feature;

use App\Models\Colour;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use App\Services\CompanyProvisioner;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class RoofingCatalogTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
        app(CompanyProvisioner::class)->ensureRoofingCatalog($this->company);
    }

    public function test_edit_item_loads_the_opening_stock_already_saved(): void
    {
        $sheetType = ProductCategory::query()->where('name', 'Roofing Sheet')->firstOrFail();
        $tax = Tax::query()->create([
            'company_id' => $this->company->id,
            'name' => 'VAT',
            'rate' => 16,
            'is_active' => true,
        ]);
        $pcs = Unit::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Piece',
            'short_name' => 'PCS',
            'multiplier' => 1,
            'is_active' => true,
        ]);
        $black = Colour::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Black',
            'is_active' => true,
        ]);

        $this->actingAsAdmin()->post(route('products.store'), array_merge(
            $this->productPayload($sheetType->id, $pcs->id, $tax->id, 'Gauge Sheet', [$black->id]),
            ['opening_stock' => 18, 'colour_id' => $black->id]
        ))->assertRedirect(route('products.index'));

        $sheet = Product::query()->where('name', 'Gauge Sheet')->firstOrFail();
        $variant = ProductVariant::query()->where('product_id', $sheet->id)->where('colour_id', $black->id)->firstOrFail();
        $this->assertStock($sheet, 18, null, $variant->id);

        $this->actingAsAdmin()->get(route('products.edit', $sheet))
            ->assertOk()
            ->assertSee('value="18"', false);

        $this->actingAsAdmin()->put(route('products.update', $sheet), array_merge(
            $this->productPayload($sheetType->id, $pcs->id, $tax->id, 'Gauge Sheet', [$black->id]),
            ['opening_stock' => 18, 'colour_id' => $black->id]
        ))->assertRedirect(route('products.index'));
        $this->assertStock($sheet, 18, null, $variant->id);

        $this->actingAsAdmin()->put(route('products.update', $sheet), array_merge(
            $this->productPayload($sheetType->id, $pcs->id, $tax->id, 'Gauge Sheet', [$black->id]),
            ['opening_stock' => 22, 'colour_id' => $black->id]
        ))->assertRedirect(route('products.index'));
        $this->assertStock($sheet, 22, null, $variant->id);
    }

    public function test_roofing_colours_stay_independent_through_stock_sales_and_folding(): void
    {
        $sheetType = ProductCategory::query()->where('name', 'Roofing Sheet')->firstOrFail();
        $sheetGroup = ProductCategory::query()->where('name', 'Roofing Sheets')->firstOrFail();
        $tileType = ProductCategory::query()->where('name', 'Roofing Tile')->firstOrFail();
        $componentType = ProductCategory::query()->where('name', 'Roofing Component')->firstOrFail();
        $this->assertSame($sheetGroup->id, (int) $sheetType->parent_id);

        $this->actingAsAdmin()->get(route('products.create'))
            ->assertOk()
            ->assertSee('Product type')
            ->assertSee('Roofing Sheet')
            ->assertSee('Colour')
            ->assertSee('name="colour_id"', false);

        $tax = Tax::query()->create([
            'company_id' => $this->company->id,
            'name' => 'VAT',
            'rate' => 16,
            'is_active' => true,
        ]);
        $pcs = Unit::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Piece',
            'short_name' => 'PCS',
            'multiplier' => 1,
            'is_active' => true,
        ]);
        $kg = Unit::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Kilogram',
            'short_name' => 'KG',
            'multiplier' => 1,
            'is_active' => true,
        ]);
        $black = Colour::query()->create(['company_id' => $this->company->id, 'name' => 'Black', 'is_active' => true]);
        $coffee = Colour::query()->create(['company_id' => $this->company->id, 'name' => 'Coffee Brown', 'is_active' => true]);

        $this->actingAsAdmin()->post(route('products.store'), [
            'name' => 'Classic Roofing Sheet',
            'category_id' => $sheetGroup->id,
            'unit_id' => $pcs->id,
            'tax_id' => $tax->id,
            'purchase_price' => 1000,
            'selling_price' => 1500,
            'manage_stock' => 1,
            'for_sale' => 1,
            'tax_inclusive' => 1,
            'is_active' => 1,
            'colour_ids' => [$black->id],
            'branch_id' => $this->branch->id,
        ])->assertSessionHasErrors('category_id');

        $this->actingAsAdmin()->post(route('products.store'), $this->productPayload($sheetType->id, $pcs->id, $tax->id, 'Classic Roofing Sheet', [$black->id, $coffee->id]))
            ->assertRedirect(route('products.index'));

        $sheet = Product::query()->where('name', 'Classic Roofing Sheet')->firstOrFail();
        $blackVariant = ProductVariant::query()->where('product_id', $sheet->id)->where('colour_id', $black->id)->firstOrFail();
        $coffeeVariant = ProductVariant::query()->where('product_id', $sheet->id)->where('colour_id', $coffee->id)->firstOrFail();
        $this->assertStock($sheet, 0, null, $blackVariant->id);
        $this->assertStock($sheet, 0, null, $coffeeVariant->id);
        $this->assertStock($sheet, 0);

        $supplier = Supplier::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Roofing Mill',
            'is_active' => true,
        ]);
        $this->receive($supplier, $sheet, $blackVariant->id, 100);
        $this->assertStock($sheet, 100, null, $blackVariant->id);
        $this->assertStock($sheet, 0, null, $coffeeVariant->id);

        $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'idempotency_key' => 'roof-black-sale',
            'items' => [[
                'product_id' => $sheet->id,
                'product_variant_id' => $blackVariant->id,
                'quantity' => 20,
            ]],
            'payment_amount' => 40000,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertStock($sheet, 80, null, $blackVariant->id);
        $this->assertStock($sheet, 0, null, $coffeeVariant->id);
        $this->assertStock($sheet, 0, $this->secondaryBranch->id, $blackVariant->id);

        $this->receive($supplier, $sheet, $coffeeVariant->id, 40);
        $this->assertStock($sheet, 80, null, $blackVariant->id);
        $this->assertStock($sheet, 40, null, $coffeeVariant->id);

        $this->actingAsAdmin()->post(route('products.store'), $this->productPayload($tileType->id, $pcs->id, $tax->id, 'Shingle Roofing Tile', [$black->id, $coffee->id]))
            ->assertRedirect(route('products.index'));
        $this->actingAsAdmin()->post(route('products.store'), $this->productPayload($tileType->id, $pcs->id, $tax->id, 'Classic Roofing Tile', [$black->id]))
            ->assertRedirect(route('products.index'));
        $this->actingAsAdmin()->post(route('products.store'), [
            'name' => 'Roofing Nails',
            'category_id' => $componentType->id,
            'unit_id' => $kg->id,
            'tax_id' => $tax->id,
            'purchase_price' => 200,
            'selling_price' => 280,
            'manage_stock' => 1,
            'for_sale' => 1,
            'tax_inclusive' => 1,
            'is_active' => 1,
            'opening_stock' => 12.5,
            'branch_id' => $this->branch->id,
        ])->assertRedirect(route('products.index'));

        $nails = Product::query()->where('name', 'Roofing Nails')->firstOrFail();
        $this->assertStock($nails, 12.5);
        $this->assertSame(0, ProductVariant::query()->where('product_id', $nails->id)->count());

        $this->actingAsAdmin()->get(route('reports.stock.stock', ['show' => 1, 'category_id' => $sheetGroup->id]))
            ->assertOk()
            ->assertSee('Classic Roofing Sheet')
            ->assertSee('Black')
            ->assertSee('Coffee Brown')
            ->assertSee('80.00')
            ->assertSee('40.00');

        $this->actingAsAdmin()->get(route('reports.stock.ledger', [
            'show' => 1,
            'product_id' => $sheet->id,
            'product_variant_id' => $blackVariant->id,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk()->assertSee('Classic Roofing Sheet Black');

        $this->actingAsAdmin()->getJson(route('pos.catalog', ['category_id' => $sheetGroup->id]))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Classic Roofing Sheet']);

        $this->actingAsAdmin()->get(route('products.index', ['category_id' => $sheetGroup->id]))
            ->assertOk()
            ->assertSee('Classic Roofing Sheet');

        $this->actingAsCashier()->post(route('products.store'), $this->productPayload($sheetType->id, $pcs->id, $tax->id, 'Blocked Sheet', [$black->id]))
            ->assertForbidden();

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => now()->toDateString(),
            'product_id' => $sheet->id,
            'colour_id' => $black->id,
            'quantity' => 5,
            'effect' => 'produce',
            'notes' => 'folded 5 pcs',
            'idempotency_key' => 'roof-fold-black',
        ])->assertRedirect(route('folding.index'));

        $this->assertStock($sheet, 85, null, $blackVariant->id);
        $this->assertStock($sheet, 40, null, $coffeeVariant->id);
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::ISSUE)->count());
    }

    private function productPayload(int $typeId, int $unitId, int $taxId, string $name, array $colourIds): array
    {
        return [
            'name' => $name,
            'category_id' => $typeId,
            'unit_id' => $unitId,
            'tax_id' => $taxId,
            'purchase_price' => 1000,
            'selling_price' => 1500,
            'manage_stock' => 1,
            'for_sale' => 1,
            'tax_inclusive' => 1,
            'is_active' => 1,
            'colour_ids' => $colourIds,
            'branch_id' => $this->branch->id,
        ];
    }

    private function receive(Supplier $supplier, Product $product, int $variantId, float $quantity): void
    {
        $this->actingAsAdmin()->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'status' => 'pending',
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_cost' => 1000,
            ]],
        ])->assertRedirect();

        $purchase = PurchaseOrder::query()->latest('id')->first();
        $this->actingAsAdmin()->put(route('purchases.status.update', $purchase), ['status' => 'received'])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    private function assertStock(Product $product, float $expected, ?int $branchId = null, int $variantId = 0): void
    {
        $row = \App\Models\ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId ?: $this->branch->id)
            ->where('product_variant_id', $variantId)
            ->first();

        $this->assertEquals($expected, $row ? (float) $row->quantity : 0.0);
    }
}
