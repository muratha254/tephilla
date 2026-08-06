<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes excess unpaid invoice_items for a sale line when summed quantities exceed
 * penjualan_detail (e.g. a phantom qty-4 row plus correct qty-1 row for the same product).
 * Keeps rows that can be 1:1 matched to each detail line's quantity; deletes unmatched extras.
 */
class DedupePhantomConsignmentInvoiceItems extends Command
{
    protected $signature = 'consignment:dedupe-phantom-items
                            {--dry-run : List deletions without writing}
                            {--from= : Inclusive sale date (Y-m-d) COALESCE(saledate, created_at)}
                            {--to= : Inclusive sale date (Y-m-d)}
                            {--receipt= : Only this penjualan receipt number (e.g. A51076)}';

    protected $description = 'Remove phantom duplicate consignment invoice_items (qty sum > sale detail sum for same penjualan+product)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN – no changes will be written.');
        }

        $from = $this->option('from') ? Carbon::parse($this->option('from'))->toDateString() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->toDateString() : null;
        $receipt = $this->option('receipt') ? trim((string) $this->option('receipt')) : '';

        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            $this->error('invoice_items.penjualan_id is required.');

            return 1;
        }

        $groupsQuery = DB::table('invoice_items as ii')
            ->whereNotNull('ii.penjualan_id')
            ->select('ii.penjualan_id', 'ii.produk_id')
            ->groupBy('ii.penjualan_id', 'ii.produk_id');

        $joinPenjualan = ($from || $to || $receipt !== '');
        if ($joinPenjualan) {
            $groupsQuery->join('penjualan as p', 'p.id_penjualan', '=', 'ii.penjualan_id');
            if ($from || $to) {
                $groupsQuery->whereRaw(
                    'DATE(COALESCE(p.saledate, p.created_at)) BETWEEN ? AND ?',
                    [$from ?? '1970-01-01', $to ?? '2099-12-31']
                );
            }
            if ($receipt !== '') {
                $groupsQuery->where('p.receiptno', $receipt);
            }
        }

        $groups = $groupsQuery->get();
        $this->info('Distinct (penjualan_id, produk_id) groups to check: '.$groups->count());

        $deleted = 0;
        $groupsFixed = 0;

        foreach ($groups->chunk(200) as $chunk) {
            $penIds = $chunk->pluck('penjualan_id')->unique()->filter()->all();
            $prodIds = $chunk->pluck('produk_id')->unique()->filter()->all();

            if ($penIds === [] || $prodIds === []) {
                continue;
            }

            $penjualans = Penjualan::query()->whereIn('id_penjualan', $penIds)->get()->keyBy('id_penjualan');
            $produks = Produk::query()->whereIn('id_produk', $prodIds)->get()->keyBy('id_produk');
            $supplierIds = $produks->pluck('id_supplier')->unique()->filter()->all();
            $suppliers = $supplierIds === []
                ? collect()
                : Supplier::query()->whereIn('id_supplier', $supplierIds)->get()->keyBy('id_supplier');

            $detailKeyToQtys = PenjualanDetail::query()
                ->whereIn('id_penjualan', $penIds)
                ->whereIn('id_produk', $prodIds)
                ->orderBy('id_penjualan_detail')
                ->get()
                ->groupBy(fn ($d) => $d->id_penjualan.'_'.$d->id_produk)
                ->map(fn (Collection $rows) => $rows->pluck('jumlah')->map(fn ($q) => (int) $q)->values()->all());

            $invoiceItemsByKey = InvoiceItem::query()
                ->whereIn('penjualan_id', $penIds)
                ->whereIn('produk_id', $prodIds)
                ->whereRaw('COALESCE(amount_paid, 0) = 0')
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($i) => $i->penjualan_id.'_'.$i->produk_id);

            foreach ($chunk as $g) {
                $penjualanId = (int) $g->penjualan_id;
                $produkId = (int) $g->produk_id;
                $key = $penjualanId.'_'.$produkId;

                $penjualan = $penjualans->get($penjualanId);
                if (! $penjualan) {
                    continue;
                }

                // Completed sales, or paid-but-active (reopened edit not finalized): both can have phantom invoice rows.
                if (Schema::hasColumn('penjualan', 'status')) {
                    $st = strtolower((string) $penjualan->status);
                    $paidActive = $st === 'active'
                        && Schema::hasColumn('penjualan', 'bayar')
                        && (float) ($penjualan->bayar ?? 0) > 0;
                    if ($st !== 'completed' && ! $paidActive) {
                        continue;
                    }
                }

                if (! $joinPenjualan) {
                    $saleDay = Carbon::parse($penjualan->saledate ?? $penjualan->created_at)->toDateString();
                    if ($from && strcmp($saleDay, $from) < 0) {
                        continue;
                    }
                    if ($to && strcmp($saleDay, $to) > 0) {
                        continue;
                    }
                    if ($receipt !== '' && (string) $penjualan->receiptno !== $receipt) {
                        continue;
                    }
                }

                $produk = $produks->get($produkId);
                if (! $produk) {
                    continue;
                }

                $receiptUpper = strtoupper((string) ($penjualan->receiptno ?? ''));
                $lydiaKaumaReceipt = str_contains($receiptUpper, 'LYDIA') || str_contains($receiptUpper, 'KAUMA');

                $supplierConsignmentOk = false;
                if ($produk->id_supplier) {
                    $supplier = $suppliers->get($produk->id_supplier);
                    $supplierConsignmentOk = $supplier
                        && strtoupper(trim((string) ($supplier->mop ?? ''))) === 'CONSIGNMENT';
                }

                if (! $supplierConsignmentOk && ! $lydiaKaumaReceipt) {
                    continue;
                }

                $detailQtys = $detailKeyToQtys->get($key, []);
                if ($detailQtys === []) {
                    continue;
                }

                $totalDet = array_sum($detailQtys);

                $pending = collect($invoiceItemsByKey->get($key, []))->values();
                if ($pending->isEmpty()) {
                    continue;
                }

                $groupDeletes = 0;

                while ($pending->isNotEmpty()) {
                    $totalInv = (int) $pending->sum('quantity');
                    if ($totalInv <= $totalDet) {
                        break;
                    }

                    $dWork = $detailQtys;
                    $unmatched = [];

                    foreach ($pending->sortBy('quantity') as $inv) {
                        $q = (int) $inv->quantity;
                        $matched = false;
                        foreach ($dWork as $idx => $dq) {
                            if ((int) $dq === $q) {
                                unset($dWork[$idx]);
                                $dWork = array_values($dWork);
                                $matched = true;

                                break;
                            }
                        }
                        if (! $matched) {
                            $unmatched[] = $inv;
                        }
                    }

                    if ($unmatched === []) {
                        $this->warn("Excess qty but no safe phantom row for penjualan_id={$penjualanId} produk_id={$produkId} (inspect manually).");

                        break;
                    }

                    usort($unmatched, function ($a, $b) {
                        $cq = (int) $b->quantity <=> (int) $a->quantity;

                        return $cq !== 0 ? $cq : (int) $b->id <=> (int) $a->id;
                    });

                    $inv = $unmatched[0];
                    $receiptLabel = $penjualan->receiptno ?? $penjualanId;
                    $line = "receipt {$receiptLabel} produk_id {$produkId} invoice_item id {$inv->id} qty {$inv->quantity} amount {$inv->amount}";
                    if ($dryRun) {
                        $this->line("Would delete: {$line}");
                    } else {
                        DB::transaction(function () use ($inv) {
                            $invoice = Invoice::find($inv->invoice_id);
                            $amt = (float) $inv->amount;
                            $inv->delete();
                            if ($invoice) {
                                $invoice->total = max(0, (float) $invoice->total - $amt);
                                $invoice->save();
                                if ($invoice->items()->count() === 0) {
                                    $invoice->delete();
                                }
                            }
                        });
                        $this->line("Deleted: {$line}");
                    }

                    $pending = $pending->reject(fn ($row) => (int) $row->id === (int) $inv->id)->values();

                    $deleted++;
                    $groupDeletes++;
                }

                if ($groupDeletes > 0) {
                    $groupsFixed++;
                }
            }
        }

        $this->info("Done. Groups with excess qty fixed: {$groupsFixed}, invoice_items ".($dryRun ? 'that would be removed' : 'removed').": {$deleted}");

        return 0;
    }
}
