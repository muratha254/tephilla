<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\JournalEntry;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;
use App\Models\StockTransfer;
use App\Services\AccountingPoster;
use Tests\Support\CreatesSellixWorld;
use Tests\TestCase;

class OperationalWorkflowTest extends TestCase
{
    use CreatesSellixWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSellixWorld();
    }

    public function test_cash_sale_via_pos_posts_balanced_journal_deducts_stock_and_is_idempotent(): void
    {
        $qty = 2;
        $unitPrice = (float) $this->product->selling_price;
        $expectedTotal = round($unitPrice * $qty, 2);

        $response = $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => $qty],
            ],
            'payment_amount' => $expectedTotal,
            'payment_method' => 'cash',
        ]);

        $response->assertOk()->assertJsonStructure(['id', 'number']);

        $sale = Sale::query()->with(['items', 'payments'])->findOrFail($response->json('id'));
        $this->assertSame(Sale::STATUS_COMPLETED, $sale->status);
        $this->assertEquals($expectedTotal, (float) $sale->total);
        $this->assertEquals(98.0, $this->stockQty());

        $entry = JournalEntry::query()
            ->with('lines')
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('source_event', 'sale_complete')
            ->first();

        $this->assertNotNull($entry);
        $debit = round((float) $entry->lines->sum('debit'), 2);
        $credit = round((float) $entry->lines->sum('credit'), 2);
        $this->assertGreaterThan(0, $debit);
        $this->assertEquals($debit, $credit);

        $this->assertTrue(
            AuditLog::query()
                ->where('module', 'sales')
                ->where('action', 'create')
                ->where('auditable_type', Sale::class)
                ->where('auditable_id', $sale->id)
                ->exists()
        );

        $this->assertEquals(1, LoyaltyTransaction::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', LoyaltyTransaction::TYPE_EARN)
            ->count());

        $this->customer->refresh();
        $this->assertEquals(round($expectedTotal * 0.01, 2), (float) $this->customer->loyalty_points);

        app(AccountingPoster::class)->postSale($sale->fresh(['items.product', 'payments']));

        $this->assertEquals(1, JournalEntry::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('source_event', 'sale_complete')
            ->count());

        $this->assertEquals(1, LoyaltyTransaction::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', LoyaltyTransaction::TYPE_EARN)
            ->count());
    }

    public function test_credit_note_create_and_post_restores_stock_and_rejects_over_quantity(): void
    {
        $order = $this->actingAsAdmin()->postJson(route('pos.order'), [
            'customer_id' => $this->customer->id,
            'sale_date' => now()->toDateString(),
            'document_type' => 'pos',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3],
            ],
            'payment_amount' => 300,
            'payment_method' => 'cash',
        ]);
        $order->assertOk();

        $sale = Sale::query()->with('items')->findOrFail($order->json('id'));
        $saleItem = $sale->items->first();
        $this->assertEquals(97.0, $this->stockQty());

        $posted = $this->actingAsAdmin()->post(route('sales.credit-notes.store'), [
            'sale_id' => $sale->id,
            'credit_date' => now()->toDateString(),
            'reason' => 'Customer return',
            'notes' => 'Partial return',
            'restore_stock' => 1,
            'action' => 'posted',
            'items' => [
                ['sale_item_id' => $saleItem->id, 'quantity' => 1],
            ],
        ]);

        $posted->assertRedirect();
        $this->assertEquals(98.0, $this->stockQty());

        $note = CreditNote::query()->where('sale_id', $sale->id)->first();
        $this->assertNotNull($note);
        $this->assertSame(CreditNote::STATUS_POSTED, $note->status);
        $this->assertTrue((bool) $note->stock_restored);
        $this->assertTrue((bool) $note->accounting_posted);

        $entry = JournalEntry::query()
            ->with('lines')
            ->where('source_type', CreditNote::class)
            ->where('source_id', $note->id)
            ->where('source_event', 'credit_note')
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals(
            round((float) $entry->lines->sum('debit'), 2),
            round((float) $entry->lines->sum('credit'), 2)
        );

        $rejected = $this->actingAsAdmin()
            ->from(route('sales.credit-notes.create'))
            ->post(route('sales.credit-notes.store'), [
                'sale_id' => $sale->id,
                'credit_date' => now()->toDateString(),
                'reason' => 'Too many',
                'restore_stock' => 1,
                'action' => 'posted',
                'items' => [
                    ['sale_item_id' => $saleItem->id, 'quantity' => 99],
                ],
            ]);

        $rejected->assertRedirect(route('sales.credit-notes.create'));
        $rejected->assertSessionHas('error');
        $this->assertEquals(98.0, $this->stockQty());
        $this->assertEquals(1, CreditNote::query()->where('sale_id', $sale->id)->count());
    }

    public function test_stock_transfer_complete_moves_stock_and_rejects_second_complete(): void
    {
        $this->assertEquals(100.0, $this->stockQty($this->branch->id));
        $this->assertEquals(0.0, $this->stockQty($this->secondaryBranch->id));

        $response = $this->actingAsAdmin()->post(route('stock.transfers.store'), [
            'from_branch_id' => $this->branch->id,
            'to_branch_id' => $this->secondaryBranch->id,
            'transfer_date' => now()->toDateString(),
            'notes' => 'Move stock',
            'action' => 'complete',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_cost' => 50,
                ],
            ],
        ]);

        $transfer = StockTransfer::query()->latest('id')->first();
        $this->assertNotNull($transfer);
        $response->assertRedirect(route('stock.transfers.show', $transfer));
        $this->assertSame(StockTransfer::STATUS_COMPLETED, $transfer->fresh()->status);

        $this->assertEquals(90.0, $this->stockQty($this->branch->id));
        $this->assertEquals(10.0, $this->stockQty($this->secondaryBranch->id));

        $second = $this->actingAsAdmin()
            ->from(route('stock.transfers.show', $transfer))
            ->post(route('stock.transfers.complete', $transfer));

        $second->assertRedirect(route('stock.transfers.show', $transfer));
        $second->assertSessionHas('error');
        $this->assertEquals(90.0, $this->stockQty($this->branch->id));
        $this->assertEquals(10.0, $this->stockQty($this->secondaryBranch->id));
    }

    public function test_unauthorized_cashier_cannot_access_settings_or_journal(): void
    {
        $this->actingAsCashier()
            ->get(route('settings.company'))
            ->assertForbidden();

        $this->actingAsCashier()
            ->get(route('accounting.journal'))
            ->assertForbidden();
    }

    public function test_authorized_admin_can_view_credit_notes_index(): void
    {
        $this->actingAsAdmin()
            ->get(route('sales.credit-notes'))
            ->assertOk();
    }
}
