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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-off: ensure consignment invoice_items exist for LYDIA NAKUTUDE KAUMA lines
 * transcribed from the user's March 2026 reference sheet (receipt prefix + sale date + commodity + qty).
 */
class EnsureLydiaKaumaReferenceConsignmentItems extends Command
{
    protected $signature = 'consignment:ensure-lydia-reference {--dry-run : Show actions without writing}';

    protected $description = 'Create missing consignment invoice_items for LYDIA NAKUTUDE KAUMA (Mar 2026 reference list)';

    /**
     * Rows: receipt prefix (before |), sale date Y-m-d, commodity label, qty, expected buying total (qty×harga_beli).
     *
     * @var list<array{r:string,d:string,c:string,q:int,buy:float}>
     */
    private function referenceRows(): array
    {
        return [
            ['r' => 'B12352', 'd' => '2026-03-21', 'c' => 'INM176 AI', 'q' => 1, 'buy' => 1500],
            ['r' => 'B12359', 'd' => '2026-03-21', 'c' => 'INM344 SM', 'q' => 1, 'buy' => 250],
            ['r' => 'B12344', 'd' => '2026-03-18', 'c' => 'IMN300 SM', 'q' => 2, 'buy' => 900],
            ['r' => 'A51399', 'd' => '2026-03-18', 'c' => 'INM358 CE', 'q' => 1, 'buy' => 600],
            ['r' => 'B12340', 'd' => '2026-03-18', 'c' => 'INM358 CE', 'q' => 2, 'buy' => 1200],
            ['r' => 'A51397', 'd' => '2026-03-18', 'c' => 'INM350 SM', 'q' => 1, 'buy' => 300],
            ['r' => 'A51390', 'd' => '2026-03-18', 'c' => 'INM350 SE', 'q' => 1, 'buy' => 1000],
            ['r' => 'A51381', 'd' => '2026-03-17', 'c' => 'INM274 SE', 'q' => 1, 'buy' => 450],
            ['r' => 'A51378', 'd' => '2026-03-17', 'c' => 'INM229 R', 'q' => 1, 'buy' => 200],
            ['r' => 'A51378', 'd' => '2026-03-17', 'c' => 'INM276 SH', 'q' => 1, 'buy' => 1500],
            ['r' => 'A51371', 'd' => '2026-03-17', 'c' => 'INM353 TU', 'q' => 1, 'buy' => 350],
            ['r' => 'B12334', 'd' => '2026-03-15', 'c' => 'INM317 PE', 'q' => 1, 'buy' => 350],
            ['r' => 'A51352', 'd' => '2026-03-15', 'c' => 'INM317 PE', 'q' => 2, 'buy' => 700],
            ['r' => 'A51310', 'd' => '2026-03-14', 'c' => 'INM355 KI', 'q' => 1, 'buy' => 350],
            ['r' => 'A51313', 'd' => '2026-03-14', 'c' => 'INM316 EL', 'q' => 1, 'buy' => 350],
            ['r' => 'A51328', 'd' => '2026-03-14', 'c' => 'INM342 A!', 'q' => 1, 'buy' => 600],
            ['r' => 'A51320', 'd' => '2026-03-14', 'c' => 'IMN301 M', 'q' => 1, 'buy' => 800],
            ['r' => 'B12321', 'd' => '2026-03-14', 'c' => 'INM334 A!', 'q' => 1, 'buy' => 300],
            ['r' => 'B12315', 'd' => '2026-03-12', 'c' => 'INM342 A!', 'q' => 2, 'buy' => 1200],
            ['r' => 'A51281', 'd' => '2026-03-12', 'c' => 'INM342 SC', 'q' => 1, 'buy' => 150],
            ['r' => 'A51215', 'd' => '2026-03-09', 'c' => 'INM303 M', 'q' => 1, 'buy' => 1200],
            ['r' => 'A51214', 'd' => '2026-03-09', 'c' => 'IMN304 LA', 'q' => 1, 'buy' => 1800],
            ['r' => 'B12303', 'd' => '2026-03-09', 'c' => 'IMN23 RW', 'q' => 1, 'buy' => 800],
            ['r' => 'A51210', 'd' => '2026-03-09', 'c' => 'INM347 SE', 'q' => 1, 'buy' => 800],
            ['r' => 'A51194', 'd' => '2026-03-08', 'c' => 'INM345 A!', 'q' => 1, 'buy' => 800],
            ['r' => 'A51168', 'd' => '2026-03-07', 'c' => 'IMN326 M', 'q' => 1, 'buy' => 800],
            ['r' => 'A51174', 'd' => '2026-03-07', 'c' => 'INM321 M', 'q' => 1, 'buy' => 500],
            ['r' => 'A51170', 'd' => '2026-03-07', 'c' => 'INM344 SM', 'q' => 1, 'buy' => 250],
            ['r' => 'A51133', 'd' => '2026-03-07', 'c' => 'INM331 CE', 'q' => 1, 'buy' => 1000],
            ['r' => 'A51145', 'd' => '2026-03-07', 'c' => 'IMN303 M', 'q' => 4, 'buy' => 4800],
            ['r' => 'A51097', 'd' => '2026-03-06', 'c' => 'INM307 RE', 'q' => 1, 'buy' => 2500],
            ['r' => 'A51043', 'd' => '2026-03-05', 'c' => 'IMN300 SM', 'q' => 1, 'buy' => 450],
            ['r' => 'A51039', 'd' => '2026-03-04', 'c' => 'INM333 TI', 'q' => 1, 'buy' => 300],
            ['r' => 'A51039', 'd' => '2026-03-04', 'c' => 'INM344 SM', 'q' => 1, 'buy' => 250],
            ['r' => 'A50962', 'd' => '2026-03-02', 'c' => 'INM358 CE', 'q' => 2, 'buy' => 1200],
            ['r' => 'A50981', 'd' => '2026-03-02', 'c' => 'INM342 SC', 'q' => 2, 'buy' => 300],
            ['r' => 'A50981', 'd' => '2026-03-02', 'c' => 'INM343 H_', 'q' => 2, 'buy' => 700],
            ['r' => 'A50983', 'd' => '2026-03-02', 'c' => 'INM338 SM', 'q' => 1, 'buy' => 300],
            ['r' => 'A50931', 'd' => '2026-03-01', 'c' => 'INM334 A!', 'q' => 1, 'buy' => 300],
        ];
    }

