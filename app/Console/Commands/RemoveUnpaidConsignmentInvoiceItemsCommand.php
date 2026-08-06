<?php

namespace App\Console\Commands;

use App\Models\ConsignmentGapSuppression;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Services\SaleSupplierLedgerRemovalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Removes unpaid consignment invoice_items for specific sale line(s), e.g. after an abandoned edit
 * where the business decides that line should not post to the supplier ledger (reconciliation −amount).
 */
class RemoveUnpaidConsignmentInvoiceItemsCommand extends Command
{
    protected $signature = 'consignment:remove-unpaid-invoice-items
                            {receipts : Comma-separated receipt numbers}
                            {--produk-id= : Only penjualan_detail lines with this id_produk}
                            {--suppress : Record consignment_gap_suppressions so ledger backfill will not recreate}
                            {--dry-run : List rows only}';

    protected $description = 'Delete unpaid consignment invoice_items for matching sale lines and fix invoice totals';

    public function handle(SaleSupplierLedgerRemovalService $removal): int
    {
        $dry = (bool) $this->option('dry-run');
        $doSuppress = (bool) $this->option('suppress');
        $produkId = $this->option('produk-id') !== null && $this->option('produk-id') !== ''
            ? (int) $this->option('produk-id')
            : null;

        $receipts = array_values(array_filter(array_map('trim', explode(',', (string) $this->argument('receipts')))));
        if ($receipts === []) {
            $this->error('Provide at least one receipt.');

            return 1;
        }

        if ($produkId === null || $produkId <= 0) {
            $this->error('Pass --produk-id=... so only intended lines are removed.');

            return 1;
        }

        if ($dry) {
            $this->warn('DRY RUN — no writes.');
        }

        foreach ($receipts as $receipt) {
            $penjualan = Penjualan::query()
                ->whereRaw('UPPER(TRIM(receiptno)) = ?', [strtoupper($receipt)])
                ->first();
            if (! $penjualan) {
                $this->error("Receipt not found: {$receipt}");

                return 1;
            }

            $details = PenjualanDetail::query()
                ->where('id_penjualan', $penjualan->id_penjualan)
                ->where('id_produk', $produkId)
                ->orderBy('id_penjualan_detail')
                ->get();

            if ($details->isEmpty()) {
                $this->warn("{$receipt}: no penjualan_detail for produk_id {$produkId}.");

                continue;
            }

            foreach ($details as $detail) {
                $item = $removal->firstUnpaidConsignmentInvoiceItem($detail);
                if (! $item) {
                    $this->line("{$receipt} detail {$detail->id_penjualan_detail}: no unpaid consignment invoice_item (skip remove).");
                } elseif ($dry) {
                    $this->line("{$receipt} detail {$detail->id_penjualan_detail}: would remove invoice_item id {$item->id} amount {$item->amount} qty {$item->quantity}");
                } else {
                    $removal->removeConsignmentInvoiceItemForDetail($detail);
                    $this->info("{$receipt}: removed invoice_item {$item->id} (amount {$item->amount}).");
                }

                if ($doSuppress && ! $dry && Schema::hasTable('consignment_gap_suppressions')) {
                    $produk = Produk::find($produkId);
                    if ($produk && $produk->id_supplier) {
                        ConsignmentGapSuppression::query()->updateOrCreate(
                            [
                                'penjualan_id' => $penjualan->id_penjualan,
                                'produk_id' => $produkId,
                                'supplier_id' => (int) $produk->id_supplier,
                            ],
                            ['note' => 'consignment:remove-unpaid-invoice-items --suppress']
                        );
                        $this->info("{$receipt}: suppression recorded for produk_id {$produkId}.");
                    }
                }
            }
        }

        return 0;
    }
}
