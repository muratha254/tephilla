<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\Setting;
use App\Services\EnsureSaleSupplierLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use PDF;

class ReceiptConfirmationController extends Controller
{
    private function postSupplierLedgerAfterConfirmation(Penjualan $penjualan, ?array $detailIds = null): void
    {
        $ledger = app(EnsureSaleSupplierLedgerService::class);
        if (! $ledger->saleDefersLedgerUntilConfirmed($penjualan)) {
            return;
        }

        $ledger->postConfirmedDetailsToSupplierLedger($penjualan, $detailIds);
        $ledger->forgetUnconfirmedCashPembelianDetailIdsCache();
    }

    /**
     * Default list: hide fully confirmed receipts. Empty $status = open only; "all" = no status filter.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyReceiptConfirmationStatusScope($query, ?string $status): void
    {
        if (! Schema::hasColumn('penjualan', 'confirmation_status')) {
            return;
        }
        if ($status === null || $status === '') {
            $query->where(function ($q) {
                $q->whereNull('confirmation_status')
                    ->orWhereIn('confirmation_status', ['pending', 'review', 'defect']);
            });

            return;
        }
        if ($status === 'all') {
            return;
        }
        $query->where('confirmation_status', $status);
    }

    /**
     * JSON count for menu badge (refreshed after confirm actions).
     */
    public function pendingBadgeCount()
    {
        if (! auth()->user()->hasRole('admin')) {
            return response()->json(['count' => 0], 403);
        }

        return response()->json(['count' => Penjualan::receiptConfirmationBadgeCount()]);
    }

    /**
     * Typeahead source for receipt line edit: match code or product name; returns shop and prices.
     */
    public function productSearch(Request $request)
    {
        if (! auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['results' => []]);
        }

        $shopId = (int) $request->query('shop_id', 0);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $pattern = '%'.$escaped.'%';

        $query = Produk::query()
            ->with(['shop'])
            ->where(function ($w) use ($pattern) {
                $w->where('item_code', 'like', $pattern)
                    ->orWhere('kode_produk', 'like', $pattern)
                    ->orWhere('nama_produk', 'like', $pattern);
            });

        if ($shopId > 0) {
            $query->orderByRaw('(CASE WHEN shop_id = ? THEN 0 ELSE 1 END)', [$shopId]);
        }

        $rows = $query->orderBy('nama_produk')->limit(40)->get();

