<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\DailyCash;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Services\EnsureSaleSupplierLedgerService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finalize sales that were opened for editing (initiate edit or admin reopen) but never completed again.
 *
 * Initiate edit reverses payment and restores stock; admin reopen restores stock only (payment stays).
 * This command applies the opposite stock movement, re-applies payment when it was reversed,
 * sets status to completed, clears edit_initiated_at, fixes DailyCash for the sale day, then
 * backfills cash Pembelian / consignment gaps via EnsureSaleSupplierLedgerService.
 */
class RestoreAbandonedSaleEditCommand extends Command
{
    protected $signature = 'penjualan:restore-abandoned-edit
                            {receipts : Comma-separated receipt numbers (e.g. U728,UTAM657)}
                            {--dry-run : Show actions without writing}';

    protected $description = 'Complete abandoned edit: restore accounts/stock, set sale to completed, refresh DailyCash and supplier ledgers';

    public function handle(EnsureSaleSupplierLedgerService $ensure): int
    {
        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('DRY RUN — no database writes.');
        }

        $raw = (string) $this->argument('receipts');
        $receipts = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if ($receipts === []) {
            $this->error('Provide at least one receipt number.');

            return 1;
        }

        $exit = 0;
        foreach ($receipts as $receipt) {
            $r = $this->restoreOne($receipt, $ensure, $dry);
            if ($r !== 0) {
                $exit = $r;
            }
        }

