<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Setting;
use App\Models\DailyCash;
use App\Models\Shop;
use App\Services\EnsureSaleSupplierLedgerService;
use App\Services\SaleSupplierLedgerRemovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;

class PenjualanDetailController extends Controller
{
    /** @var int Cookie lifetime in minutes (7 days) — keeps POS cart after session refresh / expiry */
    private const POS_LAST_SALE_COOKIE_MINUTES = 10080;

    private const POS_LAST_SALE_COOKIE = 'pos_last_sale';

    /** @var SaleSupplierLedgerRemovalService */
    private $ledgerRemoval;

    public function __construct(SaleSupplierLedgerRemovalService $ledgerRemoval)
    {
        $this->ledgerRemoval = $ledgerRemoval;
    }

    /**
     * Remember which sale this terminal was using so a browser refresh can restore session id_penjualan.
     */
    private function queuePosLastSaleCookie(int $idPenjualan): void
    {
        if ($idPenjualan <= 0) {
            return;
        }
        Cookie::queue(self::POS_LAST_SALE_COOKIE, (string) $idPenjualan, self::POS_LAST_SALE_COOKIE_MINUTES);
    }

    /**
     * If Laravel session lost id_penjualan but cookie still has a recent active sale for this user, restore it.
     */
    private function restorePosSaleFromCookie(Request $request): ?int
    {
        if (! auth()->check()) {
            return null;
        }
        if ((int) auth()->user()->level !== 1) {
            return null;
        }

        $cid = (int) $request->cookie(self::POS_LAST_SALE_COOKIE, 0);
        if ($cid <= 0) {
            return null;
        }

        $penjualan = Penjualan::find($cid);
        if (! $penjualan) {
            return null;
        }

        if (Schema::hasColumn('penjualan', 'status')) {
            $st = (string) ($penjualan->status ?? '');
            if ($st === 'completed' || ! in_array($st, ['active', 'suspended', 'edit_initiated'], true)) {
                return null;
            }
        }

        if ((int) $penjualan->id_user !== (int) auth()->id()) {
            return null;
        }

        return (int) $penjualan->id_penjualan;
    }

    public function index(Request $request)
    {
        // Check if day is opened
        if (!DailyCash::isTodayOpened()) {
            return redirect()->route('daily-cash.open-form')
                ->withErrors(['error' => 'Cash on Hand must be entered before sales can be recorded.']);
        }

        // Check if day is closed
        if (DailyCash::isTodayClosed()) {
            return redirect()->route('dashboard')
                ->withErrors(['error' => 'Today has already been closed. Please open a new day.']);
        }

        // Resume suspended or initiated-edit sale from URL (fallback when session doesn't persist across redirect)
        $resumeId = $request->query('resume');
        if ($resumeId) {
            $penjualanResume = Penjualan::find($resumeId);
            if ($penjualanResume && \Illuminate\Support\Facades\Schema::hasColumn('penjualan', 'status')
                && in_array($penjualanResume->status, ['suspended', 'active', 'edit_initiated'])) {
                session(['id_penjualan' => $penjualanResume->id_penjualan]);
                if (in_array($penjualanResume->status, ['suspended', 'edit_initiated'])) {
                    $penjualanResume->status = 'active';
                    $penjualanResume->update();
                }
                // Load POS directly without redirect (avoids session loss on LAN/network access)
                $id_penjualan = $penjualanResume->id_penjualan;
                $penjualan = $penjualanResume;
                // Don't load all products - use AJAX search instead (much faster)
                // Provide empty array for legacy modal view (not used anymore but included for compatibility)
                $produk = collect([]);
                $member = Member::orderBy('nama')->get();
                $diskon = Setting::first()->diskon ?? 0;
                $memberSelected = $penjualan->member ?? new Member();
                $shops = Shop::orderBy('shop_code')->get();
                $this->queuePosLastSaleCookie((int) $id_penjualan);

                return view('penjualan_detail.index', compact('produk', 'member', 'diskon', 'id_penjualan', 'penjualan', 'memberSelected', 'shops'));
            }
        }

        if (! session()->has('id_penjualan')) {
            $restoredId = $this->restorePosSaleFromCookie($request);
            if ($restoredId) {
                session(['id_penjualan' => $restoredId]);
            }
        }

        // Don't load all products - use AJAX search instead (much faster for 100k+ products)
        // Products are loaded via AJAX in Select2 dropdown
        // Provide empty array for legacy modal view (not used anymore but included for compatibility)
        $produk = collect([]);
        $member = Member::orderBy('nama')->get();
        $diskon = Setting::first()->diskon ?? 0;

        // Check whether there are any transactions in progress
        if ($id_penjualan = session('id_penjualan')) {
            $penjualan = Penjualan::find($id_penjualan);
            
            // If penjualan doesn't exist, clear session and create new
            if (!$penjualan) {
                session()->forget('id_penjualan');
                return redirect()->route('transaksi.baru');
            }
            
            // If sale is completed, redirect (only if status column exists)
            // Allow suspended sales to be worked on if they're in the current session
            if ($penjualan && \Illuminate\Support\Facades\Schema::hasColumn('penjualan', 'status')) {
                if ($penjualan->status === 'completed') {
                    session()->forget('id_penjualan');
                    return redirect()->route('transaksi.baru');
                }
                // If suspended, allow user to continue working on it (reactivate it)
                if ($penjualan->status === 'suspended') {
                    $penjualan->status = 'active';
                    $penjualan->update();
                }
            }
            
            $memberSelected = $penjualan->member ?? new Member();
            $shops = Shop::orderBy('shop_code')->get();

            $this->queuePosLastSaleCookie((int) $id_penjualan);

            return view('penjualan_detail.index', compact('produk', 'member', 'diskon', 'id_penjualan', 'penjualan', 'memberSelected', 'shops'));
        } else {
            if (auth()->user()->level == 1) {
                return redirect()->route('transaksi.baru');
            } else {
                return redirect()->route('dashboard');
            }
        }
    }

