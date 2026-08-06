<?php

namespace App\Services;

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Finds completed sale lines that are excluded from consignment / cash-purchase ledgers
 * because buying price is missing (or supplier unassigned for those flows).
 */
class MissingBuyingPriceLedgerService
{
    public function __construct(
        private EnsureSaleSupplierLedgerService $ensure,
    ) {}

    public function baseDetailsQuery(string $context): Builder
    {
        $context = $context === 'cash' ? 'cash' : 'consignment';

        $q = PenjualanDetail::query()
            ->join('penjualan', 'penjualan_detail.id_penjualan', '=', 'penjualan.id_penjualan')
            ->leftJoin('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('supplier', 'produk.id_supplier', '=', 'supplier.id_supplier')
            ->whereNotNull('produk.id_produk');

        if (Schema::hasColumn('produk', 'is_incomplete')) {
            $q->whereRaw('(produk.is_incomplete = 0 OR produk.is_incomplete IS NULL)');
        }

        if (Schema::hasColumn('penjualan', 'status')) {
            $q->where('penjualan.status', 'completed');
        }

        if (Schema::hasColumn('penjualan', 'sale_type')) {
            $q->where(function ($w) {
                $w->whereNull('penjualan.sale_type')
                    ->orWhere('penjualan.sale_type', '!=', 'management');
            });
        }

        if ($context === 'consignment') {
            $q->where(function ($w) {
                $w->whereNull('produk.id_supplier')
                    ->orWhereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CONSIGNMENT']);
            });
        } else {
            $q->where(function ($w) {
                $w->whereNull('produk.id_supplier')
                    ->orWhereRaw('UPPER(TRIM(supplier.mop)) = ?', ['CASH'])
                    ->orWhereRaw("supplier.nama LIKE '% (Cash)'");
            });
        }

        $q->where(function ($w) {
            $w->whereNull('produk.harga_beli')
                ->orWhere('produk.harga_beli', '<=', 0);
        });

        return $q->select('penjualan_detail.*')->orderByDesc('penjualan_detail.id_penjualan_detail');
    }

    public function countForContext(string $context): int
    {
        return (int) $this->baseDetailsQuery($context)->count();
    }

    public function validateSupplierMatchesAccountingType(Supplier $supplier, string $accountingType): bool
    {
        if ($accountingType === 'consignment') {
            return strtoupper(trim((string) ($supplier->mop ?? ''))) === 'CONSIGNMENT';
        }

        return $this->ensure->isCashSupplier($supplier);
    }

    /**
     * After produk is updated, push ledger rows for this sale.
     */
    public function syncLedgersForPenjualanAfterProductFix(Penjualan $penjualan, Supplier $supplier): void
    {
        if (strtoupper(trim((string) ($supplier->mop ?? ''))) === 'CONSIGNMENT') {
            $this->ensure->fillConsignmentGapsWithRetries($penjualan, null, 5);

            return;
        }

        if ($this->ensure->isCashSupplier($supplier)) {
            $this->ensure->fillCashPembelianIfAbsentForPenjualan($penjualan);
            $this->ensure->appendCashPembelianDetailsForPenjualan($penjualan);
        }
    }
}
