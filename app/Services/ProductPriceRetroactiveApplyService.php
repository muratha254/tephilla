<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductPriceRetroactiveApplyService
{
    /**
     * @return array{sales_lines: int, penjualan_recalculated: int, consignment_items: int, cash_details: int, cash_masters: int}
     */
    public function apply(
        int $produkId,
        float $newHargaBeli,
        float $newHargaJual,
        string $dateFrom,
        string $dateTo,
        bool $updateSales,
        bool $updateConsignment,
        bool $updateCashPembelian,
        bool $beliChanged,
        bool $jualChanged
    ): array {
        $counts = [
            'sales_lines' => 0,
            'penjualan_recalculated' => 0,
            'consignment_items' => 0,
            'cash_details' => 0,
            'cash_masters' => 0,
        ];

        DB::transaction(function () use (
            $produkId,
            $newHargaBeli,
            $newHargaJual,
            $dateFrom,
            $dateTo,
            $updateSales,
            $updateConsignment,
            $updateCashPembelian,
            $beliChanged,
            $jualChanged,
            &$counts
        ) {
            $penjualanIds = [];

            if ($jualChanged && $updateSales) {
                $detailQuery = PenjualanDetail::query()
                    ->where('penjualan_detail.id_produk', $produkId)
                    ->join('penjualan', 'penjualan.id_penjualan', '=', 'penjualan_detail.id_penjualan')
                    ->whereRaw('DATE(COALESCE(penjualan.saledate, penjualan.created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo]);

                if (Schema::hasColumn('penjualan', 'status')) {
                    $detailQuery->where('penjualan.status', 'completed');
                }

                $details = $detailQuery->select('penjualan_detail.*')->orderBy('penjualan_detail.id_penjualan_detail')->get();

                foreach ($details as $d) {
                    $hj = $newHargaJual;
                    $j = (int) $d->jumlah;
                    $diskPct = min(100, max(0, (float) ($d->diskon ?? 0)));
                    $subtotal = round($hj * $j * (1 - $diskPct / 100), 0);
                    $d->harga_jual = $hj;
                    $d->subtotal = $subtotal;
                    $d->save();
                    $counts['sales_lines']++;
                    $penjualanIds[(int) $d->id_penjualan] = true;
                }

                foreach (array_keys($penjualanIds) as $pid) {
                    $this->recalculatePenjualanTotals((int) $pid);
                    $counts['penjualan_recalculated']++;
                }
            }

            if ($beliChanged && $updateConsignment && Schema::hasColumn('invoice_items', 'penjualan_id')) {
                $items = InvoiceItem::query()
                    ->join('penjualan', 'penjualan.id_penjualan', '=', 'invoice_items.penjualan_id')
                    ->join('supplier', 'supplier.id_supplier', '=', 'invoice_items.supplier_id')
                    ->where('invoice_items.produk_id', $produkId)
                    ->whereRaw('DATE(COALESCE(penjualan.saledate, penjualan.created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
                    ->whereRaw('COALESCE(invoice_items.amount_paid, 0) = 0')
                    ->whereRaw("UPPER(TRIM(COALESCE(supplier.mop,''))) = 'CONSIGNMENT'")
                    ->select('invoice_items.*')
                    ->orderBy('invoice_items.id')
                    ->get();

                foreach ($items as $inv) {
                    $newAmount = round((float) $inv->quantity * $newHargaBeli, 2);
                    $oldAmount = (float) $inv->amount;
                    $diff = $newAmount - $oldAmount;
                    if (abs($diff) < 0.0001) {
                        continue;
                    }
                    $paid = (float) ($inv->amount_paid ?? 0);
                    $inv->amount = $newAmount;
                    $inv->balance = max(0, round($newAmount - $paid, 2));
                    $inv->save();

                    $invModel = Invoice::query()->find($inv->invoice_id);
                    if ($invModel) {
                        $invModel->total = max(0, round((float) $invModel->total + $diff, 2));
                        $invModel->save();
                    }
                    $counts['consignment_items']++;
                }
            }

            if ($beliChanged && $updateCashPembelian) {
                $rows = PembelianDetail::query()
                    ->where('pembelian_detail.id_produk', $produkId)
                    ->join('pembelian', 'pembelian.id_pembelian', '=', 'pembelian_detail.id_pembelian')
                    ->join('supplier', 'supplier.id_supplier', '=', 'pembelian.id_supplier')
                    ->whereRaw("UPPER(TRIM(COALESCE(supplier.mop,''))) = 'CASH'")
                    ->whereRaw('DATE(COALESCE(pembelian.purchasedate2, pembelian.created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo])
                    ->whereRaw('COALESCE(pembelian.bayar, 0) = 0')
                    ->select('pembelian_detail.*')
                    ->orderBy('pembelian_detail.id_pembelian_detail')
                    ->get();

                $pembelianTouched = [];

                foreach ($rows as $row) {
                    $j = (int) $row->jumlah;
                    $newSub = (int) round($newHargaBeli * $j, 0);
                    $row->harga_beli = (int) round($newHargaBeli, 0);
                    $row->subtotal = $newSub;
                    $row->save();
                    $counts['cash_details']++;
                    $pembelianTouched[(int) $row->id_pembelian] = true;
                }

                foreach (array_keys($pembelianTouched) as $pembId) {
                    $sum = (int) PembelianDetail::where('id_pembelian', $pembId)->sum('subtotal');
                    $sumQty = (int) PembelianDetail::where('id_pembelian', $pembId)->sum('jumlah');
                    Pembelian::where('id_pembelian', $pembId)->update([
                        'total_harga' => $sum,
                        'total_item' => $sumQty,
                    ]);
                    $counts['cash_masters']++;
                }
            }
        });

        return $counts;
    }

    public function recalculatePenjualanTotals(int $idPenjualan): void
    {
        $p = Penjualan::query()->find($idPenjualan);
        if (! $p) {
            return;
        }

        $sumSub = (float) PenjualanDetail::where('id_penjualan', $idPenjualan)->sum('subtotal');
        $p->total_harga = round($sumSub, 2);

        $type = $p->discount_type ?? 'percentage';
        $discountAmount = 0.0;
        if ($type === 'fixed' && Schema::hasColumn('penjualan', 'discount_amount') && (float) ($p->discount_amount ?? 0) > 0) {
            $discountAmount = min((float) $p->discount_amount, (float) $p->total_harga);
        } else {
            $diskPct = min(100.0, max(0.0, (float) ($p->diskon ?? 0)));
            $discountAmount = round($p->total_harga * ($diskPct / 100.0), 0);
        }

        if (Schema::hasColumn('penjualan', 'discount_amount')) {
            $p->discount_amount = $discountAmount > 0 ? $discountAmount : null;
        }

        $p->bayar = max(0.0, round($p->total_harga - $discountAmount, 2));

        if (Schema::hasColumn('penjualan', 'tax')) {
            $p->tax = round($p->bayar * (16 / 116), 2);
        }

        if (Schema::hasColumn('penjualan', 'driver_commission')) {
            $taxAmt = (float) ($p->tax ?? 0);
            $subtotalBeforeVat = $p->bayar - $taxAmt;
            $setting = Setting::query()->first();
            $rate = (float) ($setting->driver_commission_rate ?? 0);
            $p->driver_commission = $rate > 0 && $subtotalBeforeVat > 0
                ? round($subtotalBeforeVat * ($rate / 100), 2)
                : 0;
        }

        $p->save();
    }
}
