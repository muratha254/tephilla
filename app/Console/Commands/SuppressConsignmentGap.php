<?php

namespace App\Console\Commands;

use App\Models\ConsignmentGapSuppression;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record a sale line that must not generate (or regenerate) consignment invoice_items,
 * and optionally delete existing matching invoice_item rows (adjust invoice totals).
 */
class SuppressConsignmentGap extends Command
{
    protected $signature = 'consignment:suppress-gap
                            {receipt : Penjualan receipt number (e.g. U728)}
                            {--supplier= : Supplier name substring (default: ELSY)}
                            {--produk= : Product code substring (default: EE586)}
                            {--note= : Optional note stored with the suppression}
                            {--delete : Delete invoice_item rows for this sale+supplier+product}';

    protected $description = 'Suppress consignment ledger gap for one sale line; optional --delete removes existing invoice_items';

    public function handle(): int
    {
        if (! Schema::hasTable('consignment_gap_suppressions')) {
            $this->error('Run migrations first: consignment_gap_suppressions table is missing.');

            return 1;
        }

        $receipt = trim((string) $this->argument('receipt'));
        $supplierNeedle = trim((string) ($this->option('supplier') ?: 'ELSY'));
        $produkNeedle = trim((string) ($this->option('produk') ?: 'EE586'));
        $note = $this->option('note') ? (string) $this->option('note') : 'Manual suppression via consignment:suppress-gap';
        $doDelete = (bool) $this->option('delete');

        $penjualan = Penjualan::query()->whereRaw('UPPER(TRIM(receiptno)) = ?', [strtoupper($receipt)])->first();
        if (! $penjualan) {
            $this->error("Receipt not found: {$receipt}");

            return 1;
        }

        $supplier = Supplier::query()->where('nama', 'like', '%'.$supplierNeedle.'%')->first();
        if (! $supplier) {
            $this->error("Supplier not found matching: {$supplierNeedle}");

            return 1;
        }

        $produk = Produk::query()
            ->where('id_supplier', $supplier->id_supplier)
            ->where('kode_produk', 'like', $produkNeedle.'%')
            ->orderBy('id_produk')
            ->first();

        if (! $produk) {
            $this->error("No product for supplier {$supplier->nama} with code like {$produkNeedle}%");

            return 1;
        }

        ConsignmentGapSuppression::query()->updateOrCreate(
            [
                'penjualan_id' => $penjualan->id_penjualan,
                'produk_id' => $produk->id_produk,
                'supplier_id' => $supplier->id_supplier,
            ],
            ['note' => $note]
        );

        $this->info("Suppression recorded: receipt {$penjualan->receiptno} penjualan_id={$penjualan->id_penjualan} supplier_id={$supplier->id_supplier} produk_id={$produk->id_produk} ({$produk->kode_produk})");

        if (! $doDelete) {
            $this->warn('No --delete: existing invoice_items were not removed. Run again with --delete if needed.');

            return 0;
        }

        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return 0;
        }

        $items = InvoiceItem::query()
            ->where('penjualan_id', $penjualan->id_penjualan)
            ->where('supplier_id', $supplier->id_supplier)
            ->where('produk_id', $produk->id_produk)
            ->orderBy('id')
            ->get();

        $deleted = 0;
        DB::transaction(function () use ($items, &$deleted) {
            foreach ($items as $item) {
                $invoice = Invoice::query()->find($item->invoice_id);
                $amt = (float) $item->amount;
                $item->delete();
                $deleted++;
                if ($invoice) {
                    $invoice->total = max(0, (float) $invoice->total - $amt);
                    $invoice->save();
                    if ($invoice->items()->count() === 0) {
                        $invoice->delete();
                    }
                }
            }
        });

        $this->info("Deleted {$deleted} invoice_item row(s).");

        return 0;
    }
}
