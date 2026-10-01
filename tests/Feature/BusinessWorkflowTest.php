<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Colour;
use App\Models\Customer;
use App\Models\Folding;
use App\Models\GoodsReceiptItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Services\CustomerPaymentService;
use App\Services\InventoryService;
use App\Support\PermissionCatalog;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class BusinessWorkflowTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
    }

    public function test_guest_cannot_open_the_dashboard_and_bad_login_is_rejected(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => 'admin',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_production_staff_cannot_open_sales_or_user_admin_by_url(): void
    {
        $staff = $this->makeUser('Folder', 'folder2@test.local', 'folder2', PermissionCatalog::PRODUCTION_STAFF);

        $this->actingAs($staff->fresh(['role.permissions']))
            ->get(route('sales.index'))
            ->assertForbidden();

        $this->actingAs($staff->fresh(['role.permissions']))
            ->postJson(route('pos.order'), [
                'customer_id' => $this->customer->id,
                'sale_date' => now()->toDateString(),
                'document_type' => 'pos',
                'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
                'payment_amount' => 100,
                'payment_method' => 'cash',
            ])
            ->assertForbidden();

        $this->actingAsCashier()
            ->post(route('folding.store'), [
                'folded_on' => now()->toDateString(),
                'product_id' => $this->product->id,
                'quantity' => 1,
                'employee_name' => 'Nope',
            ])
            ->assertForbidden();

        $this->assertEquals(100.0, $this->stockQty());
    }

    public function test_stock_chain_matches_movement_ledger(): void
    {
        $ridge = $this->makeProduct('Ridge', 'RIDGE-1', 800, 1500);
        $this->opening($ridge, 100);

        $this->adjust($ridge, 'addition', 50);
        $this->assertStock($ridge, 150);

        $sale = $this->sell($ridge, 20, 20 * 1500);
        $this->assertEquals(30000.0, (float) $sale->total);
        $this->assertEquals(30000.0, (float) $sale->items->sum('line_total'));
        $this->assertEquals(30000.0, (float) $sale->paid_amount);
        $this->assertEquals(0.0, (float) $sale->balance);
        $this->assertStock($ridge, 130);

        $this->adjust($ridge, 'addition', 10);
        $this->adjust($ridge, 'reduction', 5);
        $this->assertStock($ridge, 135);
        $this->assertEquals(135.0, $this->movementBalance($ridge));
    }

    public function test_inventory_adjust_opens_for_a_colour_and_changes_only_that_colour(): void
    {
        $sheet = $this->makeProduct('Flat Sheet Adjust', 'FLAT-ADJ', 100, 150);
        $black = Colour::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Black',
            'is_active' => true,
        ]);
        $coffee = Colour::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Coffee Brown',
            'is_active' => true,
        ]);
        $blackVariant = ProductVariant::query()->create([
            'company_id' => $this->company->id,
            'product_id' => $sheet->id,
            'colour_id' => $black->id,
            'color' => 'Black',
            'is_active' => true,
        ]);
        $coffeeVariant = ProductVariant::query()->create([
            'company_id' => $this->company->id,
            'product_id' => $sheet->id,
            'colour_id' => $coffee->id,
            'color' => 'Coffee Brown',
            'is_active' => true,
        ]);
        $sheet->update(['has_variants' => true]);

        $html = $this->actingAsAdmin()->get(route('stock.manager'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/data-variants="(\[.*?' . preg_quote($blackVariant->id, '/') . '.*?\])"/',
            $html
        );
        preg_match('/data-url="' . preg_quote(route('stock.adjust', $sheet), '/') . '" data-variants="([^"]+)"/', $html, $matches);
        $variants = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES), true);
        $this->assertIsArray($variants);
        $this->assertEqualsCanonicalizing(
            ['Black', 'Coffee Brown'],
            array_column($variants, 'name')
        );

        $this->actingAsAdmin()
            ->from(route('stock.manager'))
            ->post(route('stock.adjust', $sheet), [
                'branch_id' => $this->branch->id,
                'occurred_at' => now()->toDateString(),
                'status' => 'addition',
                'control_account' => 'migration_control',
                'quantity' => 12,
                'product_variant_id' => $blackVariant->id,
                'notes' => 'Black receipt',
            ])
            ->assertRedirect(route('stock.manager'))
            ->assertSessionHas('success', 'Stock adjusted.');

        $this->assertStock($sheet, 12, null, $blackVariant->id);
        $this->assertStock($sheet, 0, null, $coffeeVariant->id);

        $this->actingAsAdmin()
            ->from(route('stock.manager'))
            ->post(route('stock.adjust', $sheet), [
                'branch_id' => $this->branch->id,
                'occurred_at' => now()->toDateString(),
                'status' => 'reduction',
                'control_account' => 'migration_control',
                'quantity' => 5,
            ])
            ->assertRedirect(route('stock.manager'))
            ->assertSessionHasErrors('product_variant_id');

        $this->assertStock($sheet, 12, null, $blackVariant->id);
    }

    public function test_insufficient_sale_and_transfer_do_not_change_stock(): void
    {
        $ridge = $this->makeProduct('Valley', 'VALLEY-1', 500, 900);
        $this->opening($ridge, 10);

        $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [['product_id' => $ridge->id, 'quantity' => 15]],
            'payment_amount' => 15 * 900,
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertStock($ridge, 10);
        $this->assertSame(0, Sale::query()->whereHas('items', fn ($q) => $q->where('product_id', $ridge->id))->count());

        $this->actingAsInventoryManager()
            ->from(route('stock.transfers.create'))
            ->post(route('stock.transfers.store'), [
                'from_branch_id' => $this->branch->id,
                'to_branch_id' => $this->secondaryBranch->id,
                'transfer_date' => now()->toDateString(),
                'action' => 'complete',
                'items' => [['product_id' => $ridge->id, 'quantity' => 30]],
            ])
            ->assertRedirect(route('stock.transfers.create'))
            ->assertSessionHas('error');

        $this->assertStock($ridge, 10);
        $this->assertStock($ridge, 0, $this->secondaryBranch->id);
        $this->assertSame(0, StockTransfer::query()->count());
    }

    public function test_transfer_moves_stock_once_and_rejects_same_branch(): void
    {
        $box = $this->makeProduct('Bardge Box', 'BOX-1', 400, 700);
        $this->opening($box, 100);

        $this->actingAsAdmin()
            ->post(route('stock.transfers.store'), [
                'from_branch_id' => $this->branch->id,
                'to_branch_id' => $this->branch->id,
                'transfer_date' => now()->toDateString(),
                'action' => 'complete',
                'items' => [['product_id' => $box->id, 'quantity' => 10]],
            ])
            ->assertSessionHasErrors('to_branch_id');

        $response = $this->actingAsAdmin()->post(route('stock.transfers.store'), [
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $this->secondaryBranch->id,
            'transfer_date' => now()->toDateString(),
            'action' => 'complete',
            'items' => [['product_id' => $box->id, 'quantity' => 30]],
        ]);

        $transfer = StockTransfer::query()->latest('id')->first();
        $response->assertRedirect(route('stock.transfers.show', $transfer));
        $this->assertSame(StockTransfer::STATUS_COMPLETED, $transfer->status);
        $this->assertStock($box, 70);
        $this->assertStock($box, 30, $this->secondaryBranch->id);

        $this->actingAsAdmin()
            ->post(route('stock.transfers.complete', $transfer))
            ->assertSessionHas('error');

        $this->assertStock($box, 70);
        $this->assertStock($box, 30, $this->secondaryBranch->id);
        $this->assertEquals(70.0, $this->movementBalance($box));
        $this->assertEquals(30.0, $this->movementBalance($box, $this->secondaryBranch->id));
    }

    public function test_void_restores_stock_and_cannot_be_repeated(): void
    {
        $flash = $this->makeProduct('Side Flash', 'FLASH-1', 300, 600);
        $this->opening($flash, 40);
        $sale = $this->sell($flash, 5, 3000);
        $this->assertStock($flash, 35);

        $this->actingAsAdmin()->post(route('sales.void', $sale), [
            'invoice_date' => now()->toDateString(),
            'receipt_ref' => 'TEST-VOID',
            'want_refund' => '1',
            'void_reason' => 'Entered by mistake',
        ])->assertRedirect();

        $sale->refresh();
        $this->assertSame(Sale::STATUS_VOIDED, $sale->status);
        $this->assertStock($flash, 40);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::SALE_VOID)->where('product_id', $flash->id)->count());

        $this->actingAsAdmin()->post(route('sales.void', $sale), [
            'invoice_date' => now()->toDateString(),
            'receipt_ref' => 'TEST-VOID-2',
            'want_refund' => '1',
            'void_reason' => 'Again',
        ])->assertStatus(422);

        $this->assertStock($flash, 40);
    }

    public function test_partial_payments_reach_zero_and_overpayment_is_rejected(): void
    {
        $customer = Customer::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'type' => Customer::TYPE_REGULAR,
            'name' => 'Acme Roofing',
            'phone' => '0711000000',
            'is_walk_in' => false,
            'is_active' => true,
            'opening_balance' => 0,
            'credit_limit' => 0,
        ]);
        $ridge = $this->makeProduct('Ridge Credit', 'RIDGE-CR', 800, 1500);
        $this->opening($ridge, 20);

        $response = $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'invoice',
            'items' => [['product_id' => $ridge->id, 'quantity' => 4]],
            'payment_amount' => 2500,
            'payment_method' => 'cash',
        ]);
        $response->assertOk();

        $sale = Sale::query()->findOrFail($response->json('id'));
        $this->assertEquals(6000.0, (float) $sale->total);
        $this->assertEquals(2500.0, (float) $sale->paid_amount);
        $this->assertEquals(3500.0, (float) $sale->balance);
        $this->assertStock($ridge, 16);

        $service = app(CustomerPaymentService::class);
        $service->apply($customer->fresh(), 1000, Payment::METHOD_MPESA, now(), 'MPESA1');
        $sale->refresh();
        $this->assertEquals(3500.0, (float) $sale->paid_amount);
        $this->assertEquals(2500.0, (float) $sale->balance);

        $service->apply($customer->fresh(), 2500, Payment::METHOD_BANK_TRANSFER, now(), 'BANK1');
        $sale->refresh();
        $this->assertEquals(6000.0, (float) $sale->paid_amount);
        $this->assertEquals(0.0, (float) $sale->balance);
        $this->assertEquals(6000.0, (float) Payment::query()->where('payable_id', $sale->id)->where('payable_type', Sale::class)->sum('amount'));

        $this->expectException(\InvalidArgumentException::class);
        $service->apply($customer->fresh(), 1, Payment::METHOD_CASH, now());
    }

    public function test_folding_by_colour_and_decimal_nails_update_dashboard(): void
    {
        $ridge = $this->makeProduct('Ridge Fold', 'RIDGE-F', 100, 200);
        $black = Colour::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Black Test',
            'is_active' => true,
        ]);

        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => now()->toDateString(),
            'product_id' => $ridge->id,
            'colour_id' => $black->id,
            'quantity' => 12,
            'employee_name' => 'Asha',
        ])->assertRedirect(route('folding.index'));

        $variantStock = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('product_id', $ridge->id)
            ->where('product_variant_id', '>', 0)
            ->first();
        $this->assertEquals(12.0, (float) $variantStock->quantity);

        $nails = $this->makeProduct('Nails', 'NAILS-1', 10, 18);
        $this->actingAsAdmin()->post(route('folding.store'), [
            'folded_on' => now()->toDateString(),
            'product_id' => $nails->id,
            'quantity' => 318.5,
            'employee_name' => 'Store',
        ])->assertRedirect(route('folding.index'));
        $this->assertStock($nails, 318.5);

        $this->actingAsAdmin()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('330.5', false);

        $this->actingAsAdmin()
            ->get(route('reports.stock.ledger', ['show' => 1, 'product_id' => $nails->id]))
            ->assertOk()
            ->assertSee('318.5', false);
    }

    public function test_receiving_and_returning_a_colour_keeps_variant_stock(): void
    {
        $supplier = Supplier::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Test Sheets Ltd',
            'is_active' => true,
        ]);
        $ridge = $this->makeProduct('Ridge Receive', 'RIDGE-RCV', 400, 700);
        $colour = Colour::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Maroon Red',
            'is_active' => true,
        ]);
        $variant = ProductVariant::query()->create([
            'company_id' => $this->company->id,
            'product_id' => $ridge->id,
            'colour_id' => $colour->id,
            'color' => 'Maroon Red',
            'sku' => 'RIDGE-RCV-MR',
            'is_active' => true,
        ]);
        $ridge->update(['has_variants' => true]);

        $this->actingAsAdmin()->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'status' => 'pending',
            'items' => [[
                'product_id' => $ridge->id,
                'product_variant_id' => $variant->id,
                'quantity' => 25,
                'unit_cost' => 400,
            ]],
        ])->assertRedirect();

        $this->assertStock($ridge, 0, null, $variant->id);

        $purchase = PurchaseOrder::query()->latest('id')->first();
        $this->actingAsAdmin()
            ->from(route('purchases.show', $purchase))
            ->put(route('purchases.status.update', $purchase), ['status' => 'received'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertStock($ridge, 25, null, $variant->id);
        $this->assertStock($ridge, 0);
        $receiptItem = GoodsReceiptItem::query()->where('product_id', $ridge->id)->first();
        $this->assertSame($variant->id, (int) $receiptItem->product_variant_id);

        $this->actingAsAdmin()->post(route('purchases.returns.store', $purchase), [
            'return_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $ridge->id,
                'product_variant_id' => $variant->id,
                'quantity' => 5,
            ]],
        ])->assertRedirect(route('purchase-returns.index'));

        $this->assertStock($ridge, 20, null, $variant->id);
        $this->assertStock($ridge, 0);
        $this->assertEquals(20.0, $this->movementBalance($ridge, null, $variant->id));

        $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'idempotency_key' => 'colour-sale-once',
            'items' => [[
                'product_id' => $ridge->id,
                'product_variant_id' => $variant->id,
                'quantity' => 5,
            ]],
            'payment_amount' => 3500,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertStock($ridge, 15, null, $variant->id);
        $this->assertStock($ridge, 0);
    }

    public function test_pos_catalog_exposes_colours_and_sale_deducts_that_colour(): void
    {
        $ridge = $this->makeProduct('Valley POS', 'VALLEY-POS', 500, 900);
        $colour = Colour::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Black Red',
            'is_active' => true,
        ]);
        $variant = ProductVariant::query()->create([
            'company_id' => $this->company->id,
            'product_id' => $ridge->id,
            'colour_id' => $colour->id,
            'color' => 'Black Red',
            'sku' => 'VALLEY-POS-BR',
            'is_active' => true,
        ]);
        $ridge->update(['has_variants' => true]);
        app(InventoryService::class)->apply([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'product_id' => $ridge->id,
            'product_variant_id' => $variant->id,
            'type' => StockMovement::OPENING,
            'quantity_in' => 8,
            'unit_cost' => 500,
            'user_id' => $this->admin->id,
            'occurred_at' => now(),
        ]);

        $catalog = $this->actingAsAdmin()->getJson(route('pos.catalog'));
        $catalog->assertOk();
        $row = collect($catalog->json('products'))->firstWhere('id', $ridge->id);
        $this->assertNotNull($row);
        $this->assertSame('Black Red', $row['variants'][0]['name']);
        $this->assertEquals(8.0, (float) $row['variants'][0]['qty']);

        $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [[
                'product_id' => $ridge->id,
                'product_variant_id' => $variant->id,
                'quantity' => 3,
            ]],
            'payment_amount' => 2700,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertStock($ridge, 5, null, $variant->id);
        $this->assertStock($ridge, 0);
    }

    public function test_repeated_sale_folding_and_transfer_do_not_post_twice(): void
    {
        $ridge = $this->makeProduct('Ridge Repeat', 'RIDGE-REP', 100, 200);
        $this->opening($ridge, 10);

        $salePayload = [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'idempotency_key' => 'sale-repeat-1',
            'items' => [['product_id' => $ridge->id, 'quantity' => 4]],
            'payment_amount' => 800,
            'payment_method' => 'cash',
        ];
        $first = $this->actingAsAdmin()->postJson(route('pos.order'), $salePayload);
        $second = $this->actingAsAdmin()->postJson(route('pos.order'), $salePayload);
        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::POS_SALE)->where('product_id', $ridge->id)->count());
        $this->assertStock($ridge, 6);

        $folding = [
            'folded_on' => now()->toDateString(),
            'product_id' => $ridge->id,
            'quantity' => 3,
            'employee_name' => 'Asha',
            'idempotency_key' => 'fold-repeat-1',
        ];
        $this->actingAsAdmin()->post(route('folding.store'), $folding)->assertRedirect(route('folding.index'));
        $this->actingAsAdmin()->post(route('folding.store'), $folding)->assertRedirect(route('folding.index'));
        $this->assertSame(1, Folding::query()->count());
        $this->assertSame(1, StockMovement::query()->where('type', StockMovement::FOLDING)->where('product_id', $ridge->id)->count());
        $this->assertStock($ridge, 9);
        $this->assertSame(1, AuditLog::query()->where('module', 'folding')->where('action', 'create')->count());

        $transfer = [
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $this->secondaryBranch->id,
            'transfer_date' => now()->toDateString(),
            'action' => 'complete',
            'idempotency_key' => 'xfer-repeat-1',
            'items' => [['product_id' => $ridge->id, 'quantity' => 3]],
        ];
        $this->actingAsAdmin()->post(route('stock.transfers.store'), $transfer)->assertRedirect();
        $this->actingAsAdmin()->post(route('stock.transfers.store'), $transfer)->assertRedirect();
        $this->assertSame(1, StockTransfer::query()->count());
        $this->assertStock($ridge, 6);
        $this->assertStock($ridge, 3, $this->secondaryBranch->id);
        $this->assertSame(1, StockMovement::query()->withoutGlobalScope('branch')->where('type', StockMovement::TRANSFER_OUT)->where('product_id', $ridge->id)->count());
        $this->assertSame(1, StockMovement::query()->withoutGlobalScope('branch')->where('type', StockMovement::TRANSFER_IN)->where('product_id', $ridge->id)->count());
    }

    public function test_transfer_cannot_exceed_source_stock_even_for_an_admin(): void
    {
        $ridge = $this->makeProduct('Ridge Short', 'RIDGE-SHORT', 100, 200);
        $this->opening($ridge, 10);

        $this->actingAsAdmin()
            ->from(route('stock.transfers.create'))
            ->post(route('stock.transfers.store'), [
                'from_branch_id' => $this->branch->id,
                'to_branch_id' => $this->secondaryBranch->id,
                'transfer_date' => now()->toDateString(),
                'action' => 'complete',
                'idempotency_key' => 'xfer-short-1',
                'items' => [['product_id' => $ridge->id, 'quantity' => 15]],
            ])
            ->assertRedirect(route('stock.transfers.create'))
            ->assertSessionHas('error');

        $this->assertStock($ridge, 10);
        $this->assertStock($ridge, 0, $this->secondaryBranch->id);
        $this->assertSame(0, StockTransfer::query()->count());
    }

    public function test_opening_stock_excel_imports_all_rows_or_none(): void
    {
        $unit = Unit::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Piece',
            'short_name' => 'PCS',
            'is_active' => true,
        ]);
        $ridge = $this->makeProduct('Ridge Import', 'RIDGE-IMP', 100, 200);
        $nails = $this->makeProduct('Nails Import', 'NAILS-IMP', 10, 18);
        $ridge->update(['unit_id' => $unit->id]);
        $nails->update(['unit_id' => $unit->id]);

        $bad = $this->spreadsheetUpload([
            ['product', 'colour', 'quantity', 'unit', 'branch'],
            ['Ridge Import', '', 20, 'PCS', 'MAIN'],
            ['Missing Item', '', 5, 'PCS', 'MAIN'],
        ]);
        $this->actingAsAdmin()->post(route('stock.opening.store'), ['file' => $bad])
            ->assertRedirect(route('stock.opening'));
        $this->assertStock($ridge, 0);
        $this->assertSame(0, StockMovement::query()->where('type', StockMovement::OPENING)->count());

        $good = $this->spreadsheetUpload([
            ['product', 'colour', 'quantity', 'unit', 'branch'],
            ['Ridge Import', '', 20, 'PCS', 'MAIN'],
            ['Nails Import', '', 318.5, 'PCS', 'MAIN'],
        ]);
        $this->actingAsAdmin()->post(route('stock.opening.store'), ['file' => $good])
            ->assertRedirect(route('stock.opening'))
            ->assertSessionHas('success');
        $this->assertStock($ridge, 20);
        $this->assertStock($nails, 318.5);
    }

    public function test_old_colour_register_keeps_each_colour_and_does_not_double_on_repeat(): void
    {
        $book = new Spreadsheet();
        $black = $book->getActiveSheet();
        $black->setTitle('BLACK');
        $black->fromArray([
            ['RIDGES'],
            ['DATE', 'O.S', 'SALES', 'C.S', 'NARRATION'],
            [null, 0, null, 26, 'folded 26 pcs'],
            ['10/6/2024', 26, 3, 23, 'mbugua'],
        ], null, 'A1');
        $black->setCellValue('A3', \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime('2024-05-24')));
        $black->getStyle('A3')->getNumberFormat()->setFormatCode('DD/MM/YYYY');

        $flats = $book->createSheet();
        $flats->setTitle('FLATSHEETS');
        $flats->setCellValue('C1', 'BLACK');
        $flats->fromArray([
            ['Date', 'O.S', 'Folded', 'C.S', 'Narration'],
            ['27/07/2024', 55, null, 455, 'Received 400 pcs'],
            ['2-Aug', 455, 20, 435, 'folded:20valleys,20bbcs'],
            [null, 435, null, 435, null],
        ], null, 'A2');

        $path = storage_path('framework/testing-register-' . uniqid('', true) . '.xlsx');
        (new Xlsx($book))->save($path);
        $upload = new UploadedFile($path, 'register.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAsAdmin()->post(route('stock.register.store'), ['register' => $upload])
            ->assertRedirect(route('stock.opening'))
            ->assertSessionHas('success');

        $ridges = Product::query()->where('name', 'Ridges')->firstOrFail();
        $sheets = Product::query()->where('name', 'Flatsheets')->firstOrFail();
        $this->assertTrue((bool) $ridges->for_sale);
        $this->assertFalse((bool) $sheets->for_sale);

        $colour = Colour::query()->where('name', 'Black')->firstOrFail();
        $ridgeVariant = ProductVariant::query()->where('product_id', $ridges->id)->where('colour_id', $colour->id)->firstOrFail();
        $sheetVariant = ProductVariant::query()->where('product_id', $sheets->id)->where('colour_id', $colour->id)->firstOrFail();

        $this->assertStock($ridges, 23, null, $ridgeVariant->id);
        $this->assertStock($sheets, 435, null, $sheetVariant->id);
        $this->assertSame(0, Sale::query()->count());

        $foldNote = StockMovement::query()->where('product_id', $sheets->id)->where('type', StockMovement::FOLDING)->first();
        $this->assertSame('folded:20valleys,20bbcs', $foldNote->notes);
        $this->assertEquals(20, (float) $foldNote->quantity_out);
        $saleNote = StockMovement::query()->where('product_id', $ridges->id)->where('type', StockMovement::SALE)->first();
        $this->assertSame('mbugua', $saleNote->notes);
        $this->assertSame('2024-08-02', substr((string) $foldNote->occurred_at, 0, 10));

        $again = new UploadedFile($path, 'register.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->actingAsAdmin()->post(route('stock.register.store'), ['register' => $again])
            ->assertRedirect(route('stock.opening'))
            ->assertSessionHas('success');

        $this->assertStock($ridges, 23, null, $ridgeVariant->id);
        $this->assertStock($sheets, 435, null, $sheetVariant->id);
    }

    public function test_duplicate_colour_is_rejected(): void
    {
        $this->actingAsAdmin()->post(route('colours.store'), [
            'name' => 'Coffee Brown',
            'is_active' => 1,
        ])->assertRedirect();

        $this->actingAsAdmin()
            ->from(route('colours.index'))
            ->post(route('colours.store'), [
                'name' => 'Coffee Brown',
                'is_active' => 1,
            ])
            ->assertRedirect(route('colours.index'))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Colour::query()->where('name', 'Coffee Brown')->count());
    }

    public function test_an_lpo_can_be_received_into_stock_once(): void
    {
        $supplier = Supplier::query()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'LPO Supplier',
            'is_active' => true,
        ]);
        $sheet = $this->makeProduct('Flat Sheet LPO', 'FS-LPO', 100, 150);

        $this->actingAsAdmin()->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'status' => 'ordered',
            'items' => [[
                'product_id' => $sheet->id,
                'quantity' => 40,
                'unit_cost' => 100,
            ]],
        ])->assertRedirect();

        $purchase = PurchaseOrder::query()->latest('id')->first();
        $this->assertStock($sheet, 0);

        $this->actingAsAdmin()->get(route('purchases.index', ['view' => 'lpo']))
            ->assertOk()
            ->assertSee('Receive');

        $this->actingAsAdmin()->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Receive goods');

        $this->actingAsAdmin()
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertStock($sheet, 40);
        $this->assertSame('received', $purchase->fresh()->status);

        $this->actingAsAdmin()
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStock($sheet, 40);
    }

    public function test_pos_requires_a_selling_price_when_the_item_has_none(): void
    {
        $this->actingAsAdmin()->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Selling price')
            ->assertSee('Enter selling price');

        $item = $this->makeProduct('Unpriced Sheet', 'UNPRICED', 0, 0);
        $this->opening($item, 5);

        $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [['product_id' => $item->id, 'quantity' => 2]],
            'payment_amount' => 0,
            'payment_method' => 'cash',
        ])->assertStatus(422)->assertJsonFragment([
            'message' => 'Enter a selling price for Unpriced Sheet.',
        ]);

        $this->assertEquals(0.0, (float) $item->fresh()->selling_price);
        $this->assertStock($item, 5);

        $sale = $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [['product_id' => $item->id, 'quantity' => 2, 'selling_price' => 250]],
            'payment_amount' => 500,
            'payment_method' => 'cash',
        ]);
        $sale->assertOk();

        $saved = Sale::query()->with('items')->findOrFail($sale->json('id'));
        $this->assertEquals(500.0, (float) $saved->total);
        $this->assertEquals(250.0, (float) $saved->items->first()->unit_price);
        $this->assertEquals(250.0, (float) $item->fresh()->selling_price);
        $this->assertStock($item, 3);

        $priced = $this->sell($this->product, 1, 100);
        $this->assertEquals(100.0, (float) $priced->items->first()->unit_price);
    }

    public function test_profit_and_loss_uses_the_sales_total_once(): void
    {
        $tax = Tax::query()->create([
            'company_id' => $this->company->id,
            'name' => 'VAT',
            'rate' => 16,
            'is_inclusive' => true,
            'is_active' => true,
        ]);
        $item = $this->makeProduct('Priced Sheet', 'PRICED-SHEET', 0, 800);
        $item->update(['tax_id' => $tax->id, 'tax_inclusive' => true]);
        $this->opening($item, 2);

        $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [['product_id' => $item->id, 'quantity' => 1]],
            'payment_amount' => 800,
            'payment_method' => 'cash',
        ])->assertOk();

        $this->actingAsAdmin()->get(route('accounting.profit-loss', [
            'show' => 1,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Sales Revenue')
            ->assertSee('800.00')
            ->assertSee('VAT on sales')
            ->assertSee('-110.34')
            ->assertSee('689.66')
            ->assertDontSee('1,489.66');
    }

    private function makeProduct(string $name, string $sku, float $cost, float $price): Product
    {
        return Product::query()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'sku' => $sku,
            'purchase_price' => $cost,
            'selling_price' => $price,
            'manage_stock' => true,
            'allow_negative_stock' => false,
            'is_active' => true,
            'for_sale' => true,
            'tax_inclusive' => true,
        ]);
    }

    private function opening(Product $product, float $quantity, ?int $branchId = null): void
    {
        app(InventoryService::class)->apply([
            'company_id' => $this->company->id,
            'branch_id' => $branchId ?: $this->branch->id,
            'product_id' => $product->id,
            'type' => StockMovement::OPENING,
            'quantity_in' => $quantity,
            'unit_cost' => $product->purchase_price,
            'user_id' => $this->admin->id,
            'notes' => 'Test opening',
            'occurred_at' => now(),
        ]);
    }

    private function adjust(Product $product, string $status, float $quantity): void
    {
        $this->actingAsAdmin()
            ->from(route('stock.manager'))
            ->post(route('stock.adjust', $product), [
                'branch_id' => $this->branch->id,
                'occurred_at' => now()->toDateString(),
                'status' => $status,
                'control_account' => 'migration_control',
                'quantity' => $quantity,
                'notes' => 'Test ' . $status,
            ])
            ->assertRedirect(route('stock.manager'))
            ->assertSessionHas('success');
    }

    private function sell(Product $product, float $quantity, float $payment): Sale
    {
        $response = $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'payment_amount' => $payment,
            'payment_method' => 'cash',
        ]);
        $response->assertOk();

        return Sale::query()->with('items')->findOrFail($response->json('id'));
    }

    private function assertStock(Product $product, float $expected, ?int $branchId = null, int $variantId = 0): void
    {
        $row = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId ?: $this->branch->id)
            ->where('product_variant_id', $variantId)
            ->first();

        $this->assertEquals($expected, $row ? (float) $row->quantity : 0.0);
    }

    private function spreadsheetUpload(array $rows): UploadedFile
    {
        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = storage_path('framework/testing-opening-' . uniqid('', true) . '.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        (new Xlsx($sheet))->save($path);

        return new UploadedFile($path, 'opening.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function movementBalance(Product $product, ?int $branchId = null, int $variantId = 0): float
    {
        $query = StockMovement::query()
            ->withoutGlobalScope('company')
            ->withoutGlobalScope('branch')
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId ?: $this->branch->id)
            ->where('product_variant_id', $variantId);

        return round((float) $query->sum('quantity_in') - (float) $query->sum('quantity_out'), 4);
    }
}