        $results = $rows->map(function (Produk $p) {
            $code = trim((string) ($p->item_code ?: $p->kode_produk));
            if ($code === '') {
                $code = '#'.$p->id_produk;
            }

            return [
                'id_produk' => (int) $p->id_produk,
                'item_code' => $code,
                'kode_produk' => (string) ($p->kode_produk ?? ''),
                'nama_produk' => (string) ($p->nama_produk ?? ''),
                'shop_id' => (int) ($p->shop_id ?? 0),
                'shop_name' => (string) ($p->shop->shop_name ?? ''),
                'harga_beli' => (float) ($p->harga_beli ?? 0),
                'harga_jual' => (float) ($p->harga_jual ?? 0),
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Display the receipt confirmation page
     */
    public function index()
    {
        // Only admins can access
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can access Receipt Confirmation.');
        }

        return view('receipt-confirmation.index');
    }

    /**
     * Get data for DataTable
     */
    public function data(Request $request)
    {
        $query = Penjualan::with(['member', 'user', 'details.produk.shop'])
            ->where('status', 'completed')
            ->orderBy('id_penjualan', 'desc');

        $this->applyReceiptConfirmationStatusScope($query, $request->input('confirmation_status'));

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('saledate', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('saledate', '<=', $request->input('end_date'));
        }

        // Filter by receipt number
        if ($request->filled('receipt_number')) {
            $query->where('receiptno', 'like', '%' . $request->receipt_number . '%');
        }

        // Filter by shop name
        if ($request->filled('shop_name')) {
            $query->whereHas('details.produk.shop', function($q) use ($request) {
                $q->where('shop_name', 'like', '%' . $request->shop_name . '%');
            });
        }

        // Use query builder directly for server-side pagination
        return datatables()
            ->eloquent($query)
            ->addIndexColumn()
            ->addColumn('receipt_number', function ($penjualan) {
                return $penjualan->receiptno;
            })
            ->addColumn('shop_name', function ($penjualan) {
                // Get unique shop names from sale details
                $shops = $penjualan->details->map(function($detail) {
                    return $detail->produk->shop->shop_name ?? 'N/A';
                })->unique()->values();
                return $shops->implode(', ');
            })
            ->addColumn('items_sold', function ($penjualan) {
                return $penjualan->total_item;
            })
            ->addColumn('items_name', function ($penjualan) {
                // Get item names from sale details
                $items = $penjualan->details->map(function($detail) {
                    if ($detail->produk) {
                        $productName = $detail->produk->nama_produk ?? 'N/A';
                        $quantity = $detail->jumlah ?? 1;
                        return $productName . ' (x' . $quantity . ')';
                    }
                    return 'N/A';
                })->filter()->values();
                
                $itemsList = $items->implode(', ');
                // Truncate if too long and add tooltip
                if (strlen($itemsList) > 100) {
                    return '<span title="' . htmlspecialchars($itemsList) . '">' . htmlspecialchars(substr($itemsList, 0, 100)) . '...</span>';
                }
                return htmlspecialchars($itemsList);
            })
            ->addColumn('total_amount', function ($penjualan) {
                return 'Ksh ' . format_uang($penjualan->bayar);
            })
            ->addColumn('date_of_sale', function ($penjualan) {
                return $penjualan->saledate ? Carbon::parse($penjualan->saledate)->format('d/m/Y') : '-';
            })
            ->addColumn('current_status', function ($penjualan) {
                if (Schema::hasColumn('penjualan', 'confirmation_status')) {
                    $status = $penjualan->confirmation_status ?? 'pending';
                } else {
                    $status = 'pending';
                }
                
                // Check if it was edited from defect
                $wasEditedFromDefect = false;
                if (Schema::hasColumn('penjualan', 'was_edited_from_defect')) {
                    $wasEditedFromDefect = $penjualan->was_edited_from_defect ?? false;
                }
                
                // Display status
                if ($status === 'confirmed' && $wasEditedFromDefect) {
                    $displayStatus = 'Confirmed with Edit';
                    $badgeClass = 'success';
                } elseif ($status === 'pending') {
                    $displayStatus = 'Pending';
                    $badgeClass = 'warning';
                } elseif ($status === 'confirmed') {
                    $displayStatus = 'Confirmed';
                    $badgeClass = 'success';
                } elseif ($status === 'defect') {
                    $displayStatus = 'Defect';
                    $badgeClass = 'danger';
                } elseif ($status === 'review') {
                    $displayStatus = 'Review';
                    $badgeClass = 'info';
                } else {
                    $displayStatus = ucfirst($status);
                    $badgeClass = 'secondary';
                }
                
                return '<span class="label label-' . $badgeClass . '">' . $displayStatus . '</span>';
            })
            ->addColumn('aksi', function ($penjualan) {
                if ($this->penjualanHasQuickAddIncompleteItems($penjualan)) {
                    return '<button type="button" onclick="confirmReceipt(' . $penjualan->id_penjualan . ')" class="btn btn-warning btn-xs" title="Complete Quick Add items first"><i class="fa fa-clock-o"></i> Quick Add</button>';
                }

                return '<button type="button" onclick="confirmReceipt(' . $penjualan->id_penjualan . ')" class="btn btn-success btn-xs"><i class="fa fa-check"></i> Confirm</button>';
            })
            ->rawColumns(['current_status', 'aksi', 'items_name'])
            ->make(true);
    }

    /**
     * Show receipt details
     */
    public function show($id)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }

        $penjualan = Penjualan::with(['member', 'user', 'details.produk.shop'])
            ->findOrFail($id);

        $waitingUpdate = $this->penjualanHasQuickAddIncompleteItems($penjualan);

        return view('receipt-confirmation.show', compact('penjualan', 'waitingUpdate'));
    }

    /**
     * Mark receipt as confirmed
     */
    public function confirm($id)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $penjualan = Penjualan::findOrFail($id);

        if ($block = $this->quickAddBlockResponse((int) $id)) {
            return $block;
        }

        if (!Schema::hasColumn('penjualan', 'confirmation_status')) {
            return response()->json(['error' => 'Confirmation status column does not exist.'], 500);
        }

        // Use raw SQL update to properly handle ENUM column with quotes
        // Check if it was edited from defect to show "confirmed with edit"
        $wasEditedFromDefect = Schema::hasColumn('penjualan', 'was_edited_from_defect') && 
                               DB::table('penjualan')->where('id_penjualan', $id)->value('was_edited_from_defect');
        