    /**
     * JSON: lines whose id_produk no longer exists (breaks receipt print). Used before print/checkout.
     */
    public function cartHealth($id)
    {
        $detailRows = PenjualanDetail::query()
            ->where('id_penjualan', $id)
            ->get(['id_penjualan_detail', 'id_produk']);

        $idProduks = $detailRows->pluck('id_produk')->unique()->filter()->values();
        $existing = $idProduks->isEmpty()
            ? collect()
            : Produk::query()->whereIn('id_produk', $idProduks)->pluck('id_produk')->flip();

        $issues = [];
        foreach ($detailRows as $d) {
            $pid = (int) ($d->id_produk ?? 0);
            if ($pid <= 0 || ! $existing->has($pid)) {
                $issues[] = [
                    'id_penjualan_detail' => (int) $d->id_penjualan_detail,
                    'id_produk' => $pid,
                ];
            }
        }

        return response()->json([
            'ok' => $issues === [],
            'issues' => $issues,
        ]);
    }

    public function data($id)
    {
        $detail = PenjualanDetail::with('produk.shop')
            ->where('id_penjualan', $id)
            ->orderBy('id_penjualan_detail')
            ->get();

        $data = array();
        $total = 0;
        $total_item = 0;

        foreach ($detail as $item) {
            $row = array();
            $p = $item->produk;
            $missing = ! $p;

            if ($missing) {
                $row['DT_RowClass'] = 'pos-cart-line-broken';
                $pid = (int) ($item->id_produk ?? 0);
                $detailId = (int) $item->id_penjualan_detail;
                $row['kode_produk'] = '<span class="label label-danger" title="Product no longer exists in stock — remove this line or fix the product ID">BROKEN LINE</span>';
                $row['nama_produk'] = '<span class="text-danger"><strong>Missing product</strong></span>'
                    .'<br><small class="text-muted">Line #'.$detailId.' · old product id: '.($pid ?: '—').'</small>'
                    .'<br><small class="text-warning">Remove this row (trash) or the receipt cannot print.</small>';
                $row['harga_jual'] = 'ksh '.format_uang($item->harga_jual);
                $currentStock = 0;
                $row['stok'] = '<span class="label label-danger">—</span>';
                $prodLabel = 'Missing product (line '.$detailId.')';
                $row['jumlah'] = '<input type="number" class="form-control input-sm quantity" data-id="'.$detailId.'" data-stock="0" data-product="'.htmlspecialchars($prodLabel, ENT_QUOTES, 'UTF-8').'" data-price="'.e($item->harga_jual).'" data-discount="'.e($item->diskon).'" data-original-quantity="'.e($item->jumlah).'" value="'.e($item->jumlah).'" min="1">';
                $row['diskon'] = $item->diskon.'%';
                $row['subtotal'] = 'ksh '.format_uang($item->subtotal);
                $destroyUrl = route('transaksi.destroy', $item->id_penjualan_detail);
                $row['aksi'] = '<div class="btn-group"><button onclick="deleteData(`'.$destroyUrl.'`)" class="btn btn-xs btn-danger btn-flat" title="Remove broken line"><i class="fa fa-trash"></i></button></div>';
            } else {
                // Get shop name from the product's shop relationship
                $shopName = $p->shop->shop_name ?? ($p->shop->shop_code ?? ($p->kode_produk ?? 'N/A'));
                $row['kode_produk'] = '<span class="label label-success">'.htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8').'</span>';
                $row['nama_produk'] = $p->nama_produk;
                $row['harga_jual'] = 'ksh '.format_uang($item->harga_jual);
                $currentStock = (int) ($p->stok ?? 0);
                $row['stok'] = $currentStock > 0 ? '<span class="label label-info">'.$currentStock.'</span>' : '<span class="label label-danger">Out of Stock</span>';
                $row['jumlah'] = '<input type="number" class="form-control input-sm quantity" data-id="'.$item->id_penjualan_detail.'" data-stock="'.$currentStock.'" data-product="'.htmlspecialchars($p->nama_produk, ENT_QUOTES, 'UTF-8').'" data-price="'.$item->harga_jual.'" data-discount="'.$item->diskon.'" data-original-quantity="'.$item->jumlah.'" value="'.$item->jumlah.'" min="1">';
                $row['diskon'] = $item->diskon.'%';
                $row['subtotal'] = 'ksh '.format_uang($item->subtotal);
                $destroyUrl = route('transaksi.destroy', $item->id_penjualan_detail);
                $row['aksi'] = '<div class="btn-group"><button onclick="deleteData(`'.$destroyUrl.'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button></div>';
            }

            $data[] = $row;

            $total += $item->harga_jual * $item->jumlah - (($item->diskon * $item->jumlah) / 100 * $item->harga_jual);
            $total_item += $item->jumlah;
        }
        $data[] = [
            'kode_produk' => '
                <div class="total hide">'. $total .'</div>
                <div class="total_item hide">'. $total_item .'</div>',
            'nama_produk' => '',
            'harga_jual'  => '',
            'stok'        => '',
            'jumlah'      => '',
            'diskon'      => '',
            'subtotal'    => '',
            'aksi'        => '',
        ];

        return datatables()
            ->of($data)
            ->addIndexColumn()
            ->rawColumns(['aksi', 'kode_produk', 'jumlah', 'stok', 'nama_produk'])
            ->make(true);
    }

