<?php

namespace App\Services;

use App\Models\ConsignmentGapSuppression;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Creates missing consignment invoice_items (and cash Pembelian when absent) for a single sale.
 * Only inserts rows — never deletes or updates unrelated sales.
 */
class EnsureSaleSupplierLedgerService
{
    /** Sales on/after this date post to consignment/cash only after sale-list confirmation. */
    public const LEDGER_DEFER_FROM_DATE = '2026-06-01';

    /** @var list<int>|null */
    private ?array $unconfirmedDeferredCashPembelianDetailIdsCache = null;

    public function saleDefersLedgerUntilConfirmed(Penjualan $penjualan): bool
    {
        $saleDate = $penjualan->saledate ?? $penjualan->created_at;
        $dateOnly = Carbon::parse($saleDate)->toDateString();

        return $dateOnly >= self::LEDGER_DEFER_FROM_DATE;
    }

    public function detailIsConfirmedForLedger(PenjualanDetail $detail): bool
    {
        if (! Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            return true;
        }

        return ($detail->item_confirmation_status ?? 'pending') === 'confirmed';
    }

    public function detailEligibleForLedgerPosting(Penjualan $penjualan, PenjualanDetail $detail): bool
    {
        if (! $this->saleDefersLedgerUntilConfirmed($penjualan)) {
            return true;
        }

        return $this->detailIsConfirmedForLedger($detail);
    }

    /**
     * Exclude sales-generated cash pembelian_detail rows that still match an unconfirmed sale line
     * (same rules as consignment: deferred sales post to supplier ledger only after confirmation).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function applyConfirmedOnlyFilterToCashPembelianDetailQuery($query)
    {
        $excludedIds = $this->unconfirmedDeferredCashPembelianDetailIds();
        if ($excludedIds === []) {
            return $query;
        }

        return $query->whereNotIn('pembelian_detail.id_pembelian_detail', $excludedIds);
    }

    /**
     * pembelian_detail ids that mirror unconfirmed sale lines (deferred sales only).
     *
     * @return list<int>
     */
    public function unconfirmedDeferredCashPembelianDetailIds(): array
    {
        if ($this->unconfirmedDeferredCashPembelianDetailIdsCache !== null) {
            return $this->unconfirmedDeferredCashPembelianDetailIdsCache;
        }

        if (! Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            return $this->unconfirmedDeferredCashPembelianDetailIdsCache = [];
        }

        return $this->unconfirmedDeferredCashPembelianDetailIdsCache = Cache::remember(
            'cash_unconfirmed_pembelian_detail_ids',
            300,
            fn () => $this->computeUnconfirmedDeferredCashPembelianDetailIds()
        );
    }

