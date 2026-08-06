<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PembelianDetail;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Removes consignment invoice lines and cash-generated pembelian lines that match a sale detail.
 * Used when deleting a single line or an entire sale from the sales list.
 *
 * When a completed sale is opened for editing (initiate edit or admin reopen), unpaid consignment
 * rows are stripped so abandoned edits do not leave supplier balances posted; POS completion recreates them.
 */
class SaleSupplierLedgerRemovalService
{
    /**
     * @return array{consignment: bool, cash: bool, consignment_paid: bool, cash_purchase_paid: bool}
     */
    public function ledgerImpactForDetailLine(PenjualanDetail $detail, Penjualan $penjualan, EnsureSaleSupplierLedgerService $ledger): array
    {
        $out = [
            'consignment' => false,
            'cash' => false,
            'consignment_paid' => false,
            'cash_purchase_paid' => false,
        ];

        $produk = Produk::find($detail->id_produk);
        if (! $produk || ! $produk->id_supplier) {
            return $out;
        }

        if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
            return $out;
        }

        $supplier = Supplier::find($produk->id_supplier);
        if (! $supplier) {
            return $out;
        }

        if (strtoupper(trim($supplier->mop ?? '')) === 'CONSIGNMENT' && Schema::hasColumn('invoice_items', 'penjualan_id')) {
            $paid = InvoiceItem::where('penjualan_id', $detail->id_penjualan)
                ->where('produk_id', $detail->id_produk)
                ->where('quantity', $detail->jumlah)
                ->whereRaw('COALESCE(amount_paid, 0) > 0.0001')
                ->exists();
            if ($paid) {
                $out['consignment_paid'] = true;

                return $out;
            }
            $out['consignment'] = $this->firstUnpaidConsignmentInvoiceItem($detail) !== null;
        }

        if ($ledger->isCashSupplier($supplier)) {
            $pd = $this->firstMatchingCashPembelianDetail($detail, $penjualan, (int) $supplier->id_supplier);
            if ($pd && $pd->pembelian) {
                $bayar = (float) ($pd->pembelian->bayar ?? 0);
                if ($bayar > 0.0001) {
                    $out['cash_purchase_paid'] = true;

                    return $out;
                }
                $out['cash'] = true;
            }
        }