    public function store(Request $request)
    {
        // Check if day is opened
        if (!DailyCash::isTodayOpened()) {
            return response()->json([
                'message' => 'Cash on Hand must be entered before sales can be recorded.',
                'errors' => ['cash_on_hand' => ['Cash on Hand must be entered before sales can be recorded.']]
            ], 422);
        }

        // Check if day is closed
        if (DailyCash::isTodayClosed()) {
            return response()->json([
                'message' => 'Today has already been closed. Please open a new day.',
                'errors' => ['day_closed' => ['Today has already been closed.']]
            ], 422);
        }

        $produk = Produk::where('id_produk', $request->id_produk)->first();
        if (! $produk) {
            return response()->json(['message' => 'Product not found'], 400);
        }

        $penjualan = Penjualan::find($request->id_penjualan);
        if (! $penjualan) {
            return response()->json(['message' => 'Sale not found'], 400);
        }
        $isManagementSale = (($penjualan->sale_type ?? 'normal') === 'management');
        // Management sales are priced at buying cost; normal sales use selling price.
        $unitPrice = $isManagementSale ? (float) ($produk->harga_beli ?? 0) : (float) ($produk->harga_jual ?? 0);

        // Allow adding to cart even when stock is 0 so the same product can be sold again (e.g. after quick-add).
        // Stock is validated again when completing the transaction; negative stock is allowed on completion if needed.
        // Each scan/add creates its own line (same product may appear multiple times).

        $detail = new PenjualanDetail();
        $detail->id_penjualan = $request->id_penjualan;
        $detail->id_produk = $produk->id_produk;
        $detail->harga_jual = $unitPrice;
        $detail->jumlah = 1;
        // Ensure discount is within 0-100 range for tinyInteger column
        $productDiskon = (float) ($produk->diskon ?? 0);
        $detail->diskon = min(100, max(0, (int) round($productDiskon)));
        $detail->subtotal = $unitPrice - (($detail->diskon / 100) * $unitPrice);
        $detail->save();

        return response()->json([
            'message' => 'Data saved successfully',
            'id_penjualan_detail' => (int) $detail->id_penjualan_detail,
        ], 200);
    }
    