        if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            DB::table('penjualan_detail')
                ->where('id_penjualan', $id)
                ->where(function ($q) {
                    $q->whereNull('item_confirmation_status')
                        ->orWhere('item_confirmation_status', 'pending')
                        ->orWhere('item_confirmation_status', '');
                })
                ->update(['item_confirmation_status' => 'confirmed']);
        } else {
            DB::statement("UPDATE penjualan SET confirmation_status = 'confirmed' WHERE id_penjualan = ?", [$id]);
        }

        if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')
            && Schema::hasColumn('penjualan', 'confirmation_status')) {
            $receiptStatus = $this->calculateReceiptStatusFromItems($id);
            if ($receiptStatus) {
                DB::statement('UPDATE penjualan SET confirmation_status = ? WHERE id_penjualan = ?', [$receiptStatus, $id]);
            }
        }

        $this->postSupplierLedgerAfterConfirmation($penjualan);

        return response()->json([
            'success' => 'Receipt confirmed successfully.',
            'was_edited_from_defect' => $wasEditedFromDefect,
        ]);
    }

    /**
     * Confirm multiple line items (Confirm All Items in modal — uses checked rows).
     */
    public function confirmAllItems(Request $request, $id)
    {
        if (! auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        Penjualan::findOrFail($id);

        if ($block = $this->quickAddBlockResponse((int) $id)) {
            return $block;
        }

        if (! Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            return response()->json(['error' => 'Item confirmation status column does not exist.'], 500);
        }

        $itemIds = array_values(array_filter(array_map('intval', (array) $request->input('item_ids', []))));

        $query = PenjualanDetail::query()
            ->where('id_penjualan', $id)
            ->where(function ($q) {
                $q->whereNull('item_confirmation_status')
                    ->orWhere('item_confirmation_status', 'pending')
                    ->orWhere('item_confirmation_status', '');
            });

        if ($itemIds !== []) {
            $query->whereIn('id_penjualan_detail', $itemIds);
        }

        $toConfirm = $query->pluck('id_penjualan_detail');

        if ($toConfirm->isEmpty()) {
            return response()->json([
                'error' => $itemIds !== []
                    ? 'Selected items are already confirmed or cannot be confirmed.'
                    : 'No pending items to confirm.',
            ], 422);
        }

        DB::table('penjualan_detail')
            ->where('id_penjualan', $id)
            ->whereIn('id_penjualan_detail', $toConfirm)
            ->update(['item_confirmation_status' => 'confirmed']);

        $receiptStatus = $this->calculateReceiptStatusFromItems($id);
        if ($receiptStatus && Schema::hasColumn('penjualan', 'confirmation_status')) {
            DB::statement('UPDATE penjualan SET confirmation_status = ? WHERE id_penjualan = ?', [$receiptStatus, $id]);
        }

        $penjualan = Penjualan::findOrFail($id);
        $this->postSupplierLedgerAfterConfirmation($penjualan, $toConfirm->values()->all());

        return response()->json([
            'success' => true,
            'message' => $toConfirm->count().' item(s) confirmed.',
            'confirmed_ids' => $toConfirm->values()->all(),
            'receipt_status' => $receiptStatus,
            'all_confirmed' => ($receiptStatus === 'confirmed'),
        ]);
    }

    /**
     * Mark receipt as defect
     */
    public function markDefect($id)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $penjualan = Penjualan::findOrFail($id);

        if (!Schema::hasColumn('penjualan', 'confirmation_status')) {
            return response()->json(['error' => 'Confirmation status column does not exist.'], 500);
        }

        // Use raw SQL update to properly handle ENUM column with quotes
        DB::statement("UPDATE penjualan SET confirmation_status = 'defect' WHERE id_penjualan = ?", [$id]);

        return response()->json(['success' => 'Receipt marked as defect.']);
    }

    /**
     * Show edit form for receipt
     */
    public function edit($id)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }

        $penjualan = Penjualan::with(['member', 'user', 'details.produk.shop'])
            ->findOrFail($id);

        if (Schema::hasColumn('penjualan', 'confirmation_status')) {
            $status = $penjualan->confirmation_status ?? 'pending';
        } else {
            $status = 'pending';
        }
        
        // Only allow editing if status is pending or defect
        if ($status === 'confirmed') {
            return redirect()->route('receipt-confirmation.index')
                ->withErrors(['error' => 'Confirmed receipts cannot be edited.']);
        }

        return view('receipt-confirmation.edit', compact('penjualan'));
    }

    /**
     * Update receipt details
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $penjualan = Penjualan::findOrFail($id);
        
        if (Schema::hasColumn('penjualan', 'confirmation_status')) {
            $status = $penjualan->confirmation_status ?? 'pending';
        } else {
            $status = 'pending';
        }

        // Only allow editing if status is pending or defect
        if ($status === 'confirmed') {
            return response()->json(['error' => 'Confirmed receipts cannot be edited.'], 422);
        }

        $request->validate([
            'total_item' => 'required|integer|min:1',
            'total_harga' => 'required|numeric|min:0',
            'bayar' => 'required|numeric|min:0',
            'receiptno' => 'required|string',
        ]);

        // Update receipt details
        $penjualan->total_item = $request->total_item;
        $penjualan->total_harga = $request->total_harga;
        $penjualan->bayar = $request->bayar;
        $penjualan->receiptno = $request->receiptno;
        
        if ($request->filled('saledate')) {
            $dateStr = trim($request->saledate);
            try {
                $penjualan->saledate = Carbon::createFromFormat('d/m/Y', $dateStr)->format('Y-m-d');
            } catch (\Exception $e) {
                $penjualan->saledate = Carbon::parse($dateStr)->format('Y-m-d');
            }
        }

        // Update discount if provided
        if ($request->filled('diskon')) {
            $penjualan->diskon = $request->diskon;
        }

        $penjualan->save();

        // After editing a defect receipt, set status back to pending and mark as edited from defect
        if ($status === 'defect' && Schema::hasColumn('penjualan', 'confirmation_status')) {
            DB::statement("UPDATE penjualan SET confirmation_status = 'pending', was_edited_from_defect = 1 WHERE id_penjualan = ?", [$id]);
        }

        if ($request->ajax()) {
            return response()->json(['success' => 'Receipt updated successfully.']);
        }

        return redirect()->route('receipt-confirmation.index')
            ->with('success', 'Receipt updated successfully.');
    }

    /**
     * Legacy URL: open the same Confirm Receipt modal as Sales List.
     */
    public function review($id)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }

        Penjualan::findOrFail($id);

        return redirect()->route('receipt-confirmation.index', ['confirm' => $id]);
    }

    /**
     * Confirm individual item
     */
    public function confirmItem(Request $request, $id, $itemId)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $penjualan = Penjualan::findOrFail($id);
        $detail = PenjualanDetail::where('id_penjualan_detail', $itemId)
            ->where('id_penjualan', $id)
            ->firstOrFail();

        if ($block = $this->quickAddBlockResponse((int) $id)) {
            return $block;
        }

        if (!Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            return response()->json(['error' => 'Item confirmation status column does not exist.'], 500);
        }

        try {
            DB::statement("UPDATE penjualan_detail SET item_confirmation_status = 'confirmed' WHERE id_penjualan_detail = ?", [$itemId]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update item status: ' . $e->getMessage()], 500);
        }

        // Check receipt status based on all items
        $receiptStatus = $this->calculateReceiptStatusFromItems($id);
        
        if ($receiptStatus && Schema::hasColumn('penjualan', 'confirmation_status')) {
            DB::statement("UPDATE penjualan SET confirmation_status = ? WHERE id_penjualan = ?", [$receiptStatus, $id]);
        }

        $this->postSupplierLedgerAfterConfirmation($penjualan, [(int) $itemId]);

        $allConfirmed = ($receiptStatus === 'confirmed');
        $allDefect = ($receiptStatus === 'defect');

        return response()->json([
            'success' => 'Item confirmed successfully.', 
            'all_confirmed' => $allConfirmed,
            'all_defect' => $allDefect,
            'receipt_status' => $receiptStatus
        ]);
    }

    /**
     * Mark individual item as defect
     */
    public function markItemDefect(Request $request, $id, $itemId)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $penjualan = Penjualan::findOrFail($id);
        $detail = PenjualanDetail::where('id_penjualan_detail', $itemId)
            ->where('id_penjualan', $id)
            ->firstOrFail();

        if (!Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            return response()->json(['error' => 'Item confirmation status column does not exist.'], 500);
        }

        try {
            DB::statement("UPDATE penjualan_detail SET item_confirmation_status = 'defect' WHERE id_penjualan_detail = ?", [$itemId]);
            
            // Check receipt status based on all items
            $receiptStatus = $this->calculateReceiptStatusFromItems($id);
            
            if ($receiptStatus && Schema::hasColumn('penjualan', 'confirmation_status')) {
                DB::statement("UPDATE penjualan SET confirmation_status = ? WHERE id_penjualan = ?", [$receiptStatus, $id]);
            }
            
            $allDefect = ($receiptStatus === 'defect');
            
            return response()->json([
                'success' => 'Item marked as defect.',
                'all_defect' => $allDefect,
                'receipt_status' => $receiptStatus
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update item status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update individual item (price, qty, shop, product code). Adjusts stock and supply-chain rows when the product or qty changes.
     */
    public function updateItem(Request $request, $id, $itemId)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'jumlah' => 'required|integer|min:1',
            'harga_jual' => 'required|numeric|min:0',
            'diskon' => 'nullable|integer|min:0|max:100',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'item_code' => 'nullable|string|max:255',
        ]);

        try {
            return DB::transaction(function () use ($request, $id, $itemId) {
                $penjualan = Penjualan::lockForUpdate()->findOrFail($id);
                $detail = PenjualanDetail::where('id_penjualan_detail', $itemId)
                    ->where('id_penjualan', $id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldProdukId = (int) $detail->id_produk;
                $oldJumlah = (int) $detail->jumlah;
                $oldDiskon = (int) ($detail->diskon ?? 0);

                $itemCode = trim((string) $request->input('item_code', ''));
                if ($itemCode === '') {
                    $op = Produk::find($oldProdukId);
                    if ($op) {
                        $itemCode = trim((string) ($op->item_code ?? $op->kode_produk ?? ''));
                    }
                }
                if ($itemCode === '') {
                    return response()->json(['message' => 'Item code is required (or link a product to this line).'], 422);
                }

                $preferredShopId = (int) $request->input('shop_id', 0);
                $newProduk = $this->resolveProdukByItemCodeForReceipt($itemCode, $preferredShopId);
                if (! $newProduk) {
                    return response()->json([
                        'message' => 'No product with this exact code in the selected shop (the code may exist only in another shop, or the code may be wrong — e.g. fn67 vs fn670). Adjust the shop dropdown or fix the code.',
                    ], 422);
                }

                $newProduk = Produk::lockForUpdate()->find($newProduk->id_produk);
                $newJumlah = (int) $request->jumlah;
                $newDiskon = $request->filled('diskon') ? (int) $request->diskon : $oldDiskon;
                $newHargaJual = (float) $request->harga_jual;

                $oldProduk = $oldProdukId ? Produk::lockForUpdate()->find($oldProdukId) : null;

                $ledgerService = app(EnsureSaleSupplierLedgerService::class);
                $shouldSyncSupplierLedger = ! $ledgerService->saleDefersLedgerUntilConfirmed($penjualan)
                    || ($detail->item_confirmation_status ?? 'pending') === 'confirmed';

                if ($shouldSyncSupplierLedger) {
                    $this->syncInvoiceItemAfterReceiptLineProductChange(
                        $penjualan,
                        $oldProdukId,
                        $newProduk,
                        $oldJumlah,
                        $newJumlah,
                        $newDiskon
                    );

                    $this->syncPembelianAfterReceiptLineProductChange(
                        $penjualan,
                        $oldProduk,
                        $newProduk,
                        $oldJumlah,
                        $newJumlah
                    );
                }

                if ($oldProduk && (int) $oldProduk->id_produk === (int) $newProduk->id_produk) {
                    $oldProduk->stok = (int) $oldProduk->stok + $oldJumlah - $newJumlah;
                    $oldProduk->save();
                } else {
                    if ($oldProduk) {
                        $oldProduk->stok = (int) $oldProduk->stok + $oldJumlah;
                        $oldProduk->save();
                    }
                    $newProduk->refresh();
                    $newProduk->stok = (int) $newProduk->stok - $newJumlah;
                    $newProduk->save();
                }

                if ((int) $newProduk->id_produk === $oldProdukId && $request->filled('shop_id')) {
                    $sid = (int) $request->shop_id;
                    if ((int) $newProduk->shop_id !== $sid) {
                        $newProduk->shop_id = $sid;
                        $newProduk->save();
                    }
                }

                $detail->id_produk = $newProduk->id_produk;
                $detail->jumlah = $newJumlah;
                $detail->harga_jual = $newHargaJual;
                $detail->diskon = $newDiskon;

                $subtotal = $newHargaJual * $newJumlah;
                if ($newDiskon > 0) {
                    $subtotal = $subtotal - (($newDiskon / 100) * $subtotal);
                }
                $detail->subtotal = (int) round($subtotal);
                $detail->save();

                $totalItem = (int) PenjualanDetail::where('id_penjualan', $id)->sum('jumlah');
                $totalHarga = (int) PenjualanDetail::where('id_penjualan', $id)->sum('subtotal');

                $penjualan->total_item = $totalItem;
                $penjualan->total_harga = $totalHarga;
                $penjualan->bayar = $totalHarga;
                $penjualan->save();

                if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
                    DB::statement("UPDATE penjualan_detail SET item_confirmation_status = 'pending' WHERE id_penjualan_detail = ?", [$itemId]);
                }

                return response()->json(['success' => 'Item updated successfully.']);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('Receipt line update failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['message' => config('app.debug') ? $e->getMessage() : 'Unable to update item.'], 500);
        }
    }

    private function resolveProdukByItemCodeForReceipt(string $itemCode, int $preferredShopId): ?Produk
    {
        $itemCode = trim($itemCode);
        if ($itemCode === '') {
            return null;
        }
        $matching = Produk::query()
            ->where(function ($q) use ($itemCode) {
                $q->whereRaw('UPPER(TRIM(COALESCE(item_code, ""))) = ?', [strtoupper($itemCode)])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(kode_produk, ""))) = ?', [strtoupper($itemCode)]);
            })
            ->orderByDesc('id_produk')
            ->get();
        if ($matching->isEmpty()) {
            return null;
        }
        if ($preferredShopId > 0) {
            $hit = $matching->first(function ($p) use ($preferredShopId) {
                return (int) ($p->shop_id ?? 0) === $preferredShopId;
            });
            if ($hit) {
                return Produk::find($hit->id_produk);
            }

            // Same code can exist in multiple shops — do not pick another shop's row when correcting a receipt.
            return null;
        }

        return Produk::find($matching->first()->id_produk);
    }

    private function supplierIsCashMop(int $supplierId): bool
    {
        $s = Supplier::find($supplierId);

        return $s && strtoupper(trim((string) ($s->mop ?? ''))) === 'CASH';
    }

    private function findCashPembelianDetailLine(?Produk $produk, int $jumlah, string $saleDate): ?PembelianDetail
    {
        if (! $produk || ! $this->supplierIsCashMop((int) $produk->id_supplier)) {
            return null;
        }
        $candidates = PembelianDetail::query()
            ->join('pembelian', 'pembelian.id_pembelian', '=', 'pembelian_detail.id_pembelian')
            ->where('pembelian_detail.id_produk', $produk->id_produk)
            ->where('pembelian.id_supplier', $produk->id_supplier)
            ->whereDate('pembelian.purchasedate2', $saleDate)
            ->orderByDesc('pembelian_detail.id_pembelian_detail')
            ->select('pembelian_detail.*')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }
        $hit = $candidates->firstWhere('jumlah', $jumlah);

        return $hit ? PembelianDetail::find($hit->id_pembelian_detail) : PembelianDetail::find($candidates->first()->id_pembelian_detail);
    }

    private function recalcPembelianHeader(int $idPembelian): void
    {
        $p = Pembelian::find($idPembelian);
        if (! $p) {
            return;
        }
        if ($p->details()->count() === 0) {
            $p->delete();

            return;
        }
        $p->total_harga = (float) $p->details()->sum('subtotal');
        $p->total_item = (int) $p->details()->sum('jumlah');
        $p->save();
    }

    private function appendCashPembelianDetailForSale(Produk $produk, int $jumlah, string $saleDate): void
    {
        if (Schema::hasColumn('produk', 'is_incomplete') && $produk->is_incomplete) {
            return;
        }
        if (! $this->supplierIsCashMop((int) $produk->id_supplier)) {
            return;
        }
        $subtotal = $jumlah * (float) $produk->harga_beli;
        $pembelian = Pembelian::where('id_supplier', $produk->id_supplier)
            ->whereDate('purchasedate2', $saleDate)
            ->orderByDesc('id_pembelian')
            ->first();
        if (! $pembelian) {
            $pembelian = Pembelian::create([
                'id_supplier' => $produk->id_supplier,
                'total_item' => $jumlah,
                'total_harga' => $subtotal,
                'reorder' => 0,
                'bayar' => 0,
                'purchasedate2' => $saleDate,
            ]);
            PembelianDetail::create([
                'id_pembelian' => $pembelian->id_pembelian,
                'id_produk' => $produk->id_produk,
                'harga_beli' => $produk->harga_beli,
                'jumlah' => $jumlah,
                'subtotal' => $subtotal,
            ]);

            return;
        }
        PembelianDetail::create([
            'id_pembelian' => $pembelian->id_pembelian,
            'id_produk' => $produk->id_produk,
            'harga_beli' => $produk->harga_beli,
            'jumlah' => $jumlah,
            'subtotal' => $subtotal,
        ]);
        $this->recalcPembelianHeader($pembelian->id_pembelian);
    }

    private function syncPembelianAfterReceiptLineProductChange(
        Penjualan $penjualan,
        ?Produk $oldProduk,
        Produk $newProduk,
        int $oldJumlah,
        int $newJumlah
    ): void {
        $saleDate = $penjualan->saledate
            ? Carbon::parse($penjualan->saledate)->toDateString()
            : $penjualan->created_at->toDateString();

        $oldPd = $this->findCashPembelianDetailLine($oldProduk, $oldJumlah, $saleDate);
        if ($oldPd) {
            $oidPem = (int) $oldPd->id_pembelian;
            $oldPd->delete();
            $this->recalcPembelianHeader($oidPem);
        }

        $this->appendCashPembelianDetailForSale($newProduk, $newJumlah, $saleDate);
    }

    private function syncInvoiceItemAfterReceiptLineProductChange(
        Penjualan $penjualan,
        int $oldProdukId,
        Produk $newProduk,
        int $oldJumlah,
        int $newJumlah,
        int $diskonPct
    ): void {
        if (! Schema::hasColumn('invoice_items', 'penjualan_id')) {
            return;
        }

        $saleDate = $penjualan->saledate
            ? Carbon::parse($penjualan->saledate)->toDateString()
            : $penjualan->created_at->toDateString();

        $newProduk->loadMissing('supplier');
        $newIsCash = $newProduk->supplier && strtoupper(trim((string) ($newProduk->supplier->mop ?? ''))) === 'CASH';
        $newIncomplete = Schema::hasColumn('produk', 'is_incomplete') && ! empty($newProduk->is_incomplete);
        $newSkipConsignment = $newIsCash || $newIncomplete;

        $ii = InvoiceItem::where('penjualan_id', $penjualan->id_penjualan)
            ->where('produk_id', $oldProdukId)
            ->first();

        if ($ii && (float) ($ii->amount_paid ?? 0) > 0.0001) {
            throw new \RuntimeException('This line has payments recorded against its consignment invoice. Change the product only after reversing those payments, or contact support.');
        }

        if ($ii && $newSkipConsignment) {
            $inv = Invoice::find($ii->invoice_id);
            if ($inv) {
                $inv->total = max(0, (float) $inv->total - (float) $ii->amount);
                $inv->save();
            }
            $ii->delete();
            if ($inv && (float) $inv->total <= 0 && InvoiceItem::where('invoice_id', $inv->id)->count() === 0) {
                $inv->delete();
            }

            return;
        }

        if (! $ii && ! $newSkipConsignment) {
            $newSupplier = $newProduk->supplier ?? Supplier::find($newProduk->id_supplier);
            if ($newSupplier) {
                $ledger = app(EnsureSaleSupplierLedgerService::class);
                if ($ledger->saleDefersLedgerUntilConfirmed($penjualan)) {
                    return;
                }
                $ledger->createConsignmentInvoiceItemIfNeeded(
                    $penjualan,
                    $newProduk,
                    $newSupplier,
                    $newJumlah,
                    $diskonPct
                );
            }

            return;
        }

        if (! $ii) {
            return;
        }

        $newAmount = round((float) $newProduk->harga_beli * $newJumlah, 2);
        $newSupplierId = (int) $newProduk->id_supplier;
        $oldSupplierId = (int) $ii->supplier_id;

        if ($oldSupplierId === $newSupplierId) {
            $ii->produk_id = $newProduk->id_produk;
            $ii->supplier_id = $newSupplierId;
            $ii->quantity = $newJumlah;
            $ii->amount = $newAmount;
            $ii->balance = $newAmount;
            $ii->discount = $diskonPct;
            $ii->save();

            return;
        }

        $oldInv = Invoice::find($ii->invoice_id);
        if ($oldInv) {
            $oldInv->total = max(0, (float) $oldInv->total - (float) $ii->amount);
            $oldInv->save();
        }
        $ii->delete();
        if ($oldInv && InvoiceItem::where('invoice_id', $oldInv->id)->count() === 0) {
            if ((float) ($oldInv->total ?? 0) <= 0) {
                $oldInv->delete();
            }
        }

        $newSupplier = $newProduk->supplier ?? Supplier::find($newSupplierId);
        if ($newSupplier) {
            $ledger = app(EnsureSaleSupplierLedgerService::class);
            if (! $ledger->saleDefersLedgerUntilConfirmed($penjualan)) {
                $ledger->createConsignmentInvoiceItemIfNeeded(
                    $penjualan,
                    $newProduk,
                    $newSupplier,
                    $newJumlah,
                    $diskonPct
                );
            }
        }
    }

    /**
     * Update linked product name (master data) from receipt confirmation modal — saves on blur from /penjualan.
     */
    public function updateItemProductName(Request $request, $id, $itemId)
    {
        if (! auth()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'nama_produk' => 'required|string|max:255',
        ]);

        Penjualan::findOrFail($id);
        $detail = PenjualanDetail::where('id_penjualan_detail', $itemId)
            ->where('id_penjualan', $id)
            ->firstOrFail();

        if (! $detail->id_produk) {
            return response()->json(['error' => 'This line has no linked product.'], 422);
        }

        $produk = Produk::find($detail->id_produk);
        if (! $produk) {
            return response()->json(['error' => 'Product not found.'], 404);
        }

        $produk->nama_produk = trim($request->nama_produk);
        $produk->save();

        return response()->json([
            'success' => true,
            'message' => 'Product name updated.',
            'nama_produk' => $produk->nama_produk,
        ]);
    }

    /**
     * Calculate receipt status based on all item statuses
     * Returns: 'confirmed', 'defect', 'review', or null (if items still pending)
     */
    private function calculateReceiptStatusFromItems($receiptId)
    {
        if (!Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            return null;
        }

        $items = PenjualanDetail::where('id_penjualan', $receiptId)->get();
        
        if ($items->isEmpty()) {
            return null;
        }

        $totalItems = $items->count();
        $confirmedItems = $items->where('item_confirmation_status', 'confirmed')->count();
        $defectItems = $items->where('item_confirmation_status', 'defect')->count();
        $pendingItems = $items->filter(function($item) {
            return empty($item->item_confirmation_status) || $item->item_confirmation_status === 'pending';
        })->count();

        // If all items are confirmed, receipt is confirmed
        if ($confirmedItems == $totalItems) {
            return 'confirmed';
        }

        // If all items are defect, receipt is defect
        if ($defectItems == $totalItems) {
            return 'defect';
        }

        // If there are any pending items, keep in review
        if ($pendingItems > 0) {
            return 'review';
        }

        // If there's a mix of confirmed and defect (no pending), mark as defect
        // (since defect items need attention)
        if ($defectItems > 0) {
            return 'defect';
        }

        // If all are confirmed (should have been caught above, but just in case)
        if ($confirmedItems == $totalItems) {
            return 'confirmed';
        }

        // Default: keep in review
        return 'review';
    }

    /**
     * Export receipt confirmation report to PDF
     */
    public function exportPdf(Request $request)
    {
        // Only admins can access
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized. Only administrators can export Receipt Confirmation reports.');
        }

        $query = Penjualan::with(['member', 'user', 'details.produk.shop'])
            ->where('status', 'completed')
            ->orderBy('id_penjualan', 'desc');

        $this->applyReceiptConfirmationStatusScope($query, $request->input('confirmation_status'));

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('saledate', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('saledate', '<=', $request->input('end_date'));
        }

        // Filter by receipt number
        if ($request->filled('receipt_number')) {
            $query->where('receiptno', 'like', '%' . $request->receipt_number . '%');
        }

        // Filter by shop name
        if ($request->filled('shop_name')) {
            $query->whereHas('details.produk.shop', function($q) use ($request) {
                $q->where('shop_name', 'like', '%' . $request->shop_name . '%');
            });
        }

        $penjualans = $query->get();
        $setting = Setting::first();

        // Prepare data for PDF
        $reportData = [];
        foreach ($penjualans as $penjualan) {
            // Get status
            if (Schema::hasColumn('penjualan', 'confirmation_status')) {
                $status = $penjualan->confirmation_status ?? 'pending';
            } else {
                $status = 'pending';
            }
            
            // Check if it was edited from defect
            $wasEditedFromDefect = false;
            if (Schema::hasColumn('penjualan', 'was_edited_from_defect')) {
                $wasEditedFromDefect = $penjualan->was_edited_from_defect ?? false;
            }
            
            // Display status
            if ($status === 'confirmed' && $wasEditedFromDefect) {
                $displayStatus = 'Confirmed with Edit';
            } elseif ($status === 'pending') {
                $displayStatus = 'Pending';
            } elseif ($status === 'confirmed') {
                $displayStatus = 'Confirmed';
            } elseif ($status === 'defect') {
                $displayStatus = 'Defect';
            } elseif ($status === 'review') {
                $displayStatus = 'Review';
            } else {
                $displayStatus = ucfirst($status);
            }

            // Get shop names
            $shops = $penjualan->details->map(function($detail) {
                return $detail->produk->shop->shop_name ?? 'N/A';
            })->unique()->values();
            $shopNames = $shops->implode(', ');

            // Get item names
            $items = $penjualan->details->map(function($detail) {
                if ($detail->produk) {
                    $productName = $detail->produk->nama_produk ?? 'N/A';
                    $quantity = $detail->jumlah ?? 1;
                    return $productName . ' (x' . $quantity . ')';
                }
                return 'N/A';
            })->filter()->values();
            $itemsList = $items->implode(', ');

            $reportData[] = [
                'receipt_number' => $penjualan->receiptno,
                'shop_name' => $shopNames,
                'items_sold' => $penjualan->total_item,
                'items_name' => $itemsList,
                'total_amount' => $penjualan->bayar,
                'date_of_sale' => $penjualan->saledate ? Carbon::parse($penjualan->saledate)->format('d/m/Y') : '-',
                'status' => $displayStatus,
                'cashier' => $penjualan->user->name ?? 'N/A',
            ];
        }

        // Calculate totals
        $totalAmount = $penjualans->sum('bayar');
        $totalItems = $penjualans->sum('total_item');

        // Generate filename
        $startDate = $request->input('start_date', 'all');
        $endDate = $request->input('end_date', 'all');
        $statusFilter = $request->input('confirmation_status', 'all');
        $filename = 'receipt_confirmation_' . $statusFilter . '_' . $startDate . '_to_' . $endDate . '.pdf';

        $pdf = PDF::loadView('receipt-confirmation.pdf', compact(
            'reportData',
            'setting',
            'totalAmount',
            'totalItems',
            'startDate',
            'endDate',
            'statusFilter'
        ));

        $pdf->setPaper('a4', 'landscape');
        return $pdf->stream($filename);
    }

    private function produkIsQuickAddIncomplete(?Produk $produk): bool
    {
        if (! $produk || ! Schema::hasColumn('produk', 'is_incomplete')) {
            return false;
        }

        return (int) ($produk->is_incomplete ?? 0) === 1;
    }

    private function penjualanHasQuickAddIncompleteItems(Penjualan $penjualan): bool
    {
        if (! Schema::hasColumn('produk', 'is_incomplete')) {
            return false;
        }

        $penjualan->loadMissing('details.produk');

        return $penjualan->details->contains(function ($detail) {
            return $this->produkIsQuickAddIncomplete($detail->produk);
        });
    }

    private function receiptHasQuickAddIncompleteItems(int $penjualanId): bool
    {
        if (! Schema::hasColumn('produk', 'is_incomplete')) {
            return false;
        }

        return PenjualanDetail::query()
            ->where('id_penjualan', $penjualanId)
            ->whereHas('produk', function ($q) {
                $q->where('is_incomplete', 1);
            })
            ->exists();
    }

    private function quickAddBlockResponse(int $penjualanId)
    {
        if (! $this->receiptHasQuickAddIncompleteItems($penjualanId)) {
            return null;
        }

        return response()->json([
            'error' => 'This receipt has item(s) still in Quick Add. Complete or remove them in Products → Quick Add before confirming.',
        ], 422);
    }
}

