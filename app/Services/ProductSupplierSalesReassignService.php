<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a product's supplier changes, optionally move completed-sale ledger rows
 * (consignment invoice_items / cash-generated pembelian) to the new supplier.
 */
class ProductSupplierSalesReassignService
{
    public function __construct(
        private EnsureSaleSupplierLedgerService $ledger,
        private SaleSupplierLedgerRemovalService $removal
    ) {}

    /**
     * @return array{
     *   sale_lines: int,
     *   consignment_unpaid_lines: int,
     *   cash_unpaid_lines: int,
     *   paid_consignment_lines: int,
     *   paid_cash_lines: int,
     *   old_supplier_name: string,
     *   new_supplier_name: string,
     *   new_supplier_mop: string
     * }
     */
    public function preview(int $produkId, int $oldSupplierId, int $newSupplierId): array
    {
        $oldSupplier = $oldSupplierId > 0 ? Supplier::find($oldSupplierId) : null;
        $newSupplier = Supplier::find($newSupplierId);

        $saleLines = 0;
        $consignmentUnpaid = 0;
        $cashUnpaid = 0;
        $paidConsignment = 0;
        $paidCash = 0;

        foreach ($this->completedSaleDetailsForProduct($produkId) as $detail) {
            $saleLines++;
            $penjualan = Penjualan::find($detail->id_penjualan);
            if (! $penjualan) {
                continue;
            }

            if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
                $items = InvoiceItem::query()
                    ->where('penjualan_id', $detail->id_penjualan)
                    ->where('produk_id', $detail->id_produk)
                    ->get();
                foreach ($items as $item) {
                    if ((float) ($item->amount_paid ?? 0) > 0.0001) {
                        $paidConsignment++;

                        continue;
                    }
                    $consignmentUnpaid++;
                }
            }

            if ($oldSupplierId > 0 && $this->ledger->isCashSupplier($oldSupplier)) {
                $pd = $this->removal->firstMatchingCashPembelianDetail($detail, $penjualan, $oldSupplierId);
                if ($pd && $pd->pembelian) {
                    if ((float) ($pd->pembelian->bayar ?? 0) > 0.0001) {
                        $paidCash++;
                    } else {
                        $cashUnpaid++;
                    }
                }
            }
        }

