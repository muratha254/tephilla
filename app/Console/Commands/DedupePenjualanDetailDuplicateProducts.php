<?php

namespace App\Console\Commands;

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges multiple penjualan_detail rows for the same product on one receipt into one line
 * (same id_produk). Updates sale header totals. Use after accidental double entry or reopen-edit issues.
 */
class DedupePenjualanDetailDuplicateProducts extends Command
{
    protected $signature = 'penjualan:dedupe-detail-lines
                            {receipt : Receipt number (e.g. U728, UTAM657)}
                            {--dry-run : Show merges without saving}';

    protected $description = 'Merge duplicate penjualan_detail lines (same product) on one receipt and recalculate totals';

    public function handle(): int
    {
        $receipt = trim((string) $this->argument('receipt'));
        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('DRY RUN — no database writes.');
        }

        $penjualan = Penjualan::query()
            ->whereRaw('UPPER(TRIM(receiptno)) = ?', [strtoupper($receipt)])
            ->first();

        if (! $penjualan) {
            $this->error("No sale found for receipt: {$receipt}");

            return 1;
        }

        $details = PenjualanDetail::query()
            ->where('id_penjualan', $penjualan->id_penjualan)
            ->orderBy('id_penjualan_detail')
            ->get();

        $grouped = $details->groupBy('id_produk')->filter(fn ($g) => $g->count() > 1);
        if ($grouped->isEmpty()) {
            $this->info('No duplicate product lines on this receipt (nothing to merge).');

            return 0;
        }

        foreach ($grouped as $produkId => $rows) {
            $ids = $rows->pluck('id_penjualan_detail')->all();
            $this->line("Product id {$produkId}: {$rows->count()} rows — detail ids: ".implode(', ', $ids));
        }

        if ($dry) {
            return 0;
        }

        DB::transaction(function () use ($penjualan, $grouped) {
            foreach ($grouped as $produkId => $rows) {
                $keeper = $rows->sortBy('id_penjualan_detail')->first();
                $sumQty = (int) $rows->sum(fn ($r) => (int) $r->jumlah);
                $sumSub = (float) $rows->sum(fn ($r) => (float) $r->subtotal);
                $hj = $sumQty > 0 ? round($sumSub / $sumQty, 0) : (float) ($keeper->harga_jual ?? 0);

                $keeper->jumlah = $sumQty;
                $keeper->subtotal = $sumSub;
                $keeper->harga_jual = $hj;
                $keeper->save();

                $deleteIds = $rows->pluck('id_penjualan_detail')->diff([$keeper->id_penjualan_detail])->all();
                if ($deleteIds !== []) {
                    PenjualanDetail::query()->whereIn('id_penjualan_detail', $deleteIds)->delete();
                }
            }

            $penjualan->refresh();
            $all = PenjualanDetail::query()->where('id_penjualan', $penjualan->id_penjualan)->get();
            $penjualan->total_item = (int) $all->sum(fn ($r) => (int) $r->jumlah);
            $penjualan->total_harga = (float) $all->sum(fn ($r) => (float) $r->subtotal);

            $type = $penjualan->discount_type ?? 'percentage';
            if ($type === 'fixed' && (float) ($penjualan->discount_amount ?? 0) > 0) {
                $penjualan->bayar = max(0, round($penjualan->total_harga - (float) $penjualan->discount_amount, 0));
            } else {
                $pct = min(100, max(0, (float) ($penjualan->diskon ?? 0)));
                $penjualan->bayar = round($penjualan->total_harga * (1 - $pct / 100), 0);
            }

            if (Schema::hasColumn('penjualan', 'total_with_vat')) {
                $penjualan->total_with_vat = $penjualan->bayar;
            }
            if (Schema::hasColumn('penjualan', 'tax') && $penjualan->bayar > 0) {
                $penjualan->tax = round($penjualan->bayar * (16 / 116), 2);
            }

            $penjualan->save();
        });

        $this->info('Merged duplicate lines and updated sale totals. Run consignment:dedupe-phantom-items for this receipt if consignment invoice rows were duplicated.');

        return 0;
    }
}