    /**
     * @return list<int>
     */
    private function computeUnconfirmedDeferredCashPembelianDetailIds(): array
    {
        $deferFrom = self::LEDGER_DEFER_FROM_DATE;

        // Only ~hundreds of outstanding cash purchases since defer — match in PHP (fast vs joining full history).
        $purchaseLines = DB::table('pembelian_detail as pbd')
            ->join('pembelian as pb', 'pbd.id_pembelian', '=', 'pb.id_pembelian')
            ->join('supplier as s', 'pb.id_supplier', '=', 's.id_supplier')
            ->where('pb.purchasedate2', '>=', $deferFrom)
            ->whereRaw('pb.total_harga - COALESCE(pb.bayar, 0) > 0')
            ->where(function ($cash) {
                $cash->whereRaw('UPPER(TRIM(s.mop)) = ?', ['CASH'])
                    ->orWhereRaw("s.nama LIKE '% (Cash)'");
            })
            ->select(
                'pbd.id_pembelian_detail',
                'pbd.id_produk',
                'pbd.jumlah',
                'pb.id_supplier',
                'pb.purchasedate2'
            )
            ->get();

        if ($purchaseLines->isEmpty()) {
            return [];
        }

        $pendingSales = DB::table('penjualan_detail as pd')
            ->join('penjualan as pj', 'pd.id_penjualan', '=', 'pj.id_penjualan')
            ->join('produk as pr', 'pd.id_produk', '=', 'pr.id_produk')
            ->where(function ($dateQ) use ($deferFrom) {
                $dateQ->where('pj.saledate', '>=', $deferFrom)
                    ->orWhere(function ($fallback) use ($deferFrom) {
                        $fallback->whereNull('pj.saledate')
                            ->whereDate('pj.created_at', '>=', $deferFrom);
                    });
            })
            ->where(function ($iq) {
                $iq->whereNull('pd.item_confirmation_status')
                    ->orWhere('pd.item_confirmation_status', 'pending')
                    ->orWhere('pd.item_confirmation_status', 'defect');
            })
            ->when(Schema::hasColumn('penjualan', 'status'), function ($q) {
                $q->where('pj.status', 'completed');
            })
            ->select(
                'pr.id_supplier',
                'pd.id_produk',
                'pd.jumlah',
                DB::raw('COALESCE(pj.saledate, DATE(pj.created_at)) as sale_date')
            )
            ->get();

        if ($pendingSales->isEmpty()) {
            return [];
        }

        $pendingKeys = [];
        foreach ($pendingSales as $row) {
            $pendingKeys[$this->cashSaleMatchKey(
                (int) $row->id_supplier,
                (int) $row->id_produk,
                (int) $row->jumlah,
                (string) $row->sale_date
            )] = true;
        }

        $excluded = [];
        foreach ($purchaseLines as $line) {
            $key = $this->cashSaleMatchKey(
                (int) $line->id_supplier,
                (int) $line->id_produk,
                (int) $line->jumlah,
                (string) $line->purchasedate2
            );
            if (isset($pendingKeys[$key])) {
                $excluded[] = (int) $line->id_pembelian_detail;
            }
        }

        return $excluded;
    }

    private function cashSaleMatchKey(int $supplierId, int $produkId, int $qty, string $saleDate): string
    {
        return $supplierId.'|'.$produkId.'|'.$qty.'|'.Carbon::parse($saleDate)->toDateString();
    }

    /** Call after receipt confirmation so the cash payments list updates promptly. */
    public function forgetUnconfirmedCashPembelianDetailIdsCache(): void
    {
        $this->unconfirmedDeferredCashPembelianDetailIdsCache = null;
        Cache::forget('cash_unconfirmed_pembelian_detail_ids');
    }

    public function pembelianDetailIsFromUnconfirmedDeferredSale(PembelianDetail $detail, Pembelian $pembelian): bool
    {
        return in_array(
            (int) $detail->id_pembelian_detail,
            $this->unconfirmedDeferredCashPembelianDetailIds(),
            true
        );
    }

    public function confirmedSaleDetailQtyForProduct(int $penjualanId, int $produkId): int
    {
        $query = PenjualanDetail::query()
            ->where('id_penjualan', $penjualanId)
            ->where('id_produk', $produkId);

        if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            $query->where('item_confirmation_status', 'confirmed');
        }