        return $out;
    }

    /**
     * @return array{
     *   consignment: bool,
     *   cash: bool,
     *   consignment_paid: bool,
     *   cash_purchase_paid: bool,
     *   message: ?string
     * }
     */
    public function ledgerImpactForEntirePenjualan(Penjualan $penjualan, EnsureSaleSupplierLedgerService $ledger): array
    {
        $merged = [
            'consignment' => false,
            'cash' => false,
            'consignment_paid' => false,
            'cash_purchase_paid' => false,
            'message' => null,
        ];

        $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        foreach ($details as $detail) {
            $line = $this->ledgerImpactForDetailLine($detail, $penjualan, $ledger);
            if ($line['consignment_paid']) {
                $merged['consignment_paid'] = true;
                $merged['message'] = 'This sale is linked to consignment that already has payments recorded. Remove or reverse those payments before deleting the sale.';

                return $merged;
            }
            if ($line['cash_purchase_paid']) {
                $merged['cash_purchase_paid'] = true;
                $merged['message'] = 'This sale is linked to a cash-generated purchase that already has payments recorded. Adjust that purchase before deleting the sale.';

                return $merged;
            }
            $merged['consignment'] = $merged['consignment'] || $line['consignment'];
            $merged['cash'] = $merged['cash'] || $line['cash'];
        }

        return $merged;
    }

    public function removeAllUnpaidLedgerForPenjualan(Penjualan $penjualan): void
    {
        $details = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        foreach ($details as $detail) {
            $this->removeConsignmentInvoiceItemForDetail($detail);
            $this->removeCashPembelianDetailForDetail($detail, $penjualan);
        }
    }

    /**
     * Delete every unpaid consignment invoice_item linked to this sale (including duplicate phantoms).
     * Does not touch cash Pembelian. Lines with amount_paid are kept.
     *
     * @return int number of invoice_item rows deleted
     */
    public function removeAllUnpaidConsignmentInvoiceItemsForPenjualan(Penjualan $penjualan): int
    {
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return 0;
        }

        $items = InvoiceItem::query()
            ->where('penjualan_id', $penjualan->id_penjualan)
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
            $remaining = (int) InvoiceItem::where('invoice_id', $invoiceId)->count();
            if ($remaining === 0) {
                $invoice->delete();
            } else {
                $invoice->total = (float) InvoiceItem::where('invoice_id', $invoiceId)->sum('amount');
                $invoice->save();
            }
        }

        return $items->count();
    }

    public function firstUnpaidConsignmentInvoiceItem(PenjualanDetail $detail): ?InvoiceItem
    {
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return null;
        }

        return InvoiceItem::where('penjualan_id', $detail->id_penjualan)
            ->where('produk_id', $detail->id_produk)
            ->where('quantity', $detail->jumlah)
            ->where(function ($query) {
                $query->where('amount_paid', 0)
                    ->orWhereNull('amount_paid')
                    ->orWhereRaw('COALESCE(amount_paid, 0) = 0');
            })
            ->whereRaw('COALESCE(balance, amount) = amount')
            ->orderBy('id')
            ->first();
    }

    public function firstMatchingCashPembelianDetail(PenjualanDetail $detail, Penjualan $penjualan, int $supplierId): ?PembelianDetail
    {
        $saleDay = Carbon::parse($penjualan->saledate ?? $penjualan->created_at)->toDateString();

        return PembelianDetail::query()
            ->where('pembelian_detail.id_produk', (int) $detail->id_produk)
            ->where('pembelian_detail.jumlah', (int) $detail->jumlah)
            ->whereHas('pembelian', function ($q) use ($supplierId, $saleDay) {
                $q->where('id_supplier', $supplierId)
                    ->whereDate('purchasedate2', $saleDay);
            })
            ->orderBy('pembelian_detail.id_pembelian_detail')
            ->first();
    }

    public function removeConsignmentInvoiceItemForDetail(PenjualanDetail $detail): void
    {
        $item = $this->firstUnpaidConsignmentInvoiceItem($detail);
        if (! $item) {
            return;
        }

        $invoiceId = (int) $item->invoice_id;
        $item->delete();

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return;
        }

        $remainingCount = (int) InvoiceItem::where('invoice_id', $invoiceId)->count();
        if ($remainingCount === 0) {
            $invoice->delete();

            return;
        }

        $invoice->total = (float) InvoiceItem::where('invoice_id', $invoiceId)->sum('amount');
        $invoice->save();
    }

    public function removeCashPembelianDetailForDetail(PenjualanDetail $detail, Penjualan $penjualan, ?int $cashSupplierId = null): void
    {
        $supplierId = $cashSupplierId;
        if ($supplierId === null || $supplierId <= 0) {
            $produk = Produk::find($detail->id_produk);
            if (! $produk || ! $produk->id_supplier) {
                return;
            }
            $supplierId = (int) $produk->id_supplier;
        }

        $supplier = Supplier::find($supplierId);
        if (! $supplier) {
            return;
        }

        $pd = $this->firstMatchingCashPembelianDetail($detail, $penjualan, (int) $supplier->id_supplier);
        if (! $pd) {
            return;
        }

        $pembelian = $pd->pembelian;
        $pd->delete();

        if (! $pembelian) {
            return;
        }

        if ($pembelian->details()->count() === 0) {
            $pembelian->delete();

            return;
        }

        $pembelian->total_harga = (float) $pembelian->details()->sum('subtotal');
        $pembelian->total_item = (int) $pembelian->details()->sum('jumlah');
        $pembelian->save();
    }
}