        return [
            'sale_lines' => $saleLines,
            'consignment_unpaid_lines' => $consignmentUnpaid,
            'cash_unpaid_lines' => $cashUnpaid,
            'paid_consignment_lines' => $paidConsignment,
            'paid_cash_lines' => $paidCash,
            'old_supplier_name' => $oldSupplier ? (string) $oldSupplier->nama : 'None',
            'new_supplier_name' => $newSupplier ? (string) $newSupplier->nama : 'Unknown',
            'new_supplier_mop' => strtoupper(trim((string) ($newSupplier->mop ?? ''))),
        ];
    }

    /**
     * @return array{
     *   sale_lines_processed: int,
     *   consignment_removed: int,
     *   consignment_created: int,
     *   cash_removed: int,
     *   cash_created: int,
     *   skipped_paid: int,
     *   errors: list<string>
     * }
     */
    public function apply(Produk $produk, int $oldSupplierId): array
    {
        $newSupplier = Supplier::find((int) $produk->id_supplier);
        if (! $newSupplier) {
            return [
                'sale_lines_processed' => 0,
                'consignment_removed' => 0,
                'consignment_created' => 0,
                'cash_removed' => 0,
                'cash_created' => 0,
                'skipped_paid' => 0,
                'errors' => ['New supplier not found.'],
            ];
        }

        $counts = [
            'sale_lines_processed' => 0,
            'consignment_removed' => 0,
            'consignment_created' => 0,
            'cash_removed' => 0,
            'cash_created' => 0,
            'skipped_paid' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($produk, $oldSupplierId, $newSupplier, &$counts) {
            foreach ($this->completedSaleDetailsForProduct((int) $produk->id_produk) as $detail) {
                $penjualan = Penjualan::find($detail->id_penjualan);
                if (! $penjualan) {
                    continue;
                }

                $counts['sale_lines_processed']++;

                $lineHasPaidLedger = false;

                if (Schema::hasColumn('invoice_items', 'penjualan_id')) {
                    $lineHasPaidLedger = InvoiceItem::query()
                        ->where('penjualan_id', $detail->id_penjualan)
                        ->where('produk_id', $detail->id_produk)
                        ->whereRaw('COALESCE(amount_paid, 0) > 0.0001')
                        ->exists();
                    if ($lineHasPaidLedger) {
                        $counts['skipped_paid']++;
                    } else {
                        $counts['consignment_removed'] += $this->removeAllUnpaidConsignmentForLine($detail);
                    }
                }

                if ($oldSupplierId > 0) {
                    $oldSup = Supplier::find($oldSupplierId);
                    if ($oldSup && $this->ledger->isCashSupplier($oldSup)) {
                        $pd = $this->removal->firstMatchingCashPembelianDetail($detail, $penjualan, $oldSupplierId);
                        if ($pd && $pd->pembelian && (float) ($pd->pembelian->bayar ?? 0) > 0.0001) {
                            if (! $lineHasPaidLedger) {
                                $counts['skipped_paid']++;
                            }
                            $lineHasPaidLedger = true;
                        } elseif ($pd && ! $lineHasPaidLedger) {
                            $this->removal->removeCashPembelianDetailForDetail($detail, $penjualan, $oldSupplierId);
                            $counts['cash_removed']++;
                        }
                    }
                }

                if ($lineHasPaidLedger) {
                    continue;
                }

                $posted = $this->postLedgerForLine($produk, $detail, $penjualan, $newSupplier);
                $counts['consignment_created'] += $posted['consignment'];
                $counts['cash_created'] += $posted['cash'];
            }
        });

        return $counts;
    }

    /**
     * @return \Illuminate\Support\Collection<int, PenjualanDetail>
     */
    private function completedSaleDetailsForProduct(int $produkId)
    {
        $q = PenjualanDetail::query()->where('id_produk', $produkId);
        if (Schema::hasColumn('penjualan', 'status')) {
            $q->whereIn('id_penjualan', function ($sub) {
                $sub->select('id_penjualan')->from('penjualan')->where('status', 'completed');
            });
        }

        return $q->orderBy('id_penjualan_detail')->get();
    }

    private function removeAllUnpaidConsignmentForLine(PenjualanDetail $detail): int
    {
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return 0;
        }

        $items = InvoiceItem::query()
            ->where('penjualan_id', $detail->id_penjualan)
            ->where('produk_id', $detail->id_produk)
            ->where(function ($q) {
                $q->where('amount_paid', 0)
                    ->orWhereNull('amount_paid')
                    ->orWhereRaw('COALESCE(amount_paid, 0) = 0');
            })
            ->whereRaw('COALESCE(balance, amount) = amount')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $invoiceIds = $items->pluck('invoice_id')->unique()->filter()->values()->all();
        foreach ($items as $item) {
            $item->delete();
        }

        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::find((int) $invoiceId);
            if (! $invoice) {
                continue;
            }
            if ((int) InvoiceItem::where('invoice_id', $invoiceId)->count() === 0) {
                $invoice->delete();
            } else {
                $invoice->total = (float) InvoiceItem::where('invoice_id', $invoiceId)->sum('amount');
                $invoice->save();
            }
        }

        return $items->count();
    }

    /**
     * @return array{consignment: int, cash: int}
     */
    private function postLedgerForLine(Produk $produk, PenjualanDetail $detail, Penjualan $penjualan, Supplier $supplier): array
    {
        $out = ['consignment' => 0, 'cash' => 0];

        if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
            return $out;
        }

        $qty = (float) ($detail->jumlah ?? 0);
        $buyPrice = (float) ($produk->harga_beli ?? 0);
        if ($qty <= 0 || $buyPrice <= 0) {
            return $out;
        }

        $amount = $qty * $buyPrice;
        $saleDate = $penjualan->saledate
            ? Carbon::parse($penjualan->saledate)->toDateString()
            : Carbon::parse($penjualan->created_at)->toDateString();
        $saleDateTime = Carbon::parse($saleDate)->startOfDay();
        $isCash = $this->ledger->isCashSupplier($supplier);

        if ($isCash) {
            $pembelian = Pembelian::where('id_supplier', (int) $supplier->id_supplier)
                ->whereDate('purchasedate2', $saleDate)
                ->orderBy('id_pembelian', 'desc')
                ->first();

            if (! $pembelian) {
                $pembelian = Pembelian::create([
                    'id_supplier' => (int) $supplier->id_supplier,
                    'total_item' => 0,
                    'total_harga' => 0,
                    'reorder' => 0,
                    'bayar' => 0,
                    'purchasedate2' => $saleDate,
                    'created_at' => $saleDateTime,
                    'updated_at' => $saleDateTime,
                ]);
            }

            PembelianDetail::create([
                'id_pembelian' => (int) $pembelian->id_pembelian,
                'id_produk' => (int) $produk->id_produk,
                'harga_beli' => $buyPrice,
                'jumlah' => $qty,
                'subtotal' => $amount,
                'created_at' => $saleDateTime,
                'updated_at' => $saleDateTime,
            ]);

            $pembelian->total_item = (float) ($pembelian->total_item ?? 0) + $qty;
            $pembelian->total_harga = (float) ($pembelian->total_harga ?? 0) + $amount;
            $pembelian->save();
            $out['cash'] = 1;

            return $out;
        }

        $item = $this->ledger->createConsignmentInvoiceItemIfNeeded(
            $penjualan,
            $produk,
            $supplier,
            (int) $qty,
            (int) ($detail->diskon ?? 0)
        );
        if ($item !== null) {
            $out['consignment'] = 1;
        }

        return $out;
    }
}