        return (int) $query->sum('jumlah');
    }

    public function effectiveSaleDetailQtyForProduct(Penjualan $penjualan, int $produkId): int
    {
        if ($this->saleDefersLedgerUntilConfirmed($penjualan)) {
            return $this->confirmedSaleDetailQtyForProduct((int) $penjualan->id_penjualan, $produkId);
        }

        return $this->saleDetailQtyForProduct((int) $penjualan->id_penjualan, $produkId);
    }

    /**
     * Post consignment or cash ledger rows for confirmed sale-list line(s).
     *
     * @return array{consignment: int, cash: int}
     */
    public function postConfirmedDetailsToSupplierLedger(Penjualan $penjualan, ?array $detailIds = null): array
    {
        // Deferred sales skip cash pembelian at POS; create supplier/day headers before appending lines.
        $this->fillCashPembelianIfAbsentForPenjualan($penjualan);

        $query = PenjualanDetail::query()
            ->where('id_penjualan', $penjualan->id_penjualan);

        if ($detailIds !== null && $detailIds !== []) {
            $query->whereIn('id_penjualan_detail', $detailIds);
        }

        $summary = ['consignment' => 0, 'cash' => 0];
        foreach ($query->get() as $detail) {
            if (! $this->detailIsConfirmedForLedger($detail)) {
                continue;
            }
            $result = $this->postDetailToSupplierLedger($penjualan, $detail);
            if ($result['consignment']) {
                $summary['consignment']++;
            }
            if ($result['cash']) {
                $summary['cash']++;
            }
        }

        return $summary;
    }

    /**
     * @return array{consignment: bool, cash: bool}
     */
    public function postDetailToSupplierLedger(Penjualan $penjualan, PenjualanDetail $detail): array
    {
        $result = ['consignment' => false, 'cash' => false];

        if (! $this->detailIsConfirmedForLedger($detail)) {
            return $result;
        }

        $produk = $detail->produk ?? Produk::find($detail->id_produk);
        if (! $produk) {
            return $result;
        }

        if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
            return $result;
        }

        $supplier = $produk->supplier ?? Supplier::find($produk->id_supplier);
        if (! $supplier) {
            return $result;
        }

        if ($this->isCashSupplier($supplier)) {
            $added = $this->appendCashPembelianDetailsForPenjualan($penjualan, (int) $detail->id_penjualan_detail) > 0;

            return ['consignment' => false, 'cash' => $added];
        }

        if (strtoupper(trim((string) ($supplier->mop ?? ''))) !== 'CONSIGNMENT') {
            return $result;
        }

        $diskon = Schema::hasColumn('penjualan_detail', 'diskon') ? (int) ($detail->diskon ?? 0) : 0;
        $posted = $this->createConsignmentInvoiceItemIfNeeded(
            $penjualan,
            $produk,
            $supplier,
            (int) $detail->jumlah,
            $diskon
        );
        $result['consignment'] = $posted !== null;

        return $result;
    }

    /**
     * Sum of consignment ledger qty already posted for this sale line (all suppliers if $supplierId null).
     */
    public function ledgerQtyForSaleProduct(int $penjualanId, int $produkId, ?int $supplierId = null): int
    {
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return 0;
        }

        $query = InvoiceItem::query()
            ->where('penjualan_id', $penjualanId)
            ->where('produk_id', $produkId);

        if ($supplierId !== null && $supplierId > 0) {
            $query->where('supplier_id', $supplierId);
        }

        return (int) $query->sum('quantity');
    }

    public function saleDetailQtyForProduct(int $penjualanId, int $produkId): int
    {
        return (int) PenjualanDetail::query()
            ->where('id_penjualan', $penjualanId)
            ->where('id_produk', $produkId)
            ->sum('jumlah');
    }

    /**
     * How many units may still be posted to consignment for this sale + product + supplier.
     */
    public function remainingConsignmentQtyToPost(int $penjualanId, int $produkId, int $supplierId, ?Penjualan $penjualan = null): int
    {
        $pen = $penjualan ?? Penjualan::find($penjualanId);
        $detailQty = $pen
            ? $this->effectiveSaleDetailQtyForProduct($pen, $produkId)
            : $this->saleDetailQtyForProduct($penjualanId, $produkId);
        $ledgerQty = $this->ledgerQtyForSaleProduct($penjualanId, $produkId, $supplierId);

        return max(0, $detailQty - $ledgerQty);
    }

    /**
     * Create one consignment invoice_item only when sale qty is not already fully posted.
     * Uses a row lock on the sale to avoid double-posting under concurrent requests.
     *
     * @return InvoiceItem|null Created row, or null when ledger already covers the sale qty
     */
    public function createConsignmentInvoiceItemIfNeeded(
        Penjualan $penjualan,
        Produk $produk,
        Supplier $supplier,
        int $requestedQty,
        int $diskon = 0
    ): ?InvoiceItem {
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return null;
        }

        if ($requestedQty <= 0) {
            return null;
        }

        if (strtoupper(trim((string) ($supplier->mop ?? ''))) !== 'CONSIGNMENT') {
            return null;
        }

        if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
            return null;
        }

        $unitCost = (float) ($produk->harga_beli ?? 0);
        if ($unitCost <= 0) {
            return null;
        }

        if ($this->isConsignmentGapSuppressed(
            (int) $penjualan->id_penjualan,
            (int) $produk->id_produk,
            (int) $supplier->id_supplier
        )) {
            return null;
        }

        return DB::transaction(function () use ($penjualan, $produk, $supplier, $requestedQty, $diskon, $unitCost) {
            Penjualan::query()
                ->where('id_penjualan', $penjualan->id_penjualan)
                ->lockForUpdate()
                ->first();

            $remaining = $this->remainingConsignmentQtyToPost(
                (int) $penjualan->id_penjualan,
                (int) $produk->id_produk,
                (int) $supplier->id_supplier,
                $penjualan
            );

            if ($remaining <= 0) {
                Log::debug('Consignment ledger guard: skip duplicate post', [
                    'penjualan_id' => $penjualan->id_penjualan,
                    'receiptno' => $penjualan->receiptno,
                    'produk_id' => $produk->id_produk,
                    'supplier_id' => $supplier->id_supplier,
                ]);

                return null;
            }

            $qty = min($requestedQty, $remaining);
            $amount = round($unitCost * $qty, 2);
            if ($amount <= 0) {
                return null;
            }

            $saleDate = $penjualan->saledate ?? $penjualan->created_at;
            $dateOnly = Carbon::parse($saleDate)->toDateString();
            $saleDateTime = Carbon::parse($dateOnly)->startOfDay();

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
                'created_at' => $saleDateTime->toDateTimeString(),
                'updated_at' => $saleDateTime->toDateTimeString(),
            ]);

            $invoice->total = (float) $invoice->total + $amount;
            $invoice->save();

            return $item->fresh();
        });
    }

    public function isCashSupplier(?Supplier $supplier): bool
    {
        if (! $supplier) {
            return false;
        }
        if (strtoupper(trim((string) ($supplier->mop ?? ''))) === 'CASH') {
            return true;
        }

        return stripos((string) ($supplier->nama ?? ''), '(Cash)') !== false;
    }

    /**
     * Each entry is one missing consignment invoice_item to create for this sale.
     *
     * @return list<array{produk: Produk, supplier: Supplier, qty: int, amount: float, diskon: int, detail: PenjualanDetail}>
     */
    public function collectMissingConsignmentGaps(Penjualan $penjualan, ?int $restrictSupplierId = null): array
    {
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return [];
        }

        $gaps = [];
        $detailQuery = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan);
        if ($restrictSupplierId !== null && $restrictSupplierId > 0) {
            $detailQuery->whereIn('id_produk', function ($q) use ($restrictSupplierId) {
                $q->select('id_produk')->from('produk')->where('id_supplier', $restrictSupplierId);
            });
        }
        $details = $detailQuery->orderBy('id_penjualan_detail')->get();

        // Track qty already scheduled in this pass so we never book more consignment stock than the sale.
        $allocatedThisRun = [];

        foreach ($details as $detail) {
            if (! $this->detailEligibleForLedgerPosting($penjualan, $detail)) {
                continue;
            }

            $produk = Produk::find($detail->id_produk);
            if (! $produk || ! $produk->id_supplier) {
                continue;
            }

            $supplier = Supplier::find($produk->id_supplier);
            if (! $supplier) {
                continue;
            }
            $mop = strtoupper(trim((string) ($supplier->mop ?? '')));
            if ($mop !== 'CONSIGNMENT') {
                continue;
            }

            if ($this->isConsignmentGapSuppressed((int) $penjualan->id_penjualan, (int) $produk->id_produk, (int) $supplier->id_supplier)) {
                continue;
            }

            $lineQty = (int) $detail->jumlah;
            if ($lineQty <= 0) {
                continue;
            }

            if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
                continue;
            }

            $unitCost = (float) ($produk->harga_beli ?? 0);
            if ($unitCost <= 0) {
                continue;
            }

            $allocKey = $produk->id_produk.'_'.$supplier->id_supplier;

            $detailQtySum = $this->effectiveSaleDetailQtyForProduct($penjualan, (int) $produk->id_produk);

            $invoiceQtySum = (int) InvoiceItem::where('penjualan_id', $penjualan->id_penjualan)
                ->where('produk_id', $produk->id_produk)
                ->where('supplier_id', $supplier->id_supplier)
                ->sum('quantity');

            $scheduled = (int) ($allocatedThisRun[$allocKey] ?? 0);
            $remaining = $detailQtySum - $invoiceQtySum - $scheduled;
            if ($remaining <= 0) {
                continue;
            }

            $gapQty = min($lineQty, $remaining);
            if ($gapQty <= 0) {
                continue;
            }

            $amount = round($unitCost * $gapQty, 2);
            if ($amount <= 0) {
                continue;
            }

            $diskon = 0;
            if (Schema::hasColumn('penjualan_detail', 'diskon')) {
                $diskon = (int) ($detail->diskon ?? 0);
            }

            $gaps[] = [
                'produk' => $produk,
                'supplier' => $supplier,
                'qty' => $gapQty,
                'amount' => $amount,
                'diskon' => $diskon,
                'detail' => $detail,
            ];
            $allocatedThisRun[$allocKey] = $scheduled + $gapQty;
        }

        return $gaps;
    }

    /**
     * Sale lines excluded from consignment invoice_item generation (e.g. align supplier statements).
     */
    public function isConsignmentGapSuppressed(int $penjualanId, int $produkId, int $supplierId): bool
    {
        if (! Schema::hasTable('consignment_gap_suppressions')) {
            return false;
        }

        return ConsignmentGapSuppression::query()
            ->where('penjualan_id', $penjualanId)
            ->where('produk_id', $produkId)
            ->where('supplier_id', $supplierId)
            ->exists();
    }

    /**
     * Compare consignment-relevant sale lines to invoice_items for this penjualan (by produk + supplier).
     *
     * @return array{ok: bool, shortfalls: list<array>, excluded: list<array>}
     */
    public function buildConsignmentCoverageReport(Penjualan $penjualan): array
    {
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return ['ok' => true, 'shortfalls' => [], 'excluded' => [], 'skipped' => 'no_penjualan_id_column'];
        }

        $shortfalls = [];
        $excluded = [];
        $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        $grouped = [];

        foreach ($details as $detail) {
            $produk = Produk::find($detail->id_produk);
            if (! $produk || ! $produk->id_supplier) {
                continue;
            }
            $supplier = Supplier::find($produk->id_supplier);
            if (! $supplier || strtoupper(trim((string) ($supplier->mop ?? ''))) !== 'CONSIGNMENT') {
                continue;
            }
            $key = $produk->id_produk.'_'.$supplier->id_supplier;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'produk_id' => (int) $produk->id_produk,
                    'kode_produk' => (string) ($produk->kode_produk ?? ''),
                    'supplier_id' => (int) $supplier->id_supplier,
                    'supplier_nama' => (string) ($supplier->nama ?? ''),
                    'detail_qty' => 0,
                ];
            }
            $grouped[$key]['detail_qty'] += (int) $detail->jumlah;
        }

        foreach ($grouped as $row) {
            $produk = Produk::find($row['produk_id']);
            if (! $produk) {
                continue;
            }
            if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
                $excluded[] = array_merge($row, ['reason' => 'incomplete_product']);

                continue;
            }
            if ((float) ($produk->harga_beli ?? 0) <= 0) {
                $excluded[] = array_merge($row, ['reason' => 'zero_buying_price']);

                continue;
            }
            if ($this->isConsignmentGapSuppressed((int) $penjualan->id_penjualan, $row['produk_id'], $row['supplier_id'])) {
                $excluded[] = array_merge($row, ['reason' => 'suppressed']);

                continue;
            }

            $invQty = (int) InvoiceItem::where('penjualan_id', $penjualan->id_penjualan)
                ->where('produk_id', $row['produk_id'])
                ->where('supplier_id', $row['supplier_id'])
                ->sum('quantity');

            if ($row['detail_qty'] > $invQty) {
                $shortfalls[] = [
                    'produk_id' => $row['produk_id'],
                    'kode_produk' => $row['kode_produk'],
                    'supplier_id' => $row['supplier_id'],
                    'supplier_nama' => $row['supplier_nama'],
                    'detail_qty' => $row['detail_qty'],
                    'invoice_qty' => $invQty,
                    'short' => $row['detail_qty'] - $invQty,
                ];
            }
        }

        return [
            'ok' => count($shortfalls) === 0,
            'shortfalls' => $shortfalls,
            'excluded' => $excluded,
        ];
    }

    /**
     * Run gap fill repeatedly (multi-line baskets can need more than one pass if DB state is inconsistent).
     *
     * @return int total invoice_item rows created
     */
    public function fillConsignmentGapsWithRetries(Penjualan $penjualan, ?int $restrictSupplierId = null, int $maxPasses = 5): int
    {
        $total = 0;
        for ($i = 0; $i < $maxPasses; $i++) {
            $n = $this->fillConsignmentGapsForPenjualan($penjualan, false, $restrictSupplierId);
            $total += $n;
            if ($n === 0) {
                break;
            }
        }

        return $total;
    }

    /**
     * On POS sale completion: backfill consignment gaps (with retries), cash pembelian safety net,
     * verify coverage, log audit trail. Optional dry-run for audits only (no writes).
     *
     * @return array<string, mixed>
     */
    public function ensureConsignmentLedgerCompleteOnSaleComplete(Penjualan $penjualan, bool $dryRun = false): array
    {
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return [
                'dry_run' => $dryRun,
                'skipped' => true,
                'reason' => 'invoice_items.penjualan_id missing',
                'coverage_ok' => true,
            ];
        }

        $before = $this->buildConsignmentCoverageReport($penjualan);

        if ($dryRun) {
            $gaps = $this->collectMissingConsignmentGaps($penjualan);

            return [
                'dry_run' => true,
                'would_create_invoice_items' => count($gaps),
                'coverage_ok_before' => $before['ok'],
                'shortfalls_before' => $before['shortfalls'],
                'excluded_lines' => $before['excluded'],
            ];
        }

        $passes = [];
        $invoiceItemsCreated = 0;
        for ($i = 0; $i < 5; $i++) {
            $n = $this->fillConsignmentGapsForPenjualan($penjualan, false, null);
            $invoiceItemsCreated += $n;
            $passes[] = ['pass' => $i + 1, 'created' => $n];
            if ($n === 0) {
                break;
            }
        }

        // Deferred sales: cash pembelian rows are posted on receipt confirmation only (like consignment).
        $cashCreated = 0;
        if (! $this->saleDefersLedgerUntilConfirmed($penjualan)) {
            $cashCreated = $this->fillCashPembelianIfAbsentForPenjualan($penjualan);
        }

        $after = $this->buildConsignmentCoverageReport($penjualan);

        $payload = [
            'dry_run' => false,
            'invoice_items_created_total' => $invoiceItemsCreated,
            'cash_pembelian_created' => $cashCreated,
            'fill_passes' => $passes,
            'coverage_ok' => $after['ok'],
            'remaining_shortfalls' => $after['shortfalls'],
            'excluded_lines' => $after['excluded'],
        ];

        if ($invoiceItemsCreated > 0 || $cashCreated > 0) {
            Log::warning('Ledger reconciliation: auto-created missing supplier rows for completed sale', [
                'penjualan_id' => $penjualan->id_penjualan,
                'receiptno' => $penjualan->receiptno,
                'invoice_items_created' => $invoiceItemsCreated,
                'pembelian_headers_created' => $cashCreated,
                'fill_passes' => $passes,
                'coverage_ok_after' => $after['ok'],
            ]);
        }

        if (! $after['ok']) {
            Log::error('Ledger reconciliation: consignment coverage INCOMPLETE after sale completion (manual fix required)', [
                'penjualan_id' => $penjualan->id_penjualan,
                'receiptno' => $penjualan->receiptno,
                'remaining_shortfalls' => $after['shortfalls'],
                'excluded_lines' => $after['excluded'],
            ]);
        }

        if (! $before['ok'] && $after['ok'] && $invoiceItemsCreated > 0) {
            Log::info('Ledger reconciliation: coverage restored by auto-backfill', [
                'penjualan_id' => $penjualan->id_penjualan,
                'receiptno' => $penjualan->receiptno,
                'shortfalls_resolved' => $before['shortfalls'],
            ]);
        }

        return $payload;
    }

    /**
     * @param  ?int  $restrictSupplierId  only used when backfilling one supplier’s products on a sale
     * @return int number of invoice_item rows created
     */
    public function fillConsignmentGapsForPenjualan(Penjualan $penjualan, bool $dryRun = false, ?int $restrictSupplierId = null): int
    {
        $gaps = $this->collectMissingConsignmentGaps($penjualan, $restrictSupplierId);
        if ($dryRun) {
            return count($gaps);
        }

        if (count($gaps) === 0) {
            return 0;
        }

        $created = 0;
        foreach ($gaps as $gap) {
            /** @var Produk $produk */
            $produk = $gap['produk'];
            /** @var Supplier $supplier */
            $supplier = $gap['supplier'];
            $diskon = $gap['diskon'];

            $item = $this->createConsignmentInvoiceItemIfNeeded(
                $penjualan,
                $produk,
                $supplier,
                (int) $gap['qty'],
                $diskon
            );

            if ($item !== null) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * If this sale has cash-supplier lines but no Pembelian exists for that supplier on the sale day, create one.
     * Does not modify existing Pembelian rows.
     *
     * @return int number of Pembelian headers created
     */
    public function fillCashPembelianIfAbsentForPenjualan(Penjualan $penjualan): int
    {
        $saleDate = $penjualan->saledate ?? $penjualan->created_at;
        $dateOnly = Carbon::parse($saleDate)->toDateString();
        $saleDateTime = Carbon::parse($dateOnly)->startOfDay();
        // Must match $dateOnly (used for hasPembelian + append). Using now() when saledate was null
        // left Pembelian on the wrong day so lines never appeared in Cash Generated Sales.
        $purchasedate2 = $penjualan->saledate
            ? Carbon::parse($penjualan->saledate)->toDateString()
            : $dateOnly;

        $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)
            ->with('produk.supplier')
            ->get();

        $bySupplier = [];
        foreach ($details as $detail) {
            if (! $this->detailEligibleForLedgerPosting($penjualan, $detail)) {
                continue;
            }

            $produk = $detail->produk;
            if (! $produk) {
                continue;
            }
            $supplier = $produk->supplier;
            if (! $this->isCashSupplier($supplier)) {
                continue;
            }
            if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
                continue;
            }
            $sid = (int) $produk->id_supplier;
            if (! isset($bySupplier[$sid])) {
                $bySupplier[$sid] = [];
            }
            $bySupplier[$sid][] = ['item' => $detail, 'produk' => $produk];
        }

        $created = 0;
        foreach ($bySupplier as $supplierId => $lines) {
            $eligibleLines = array_values(array_filter($lines, function ($line) use ($penjualan) {
                /** @var PenjualanDetail $item */
                $item = $line['item'];

                return $this->detailEligibleForLedgerPosting($penjualan, $item);
            }));
            if ($eligibleLines === []) {
                continue;
            }

            $hasPembelian = Pembelian::where('id_supplier', $supplierId)
                ->whereDate('purchasedate2', $dateOnly)
                ->exists();
            if ($hasPembelian) {
                continue;
            }

            $totalHarga = 0;
            $totalItem = 0;
            $pembelianDetails = [];
            foreach ($eligibleLines as $line) {
                /** @var PenjualanDetail $item */
                $item = $line['item'];
                $produk = $line['produk'];
                $subtotal = (int) round((float) $item->jumlah * (float) $produk->harga_beli);
                $totalHarga += $subtotal;
                $totalItem += $item->jumlah;
                $pembelianDetails[] = [
                    'produk' => $produk,
                    'jumlah' => $item->jumlah,
                    'harga_beli' => $produk->harga_beli,
                    'subtotal' => $subtotal,
                ];
            }

            if ($totalHarga <= 0) {
                continue;
            }

            $pembelian = Pembelian::create([
                'id_supplier' => $supplierId,
                'total_item' => $totalItem,
                'total_harga' => $totalHarga,
                'reorder' => 0,
                'bayar' => 0,
                'purchasedate2' => $purchasedate2,
                'created_at' => $saleDateTime,
                'updated_at' => $saleDateTime,
            ]);

            foreach ($pembelianDetails as $detail) {
                PembelianDetail::create([
                    'id_pembelian' => $pembelian->id_pembelian,
                    'id_produk' => $detail['produk']->id_produk,
                    'harga_beli' => $detail['harga_beli'],
                    'jumlah' => $detail['jumlah'],
                    'subtotal' => $detail['subtotal'],
                ]);
            }
            $created++;
        }

        return $created;
    }

    /**
     * When a Pembelian header already exists for this supplier/date (e.g. another sale the same day),
     * append pembelian_detail rows for this sale's cash-supplier lines that are not yet represented.
     *
     * @return int number of pembelian_detail rows inserted
     */
    public function appendCashPembelianDetailsForPenjualan(Penjualan $penjualan, ?int $restrictDetailId = null): int
    {
        $detailsQuery = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)
            ->with('produk.supplier')
            ->orderBy('id_penjualan_detail');

        if ($restrictDetailId !== null && $restrictDetailId > 0) {
            $detailsQuery->where('id_penjualan_detail', $restrictDetailId);
        }

        $details = $detailsQuery->get();

        $pembelianCache = [];
        $added = 0;

        foreach ($details as $detail) {
            if (! $this->detailEligibleForLedgerPosting($penjualan, $detail)) {
                continue;
            }

            $produk = $detail->produk;
            if (! $produk) {
                continue;
            }
            $supplier = $produk->supplier;
            if (! $this->isCashSupplier($supplier)) {
                continue;
            }
            if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
                continue;
            }
            $unit = (float) ($produk->harga_beli ?? 0);
            if ($unit <= 0) {
                continue;
            }

            $sid = (int) $produk->id_supplier;
            if (! isset($pembelianCache[$sid])) {
                $pembelianCache[$sid] = $this->findPembelianForCashSupplierOnSaleDay($sid, $penjualan);
            }
            $pembelian = $pembelianCache[$sid];
            if (! $pembelian) {
                $this->fillCashPembelianIfAbsentForPenjualan($penjualan);
                $pembelianCache[$sid] = $this->findPembelianForCashSupplierOnSaleDay($sid, $penjualan);
                $pembelian = $pembelianCache[$sid];
            }
            if (! $pembelian) {
                continue;
            }

            $qty = (int) $detail->jumlah;
            $unitInt = (int) round($unit);
            $subtotal = (int) round($qty * $unitInt);

            $matchCount = (int) PembelianDetail::where('id_pembelian', $pembelian->id_pembelian)
                ->where('id_produk', $produk->id_produk)
                ->where('jumlah', $qty)
                ->where('harga_beli', $unitInt)
                ->count();

            $needCount = (int) PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)
                ->where('id_produk', $produk->id_produk)
                ->where('jumlah', $qty)
                ->whereHas('produk', function ($q) use ($sid) {
                    $q->where('id_supplier', $sid);
                })
                ->count();

            if ($matchCount >= $needCount) {
                continue;
            }

            PembelianDetail::create([
                'id_pembelian' => $pembelian->id_pembelian,
                'id_produk' => $produk->id_produk,
                'harga_beli' => $unitInt,
                'jumlah' => $qty,
                'subtotal' => $subtotal,
            ]);

            $pembelian->total_item = (int) ($pembelian->total_item ?? 0) + $qty;
            $pembelian->total_harga = (int) ($pembelian->total_harga ?? 0) + $subtotal;
            $pembelian->save();
            $added++;
        }

        return $added;
    }

    /**
     * First Pembelian header for this supplier on the sale calendar day (same date rule as fillCashPembelianIfAbsentForPenjualan).
     */
    private function findPembelianForCashSupplierOnSaleDay(int $supplierId, Penjualan $penjualan): ?Pembelian
    {
        $saleDate = $penjualan->saledate ?? $penjualan->created_at;
        $dateOnly = Carbon::parse($saleDate)->toDateString();

        return Pembelian::query()
            ->where('id_supplier', $supplierId)
            ->whereDate('purchasedate2', $dateOnly)
            ->orderBy('id_pembelian')
            ->first();
    }
}
