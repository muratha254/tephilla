<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lydia/KAUMA-titled sales sometimes store consignment invoice_items under a wrong supplier_id
 * (e.g. orphaned id_supplier on produk). Lydia's consignment UI filters by supplier_id, so those
 * lines are invisible until supplier_id (and invoice_id) are aligned to LYDIA NAKUTUDE KAUMA.
 */
class ReassignLydiaKaumaReceiptSupplierInvoiceItems extends Command
{
    protected $signature = 'consignment:reassign-lydia-receipt-suppliers
                            {--from= : Inclusive sale date Y-m-d COALESCE(saledate, created_at)}
                            {--to= : Inclusive sale date Y-m-d}
                            {--dry-run : List changes without writing}';

    protected $description = 'Set invoice_items.supplier_id (and invoice) to LYDIA NAKUTUDE KAUMA for LYDIA|KAUMA receipts in range';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no database writes.');
        }

        $from = $this->option('from') ? Carbon::parse($this->option('from'))->toDateString() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->toDateString() : null;

        if (! $from || ! $to) {
            $this->error('Both --from= and --to= (Y-m-d) are required.');

            return 1;
        }

        $supplier = Supplier::query()
            ->whereRaw('UPPER(TRIM(nama)) LIKE ?', ['%NAKUTUDE%'])
            ->whereRaw('UPPER(TRIM(nama)) LIKE ?', ['%KAUMA%'])
            ->first();

        if (! $supplier) {
            $this->error('Supplier LYDIA NAKUTUDE KAUMA not found.');

            return 1;
        }

        $lydiaId = (int) $supplier->id_supplier;

        $items = InvoiceItem::query()
            ->whereNotNull('penjualan_id')
            ->where('supplier_id', '!=', $lydiaId)
            ->whereRaw('COALESCE(amount_paid, 0) = 0')
            ->whereHas('penjualan', function ($q) use ($from, $to) {
                $q->where(function ($q2) {
                    $q2->where('receiptno', 'like', '%LYDIA%')
                        ->orWhere('receiptno', 'like', '%KAUMA%');
                });
                $q->whereRaw(
                    'DATE(COALESCE(saledate, created_at)) BETWEEN ? AND ?',
                    [$from, $to]
                );
            })
            ->with('penjualan')
            ->orderBy('id')
            ->get();

        $this->info('Items to reassign: '.$items->count());

        $moved = 0;
        foreach ($items as $item) {
            $pen = $item->penjualan;
            if (! $pen) {
                continue;
            }
            $dateOnly = Carbon::parse($pen->saledate ?? $pen->created_at)->toDateString();
            $receipt = (string) $pen->receiptno;
            $line = "id={$item->id} receipt={$receipt} prod_id={$item->produk_id} qty={$item->quantity} amt={$item->amount} from_supplier={$item->supplier_id}";

            if ($dryRun) {
                $this->line("Would reassign: {$line} → supplier_id {$lydiaId} (invoice for {$dateOnly})");
                $moved++;

                continue;
            }

            DB::transaction(function () use ($item, $lydiaId, $dateOnly) {
                $amt = (float) $item->amount;
                $oldInvoice = $item->invoice_id ? Invoice::query()->find($item->invoice_id) : null;

                $invoice = Invoice::query()
                    ->where('id_supplier', $lydiaId)
                    ->whereDate('created_at', $dateOnly)
                    ->first();

                if (! $invoice) {
                    $invoice = Invoice::create([
                        'id_supplier' => $lydiaId,
                        'total' => 0,
                    ]);
                    if (Schema::hasTable('invoices')) {
                        DB::table('invoices')->where('id', $invoice->id)->update([
                            'created_at' => $dateOnly.' 00:00:00',
                            'updated_at' => $dateOnly.' 00:00:00',
                        ]);
                        $invoice->refresh();
                    }
                }

                $targetId = (int) $invoice->id;
                $oldId = $oldInvoice ? (int) $oldInvoice->id : 0;

                if ($oldInvoice && $oldId !== $targetId) {
                    $oldInvoice->total = max(0, (float) $oldInvoice->total - $amt);
                    $oldInvoice->save();
                    if ($oldInvoice->items()->count() === 0) {
                        $oldInvoice->delete();
                    }
                }

                $item->supplier_id = $lydiaId;
                $item->invoice_id = $targetId;
                $item->save();

                if (! $oldInvoice || $oldId !== $targetId) {
                    $invoice->total = (float) $invoice->total + $amt;
                    $invoice->save();
                }
            });

            $this->line("Reassigned: {$line}");
            $moved++;
        }

        $this->info('Done. '.($dryRun ? 'Would reassign' : 'Reassigned').": {$moved}");

        return 0;
    }
}