    public function update(Request $request, $id)
    {
        try {
            $detail = PenjualanDetail::find($id);
            if (!$detail) {
                return response()->json(['message' => 'Detail not found'], 404);
            }

            // Get product to check stock
            $produk = Produk::find($detail->id_produk);
            if (!$produk) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            // Update quantity if provided
            if ($request->has('jumlah')) {
                $newQuantity = (int) $request->jumlah;
                
                // Validate quantity is positive
                if ($newQuantity <= 0) {
                    return response()->json(['message' => 'Quantity must be greater than 0'], 400);
                }

                // Allow quantity even when it exceeds stock so the same product can be sold again (e.g. after quick-add)
                $detail->jumlah = $newQuantity;
            }

            // Update discount if provided
            // Note: diskon column is tinyInteger (0-100), so we need to ensure it's within range
            if ($request->has('diskon')) {
                $diskonInput = (float) str_replace(',', '', $request->diskon ?? 0);
                // Limit to 0-100 range for tinyInteger column
                $detail->diskon = min(100, max(0, (int) round($diskonInput)));
            }

            // Recalculate subtotal
            $detail->subtotal = $detail->harga_jual * $detail->jumlah - (($detail->diskon * $detail->jumlah) / 100 * $detail->harga_jual);
            $detail->update();

            return response()->json(['message' => 'Data updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating data: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $detail = PenjualanDetail::with('penjualan')->find($id);
        if (! $detail) {
            return response()->json(['message' => 'Line item not found or already removed.'], 404);
        }

        $penjualan = $detail->penjualan;
        if (! $penjualan) {
            return response()->json(['message' => 'Sale not found for this line.'], 404);
        }

        $ledger = app(EnsureSaleSupplierLedgerService::class);
        $preview = $this->ledgerRemoval->ledgerImpactForDetailLine($detail, $penjualan, $ledger);

        if ($preview['consignment_paid'] || $preview['cash_purchase_paid']) {
            return response()->json([
                'message' => $preview['consignment_paid']
                    ? 'This line is linked to consignment that already has payments recorded. Remove or reverse those payments before deleting the sale line.'
                    : 'This line is linked to a cash-generated purchase that already has payments recorded. Adjust that purchase before deleting the sale line.',
            ], 422);
        }

        if (($preview['consignment'] || $preview['cash']) && ! $request->boolean('confirmed')) {
            return response()->json([
                'requires_confirmation' => true,
                'message' => 'Deleting this line will also remove the matching entry from supplier consignment and/or cash-generated sales (outstanding supplier purchases).',
                'consignment' => $preview['consignment'],
                'cash' => $preview['cash'],
            ], 409);
        }

        if ($preview['consignment']) {
            $this->ledgerRemoval->removeConsignmentInvoiceItemForDetail($detail);
        }
        if ($preview['cash']) {
            $this->ledgerRemoval->removeCashPembelianDetailForDetail($detail, $penjualan);
        }

        if (Schema::hasColumn('penjualan', 'status') && ($penjualan->status ?? '') === 'completed') {
            $produk = Produk::find($detail->id_produk);
            if ($produk) {
                $produk->stok += (int) $detail->jumlah;
                $produk->save();
            }
        }

        $idProduk = (int) $detail->id_produk;
        $detail->delete();
        Produk::deleteOrphanPosQuickAddProduct($idProduk);

        return response(null, 204);
    }

    public function loadForm($diskon = 0, $total = 0, $diterima = 0)
    {
        $bayar = $total;
        // The frontend will now send the already calculated discount amount
        if ($diskon > 0) {
            $bayar = $total - $diskon;
        }

        // Calculate tax (16% inclusive)
        $tax = round($bayar * (16 / 116), 2); // 16/116 for inclusive tax
        $subtotalBeforeTax = $bayar - $tax;

        $kembali = ($diterima != 0) ? $diterima - $bayar : 0;
        $data    = [
            'totalrp' => format_uang($total),
            'bayar' => $bayar,
            'bayarrp' => format_uang($bayar),
            'tax' => $tax,
            'taxrp' => format_uang($tax),
            'subtotalBeforeTax' => $subtotalBeforeTax,
            'subtotalBeforeTaxrp' => format_uang($subtotalBeforeTax),
            'terbilang' => ucwords(terbilang($bayar). ' Shillings'),
            'kembalirp' => format_uang($kembali),
            'kembali_terbilang' => ucwords(terbilang($kembali). ' Shillings'),
        ];

        return response()->json($data);
    }
}