        return $exit;
    }

    private function restoreOne(string $receipt, EnsureSaleSupplierLedgerService $ensure, bool $dry): int
    {
        $penjualan = Penjualan::query()
            ->whereRaw('UPPER(TRIM(receiptno)) = ?', [strtoupper($receipt)])
            ->first();

        if (! $penjualan) {
            $this->error("Receipt not found: {$receipt}");

            return 1;
        }

        $id = (int) $penjualan->id_penjualan;
        $st = strtolower((string) ($penjualan->status ?? ''));
        $bayar = (float) ($penjualan->bayar ?? 0);

        if (($penjualan->sale_type ?? 'normal') === 'management') {
            $this->warn("Skip {$receipt}: management sale.");

            return 0;
        }

        if ($bayar <= 0) {
            $this->warn("Skip {$receipt}: no payment amount (bayar).");

            return 0;
        }

        if ($st === 'completed') {
            $this->line("— {$receipt} (id_penjualan={$id}): already completed — ledger backfill only.");

            if ($dry) {
                $this->line('  Would: run ensureConsignmentLedgerCompleteOnSaleComplete + appendCashPembelianDetailsForPenjualan.');

                return 0;
            }

            return $this->runLedgerBackfillOnly($receipt, $penjualan, $ensure);
        }

        if (! in_array($st, ['active', 'edit_initiated'], true)) {
            $this->info("Skip {$receipt}: status is {$st} (not active/edit_initiated).");

            return 0;
        }

        // Initiate-edit path reverses payment; edit_initiated status always had reversal.
        // active + edit_initiated_at: same (payment reversed when initiate ran; status may have changed).
        // active + no edit_initiated_at: admin "Edit sale" reopen — payment was NOT reversed.
        $paymentWasReversed = $st === 'edit_initiated'
            || ($st === 'active' && Schema::hasColumn('penjualan', 'edit_initiated_at') && $penjualan->edit_initiated_at);

        $this->line("— {$receipt} (id_penjualan={$id}) status={$st} bayar={$bayar} payment_reversed=" . ($paymentWasReversed ? 'yes' : 'no'));

        if ($dry) {
            $this->line('  Would: deduct stock for all lines, set completed, clear edit_initiated_at'
                . ($paymentWasReversed ? ', re-add payment to accounts' : '')
                . ', recalc DailyCash, run ledger backfill.');

            return 0;
        }

        try {
            DB::transaction(function () use ($penjualan, $paymentWasReversed) {
                $penjualan->refresh();
                $st = strtolower((string) ($penjualan->status ?? ''));
                if (! in_array($st, ['active', 'edit_initiated'], true)) {
                    throw new \RuntimeException('Sale is no longer active or edit_initiated (now: '.$st.'). Re-run the command or check the receipt.');
                }

                if ($paymentWasReversed) {
                    $this->reapplyPayment($penjualan);
                }

                $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
                foreach ($details as $item) {
                    $produk = Produk::find($item->id_produk);
                    if (! $produk) {
                        throw new \RuntimeException('Missing product id '.$item->id_produk);
                    }
                    $produk->stok -= (int) $item->jumlah;
                    $produk->save();
                }

                $penjualan->status = 'completed';
                if (Schema::hasColumn('penjualan', 'edit_initiated_at')) {
                    $penjualan->edit_initiated_at = null;
                }
                $penjualan->save();

                $this->recalculateDailyCashForSaleDay($penjualan);
            });
        } catch (\Throwable $e) {
            $this->error("Failed {$receipt}: ".$e->getMessage());

            return 1;
        }

        $penjualan->refresh();

        return $this->runLedgerBackfillOnly($receipt, $penjualan, $ensure);
    }

    private function runLedgerBackfillOnly(string $receipt, Penjualan $penjualan, EnsureSaleSupplierLedgerService $ensure): int
    {
        try {
            $ledger = $ensure->ensureConsignmentLedgerCompleteOnSaleComplete($penjualan, false);
            $appended = $ensure->appendCashPembelianDetailsForPenjualan($penjualan);
        } catch (\Throwable $e) {
            $this->error("Ledger backfill failed for {$receipt}: ".$e->getMessage());

            return 1;
        }

        $invCreated = (int) ($ledger['invoice_items_created_total'] ?? 0);
        $pemHdr = (int) ($ledger['cash_pembelian_created'] ?? 0);
        $cov = (bool) ($ledger['coverage_ok'] ?? true);

        $this->info("{$receipt}: ledger OK. invoice_items_created={$invCreated}, pembelian_headers={$pemHdr}, pembelian_lines_appended={$appended}. Coverage: " . ($cov ? 'yes' : 'NO — check logs'));

        return $cov ? 0 : 1;
    }

    private function reapplyPayment(Penjualan $penjualan): void
    {
        $saleType = $penjualan->sale_type ?? 'normal';
        if ($saleType === 'management' || (float) ($penjualan->bayar ?? 0) <= 0) {
            return;
        }

        $paymentMethod = $penjualan->payment_method ?? 'Cash';
        if ($paymentMethod === 'Split' && Schema::hasColumn('penjualan', 'payment_split_details') && $penjualan->payment_split_details) {
            $split = json_decode($penjualan->payment_split_details, true);
            if (is_array($split)) {
                foreach (['Cash' => $split['cash'] ?? 0, 'Mpesa' => $split['mpesa'] ?? 0, 'Card' => $split['card'] ?? 0] as $accountName => $amount) {
                    if ($amount > 0) {
                        $account = Account::getByName($accountName);
                        if ($account) {
                            $account->addAmount((float) $amount);
                        }
                    }
                }
            }
        } else {
            $account = Account::getByName($paymentMethod);
            if ($account) {
                $account->addAmount((float) $penjualan->bayar);
            }
        }
    }

    private function recalculateDailyCashForSaleDay(Penjualan $penjualan): void
    {
        if (! Schema::hasTable('daily_cash')) {
            return;
        }

        $saleDate = Carbon::parse($penjualan->created_at)->format('Y-m-d');
        $dailyCash = DailyCash::whereDate('date', $saleDate)->first();
        if (! $dailyCash) {
            return;
        }

        $query = Penjualan::whereDate('created_at', $saleDate);
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        $totalSales = (float) $query->sum('bayar');
        $dailyCash->total_sales = $totalSales;
        $dailyCash->net_sales = $totalSales - (float) ($dailyCash->opening_cash ?? 0);
        $dailyCash->save();
    }
}