    private function commodityTokens(string $commodity): array
    {
        $commodity = trim(str_replace('_', ' ', $commodity));
        if (preg_match('/^([A-Z]{2,4}\d{2,5})/i', $commodity, $m)) {
            $t = strtoupper($m[1]);
        } else {
            $t = strtoupper(explode(' ', $commodity)[0] ?? '');
        }
        $out = [$t];
        if (str_starts_with($t, 'IMN')) {
            $out[] = 'INM'.substr($t, 3);
        }
        if (str_starts_with($t, 'INM')) {
            $out[] = 'IMN'.substr($t, 3);
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Receipt text used in UI for Lydia consignment sales (supplier filter on produk is often wrong / orphaned).
     */
    private function isLydiaKaumaReceipt(Penjualan $penjualan): bool
    {
        $r = strtoupper((string) $penjualan->receiptno);

        return str_contains($r, 'LYDIA') || str_contains($r, 'KAUMA');
    }

    /**
     * Match a sale line on this penjualan: qty, optional buying total, kode token (suffixed kodes in DB).
     * Prefers produk.id_supplier = Lydia; if none and receipt is LYDIA|KAUMA, matches any supplier on that sale
     * (invoice_items still record Lydia as supplier_id).
     */
    private function resolveProdukForRow(Penjualan $penjualan, Supplier $supplier, string $commodity, int $qty, float $refBuy): ?Produk
    {
        $tokens = $this->commodityTokens($commodity);

        $detailProduk = function (bool $requireLydiaSupplier) use ($penjualan, $supplier, $qty) {
            $q = DB::table('penjualan_detail as pd')
                ->join('produk as pr', 'pr.id_produk', '=', 'pd.id_produk')
                ->where('pd.id_penjualan', $penjualan->id_penjualan)
                ->where('pd.jumlah', $qty)
                ->select('pr.*')
                ->selectRaw('ROUND(pd.jumlah * COALESCE(pr.harga_beli, 0), 2) as line_cost');
            if ($requireLydiaSupplier) {
                $q->where('pr.id_supplier', $supplier->id_supplier);
            }

            return $q->get();
        };

        $rows = $detailProduk(true);
        if ($rows->isEmpty() && $this->isLydiaKaumaReceipt($penjualan)) {
            $rows = $detailProduk(false);
        }

        if ($rows->isEmpty()) {
            return null;
        }

        $close = $rows->filter(function ($r) use ($refBuy) {
            return abs((float) $r->line_cost - (float) $refBuy) < 0.05;
        });
        $pool = $close->isNotEmpty() ? $close : $rows;

        foreach ($tokens as $token) {
            $hit = $pool->first(function ($p) use ($token) {
                $k = strtoupper((string) $p->kode_produk);

                return str_starts_with($k, $token)
                    || str_contains(strtoupper((string) $p->nama_produk), $token);
            });
            if ($hit) {
                return Produk::query()->find($hit->id_produk);
            }
        }

        return Produk::query()->find($pool->first()->id_produk);
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no database writes.');
        }

        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            $this->error('invoice_items.penjualan_id is required.');

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

        if (strtoupper(trim((string) ($supplier->mop ?? ''))) !== 'CONSIGNMENT') {
            $this->warn('Supplier MOP is not CONSIGNMENT; consignment list may still hide these lines.');
        }

        $this->info("Supplier: {$supplier->nama} (id_supplier={$supplier->id_supplier})");

        $created = 0;
        $skipped = 0;
        $warned = 0;

        foreach ($this->referenceRows() as $row) {
            $prefix = $row['r'];
            $dateOnly = $row['d'];
            $qty = (int) $row['q'];
            $refBuy = round((float) $row['buy'], 2);

            $penQuery = Penjualan::query()
                ->where('status', 'completed')
                ->whereDate(DB::raw('COALESCE(saledate, created_at)'), $dateOnly)
                ->where(function ($q) use ($prefix) {
                    $q->where('receiptno', 'like', $prefix.'|%')
                        ->orWhere('receiptno', $prefix);
                });

            $penjualan = (clone $penQuery)
                ->where(function ($q) {
                    $q->where('receiptno', 'like', '%LYDIA%')
                        ->orWhere('receiptno', 'like', '%KAUMA%');
                })
                ->orderBy('id_penjualan')
                ->first();

            if (! $penjualan) {
                $penjualan = $penQuery->orderBy('id_penjualan')->first();
            }

            if (! $penjualan) {
                $this->warn("No completed penjualan for receipt {$prefix} on {$dateOnly}.");
                $warned++;

                continue;
            }

            $produk = $this->resolveProdukForRow($penjualan, $supplier, $row['c'], $qty, $refBuy);
            if (! $produk) {
                $this->warn("No matching sale line for \"{$row['c']}\" qty {$qty} buy {$refBuy} on {$penjualan->receiptno}.");
                $warned++;

                continue;
            }

            $detailCount = (int) PenjualanDetail::query()
                ->where('id_penjualan', $penjualan->id_penjualan)
                ->where('id_produk', $produk->id_produk)
                ->where('jumlah', $qty)
                ->count();

            if ($detailCount === 0) {
                $this->warn("No penjualan_detail for {$penjualan->receiptno} product {$produk->kode_produk} qty {$qty}.");
                $warned++;

                continue;
            }

            $invoiceSum = (int) InvoiceItem::query()
                ->where('penjualan_id', $penjualan->id_penjualan)
                ->where('produk_id', $produk->id_produk)
                ->sum('quantity');
            $detailSum = (int) PenjualanDetail::query()
                ->where('id_penjualan', $penjualan->id_penjualan)
                ->where('id_produk', $produk->id_produk)
                ->sum('jumlah');

            if ($invoiceSum > $detailSum) {
                $this->warn("Phantom qty on {$penjualan->receiptno} {$produk->kode_produk} — run consignment:dedupe-phantom-items first.");
                $warned++;

                continue;
            }

            $matchingInvoiceCount = (int) InvoiceItem::query()
                ->where('penjualan_id', $penjualan->id_penjualan)
                ->where('produk_id', $produk->id_produk)
                ->where('quantity', $qty)
                ->whereRaw('COALESCE(amount_paid, 0) = 0')
                ->count();

            if ($matchingInvoiceCount >= $detailCount) {
                $skipped++;

                continue;
            }

            $amount = round((float) ($produk->harga_beli ?? 0) * $qty, 2);
            if ($amount <= 0) {
                $this->warn("Zero amount for {$produk->kode_produk} on {$penjualan->receiptno}.");
                $warned++;

                continue;
            }

            if (abs($amount - $refBuy) > 0.05) {
                $this->line("Note: DB cost {$amount} vs reference buy {$refBuy} for {$prefix} {$produk->kode_produk} (using DB harga_beli×qty).");
            }

            if ($dryRun) {
                $this->line("Would create: {$penjualan->receiptno} | {$produk->nama_produk} qty {$qty} amount {$amount}");
                $created++;

                continue;
            }

            DB::transaction(function () use ($penjualan, $supplier, $produk, $qty, $amount, $dateOnly) {
                $invoice = Invoice::where('id_supplier', $supplier->id_supplier)
                    ->whereDate('created_at', $dateOnly)
                    ->first();

                if (! $invoice) {
                    $invoice = Invoice::create([
                        'id_supplier' => $supplier->id_supplier,
                        'total' => 0,
                    ]);
                    DB::table('invoices')->where('id', $invoice->id)->update([
                        'created_at' => $dateOnly.' 00:00:00',
                        'updated_at' => $dateOnly.' 00:00:00',
                    ]);
                    $invoice->refresh();
                }

                $diskon = 0;
                if (Schema::hasColumn('penjualan_detail', 'diskon')) {
                    $d = PenjualanDetail::query()
                        ->where('id_penjualan', $penjualan->id_penjualan)
                        ->where('id_produk', $produk->id_produk)
                        ->where('jumlah', $qty)
                        ->orderBy('id_penjualan_detail')
                        ->first();
                    $diskon = (int) ($d->diskon ?? 0);
                }

                $item = InvoiceItem::create([
                    'uniqid' => uniqid(),
                    'produk_id' => $produk->id_produk,
                    'supplier_id' => $supplier->id_supplier,
                    'invoice_id' => $invoice->id,
                    'status' => 'Not paid',
                    'discount' => $diskon,
                    'balance' => $amount,
                    'amount_paid' => 0,
                    'quantity' => $qty,
                    'amount' => $amount,
                    'penjualan_id' => $penjualan->id_penjualan,
                ]);

                DB::table('invoice_items')->where('id', $item->id)->update([
                    'created_at' => $dateOnly.' 00:00:00',
                    'updated_at' => $dateOnly.' 00:00:00',
                ]);

                $invoice->total = (float) $invoice->total + $amount;
                $invoice->save();
            });

            $created++;
            $this->line("Created: {$penjualan->receiptno} | {$produk->kode_produk} qty {$qty} amount {$amount}");
        }

        $this->info("Done. Created: {$created}, skipped (already linked): {$skipped}, warnings: {$warned}");

        return 0;
    }
}
