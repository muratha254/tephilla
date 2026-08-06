<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\Produk;
use App\Models\ProdukHistory;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\Setting;
use App\Models\PurchaseOrderBatch;
use App\Models\PurchaseOrderBatchItem;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use Illuminate\Support\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDF;
use App\Models\ProdukEditLog;
use App\Services\EnsureSaleSupplierLedgerService;
use App\Services\ProductPriceRetroactiveApplyService;
use App\Services\ProductSupplierSalesReassignService;
use App\Services\ProdukEditLogService;

class ProdukController extends Controller
{
    /**
     * Parse filter dates from Stock List (yyyy-mm-dd or dd/mm/yyyy).
     */
    private function normalizeStockListFilterDate($dateString): ?string
    {
        if (empty($dateString) || ! is_string($dateString)) {
            return null;
        }
        $trimmed = trim($dateString);
        if ($trimmed === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return $trimmed;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $trimmed, $m)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $trimmed)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        try {
            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $kategori = Kategori::all()->pluck('nama_kategori', 'id_kategori');
        $shop = Shop::all()->pluck('shop_name', 'id');
        $supplier = Supplier::all()->pluck('nama', 'id_supplier');

        return view('produk.index', compact('kategori', 'shop', 'supplier'));
    }

    /**
     * Base query for Stock List — same filters as DataTables (shop, dates, optional filters, search).
     * Search matches: product code, item code, name, shop name, supplier name.
     */
    private function stockListFilteredQuery(Request $request)
    {
        $salesSubquery = DB::table('penjualan_detail as pd')
            ->select('pd.id_produk', DB::raw('SUM(pd.jumlah) as total_sold'));
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesSubquery->join('penjualan as p', 'p.id_penjualan', '=', 'pd.id_penjualan')
                ->where('p.status', 'completed');
        }
        $salesSubquery->groupBy('pd.id_produk');

        // In = Left + Sold (always matches the stock list). Stock History page keeps per-event detail.
        $itemInSql = '(produk.stok + COALESCE(sales.total_sold, 0))';
        $query = Produk::leftJoin('kategori', 'kategori.id_kategori', 'produk.id_kategori')
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
            ->leftJoinSub($salesSubquery, 'sales', 'sales.id_produk', '=', 'produk.id_produk');

        $query->select(
            'produk.*',
            'kategori.nama_kategori',
            'shops.shop_name',
            'supplier.nama as supplier_name',
            DB::raw('COALESCE(sales.total_sold, 0) as items_sold'),
            DB::raw($itemInSql.' as item_in'),
            DB::raw('produk.stok as remaining')
        );

        if ($request->filled('shop_id')) {
            $query->where('produk.shop_id', $request->input('shop_id'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('produk.id_supplier', $request->input('supplier_id'));
        }
        if ($request->filled('category_id')) {
            $query->where('produk.id_kategori', $request->input('category_id'));
        }
        if ($request->filled('stock_status')) {
            $status = $request->input('stock_status');
            if ($status === 'out_of_stock') {
                $query->where('produk.stok', '<=', 0);
            } elseif ($status === 'low_stock') {
                $query->where('produk.stok', '>', 0)
                    ->whereRaw('produk.stok <= COALESCE(produk.reorder_level, produk.reorder, 0)');
            } elseif ($status === 'in_stock') {
                $query->where('produk.stok', '>', 0)
                    ->whereRaw('(produk.reorder_level IS NULL AND produk.reorder IS NULL) OR (produk.stok > COALESCE(produk.reorder_level, produk.reorder, 0))');
            }
        }
        $startDate = $this->normalizeStockListFilterDate($request->input('start_date'));
        if ($startDate) {
            $query->whereRaw('DATE(COALESCE(produk.date_in, produk.created_at)) >= ?', [$startDate]);
        }
        $endDate = $this->normalizeStockListFilterDate($request->input('end_date'));
        if ($endDate) {
            $query->whereRaw('DATE(COALESCE(produk.date_in, produk.created_at)) <= ?', [$endDate]);
        }

        // Exact row from quick picker (Select2) — takes precedence over free-text search
        if ($request->filled('match_produk_id')) {
            $query->where('produk.id_produk', (int) $request->input('match_produk_id'));

            return $query;
        }

        $search = trim((string) $request->input('q', ''));
        if ($search === '') {
            $search = trim((string) $request->input('search.value', ''));
        }
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('produk.kode_produk', 'like', $like)
                    ->orWhere('produk.item_code', 'like', $like)
                    ->orWhere('produk.nama_produk', 'like', $like)
                    ->orWhere('shops.shop_name', 'like', $like)
                    ->orWhere('supplier.nama', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * Lightweight JSON for Stock List quick search (Select2): code, item_code, or name.
     */
    public function stockQuickSearch(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 1) {
            return response()->json(['results' => []]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $builder = Produk::query()
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->select(
                'produk.id_produk',
                'produk.nama_produk',
                'produk.kode_produk',
                'produk.item_code',
                'shops.shop_name'
            )
            ->where(function ($w) use ($like) {
                $w->where('produk.kode_produk', 'like', $like)
                    ->orWhere('produk.item_code', 'like', $like)
                    ->orWhere('produk.nama_produk', 'like', $like);
            });

        if ($request->filled('shop_id')) {
            $builder->where('produk.shop_id', (int) $request->query('shop_id'));
        }

        $rows = $builder->orderBy('produk.nama_produk')->limit(30)->get();
        $results = $rows->map(function ($p) {
            $code = trim((string) ($p->kode_produk ?? ''));
            if ($code === '') {
                $code = trim((string) ($p->item_code ?? ''));
            }
            $name = trim((string) ($p->nama_produk ?? ''));
            $shop = trim((string) ($p->shop_name ?? ''));
            $left = $code !== '' ? $code.' — '.$name : $name;
            $text = $shop !== '' ? $left.' ('.$shop.')' : $left;

            return [
                'id' => (int) $p->id_produk,
                'text' => $text !== '' ? $text : ('#'.$p->id_produk),
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Get products data for DataTables with server-side processing
     * Optimized for large datasets (100k+ products) using pagination
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function data(Request $request)
    {
        $query = $this->stockListFilteredQuery($request);

        $shops = Shop::orderBy('shop_name')->pluck('shop_name', 'id');
        $suppliers = Supplier::orderBy('nama')->pluck('nama', 'id_supplier');

        // ORDER BY must reference real columns — not all DBs have both reorder_level and reorder
        $reorderOrderSql = '0';
        try {
            if (Schema::hasColumn('produk', 'reorder_level') && Schema::hasColumn('produk', 'reorder')) {
                $reorderOrderSql = 'COALESCE(produk.reorder_level, produk.reorder, 0)';
            } elseif (Schema::hasColumn('produk', 'reorder_level')) {
                $reorderOrderSql = 'COALESCE(produk.reorder_level, 0)';
            } elseif (Schema::hasColumn('produk', 'reorder')) {
                $reorderOrderSql = 'COALESCE(produk.reorder, 0)';
            }
        } catch (\Throwable $e) {
            \Log::warning('ProdukController@data reorder column check: '.$e->getMessage());
            $reorderOrderSql = '0';
        }

        return datatables()
            ->of($query)
            ->filterColumn('item_code', function($query, $keyword) {
                // Custom filter for item_code - search in both kode_produk and item_code
                $query->where(function($q) use ($keyword) {
                    $q->where('produk.kode_produk', 'like', "%{$keyword}%")
                      ->orWhere('produk.item_code', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('shop_name', function($query, $keyword) {
                $query->where('shops.shop_name', 'like', "%{$keyword}%");
            })
            ->filterColumn('supplier_name', function($query, $keyword) {
                $query->where('supplier.nama', 'like', "%{$keyword}%");
            })
            // nama_produk: qualify table (joins) — global search only hits searchable columns (see index.blade.js)
            ->filterColumn('nama_produk', function ($query, $keyword) {
                $query->where('produk.nama_produk', 'like', "%{$keyword}%");
            })
            ->orderColumn('item_code', 'produk.kode_produk $1')
            ->orderColumn('nama_produk', 'produk.nama_produk $1')
            ->orderColumn('shop_name', 'shops.shop_name $1')
            ->orderColumn('supplier_name', 'supplier.nama $1')
            ->orderColumn('created_at', 'COALESCE(produk.date_in, produk.created_at) $1')
            ->orderColumn('remaining', 'produk.stok $1')
            ->orderColumn('harga_beli', 'produk.harga_beli $1')
            ->orderColumn('harga_jual', 'produk.harga_jual $1')
            ->orderColumn('reorder_level', $reorderOrderSql.' $1')
            ->orderColumn('item_in', 'item_in $1')
            ->addIndexColumn()
            ->addColumn('select_all', function ($produk) {
                return '
                    <input type="checkbox" name="id_produk[]" value="'. $produk->id_produk .'">
                ';
            })
            ->addColumn('aksi', function ($produk) {
                $u = auth()->user();
                $buttons = '<div class="btn-group produk-actions-inline">';
                $buttons .= '<button type="button" class="btn btn-xs btn-info btn-flat btn-open-stock-movement" '
                    .'data-url="'.e(route('produk.stock-movement', $produk->id_produk)).'" '
                    .'data-produk-id="'.(int) $produk->id_produk.'" '
                    .'data-product-name="'.e($produk->nama_produk ?? '').'" '
                    .'title="View stock movement"><i class="fa fa-list-ul"></i></button>';
                if ($u && ($u->inv_update ?? false)) {
                    $codeLabel = ! empty($produk->kode_produk) ? $produk->kode_produk : ($produk->item_code ?? '');
                    $buttons .= '<button type="button" class="btn btn-xs btn-warning btn-flat btn-open-merge-duplicate" '
                        .'data-produk-id="'.(int) $produk->id_produk.'" '
                        .'data-item-code="'.e($codeLabel).'" '
                        .'data-product-name="'.e($produk->nama_produk ?? '').'" '
                        .'data-shop-id="'.(int) ($produk->shop_id ?? 0).'" '
                        .'data-stok="'.(int) ($produk->stok ?? 0).'" '
                        .'title="Merge into another product (keeps all sales)"><i class="fa fa-compress"></i></button>';
                    $buttons .= '<button type="button" onclick="editForm(`'. route('produk.update', $produk->id_produk) .'`)" class="btn btn-xs btn-primary btn-flat"><i class="fa fa-pencil"></i></button>';
                    $buttons .= '<button type="button" class="btn btn-xs btn-success btn-flat btn-open-update-stock" '
                        .'data-restock-url="'.e(route('produk.restock')).'" '
                        .'data-produk-id="'.(int) $produk->id_produk.'" '
                        .'data-product-name="'.e($produk->nama_produk ?? '').'" '
                        .'data-current-stock="'.(int) ($produk->stok ?? 0).'" '
                        .'title="Update Stock"><i class="fa fa-plus-circle"></i></button>';
                }
                if ($u && ($u->inv_delete ?? false)) {
                    $buttons .= '<button type="button" onclick="deleteData(`'. route('produk.destroy', $produk->id_produk) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>';
                }
                $buttons .= '</div>';

                return $buttons;
            })
            ->addColumn('item_code', function ($produk) {
                // Use kode_produk if item_code is empty or contains product name
                // item_code sometimes contains product name, kode_produk has the actual code
                $code = !empty($produk->kode_produk) ? $produk->kode_produk : ($produk->item_code ?? '-');
                return '<span class="label label-success">'. $code .'</span>';
            })
            ->editColumn('nama_produk', function ($produk) {
                $u = auth()->user();
                if (! $u || ! ($u->inv_update ?? false)) {
                    return e($produk->nama_produk);
                }
                $url = route('produk.update', $produk->id_produk);
                $val = e($produk->nama_produk);

                return '<input type="text" class="form-control input-sm js-inline-produk" data-field="nama_produk" data-update-url="'.e($url).'" data-original="'.$val.'" value="'.$val.'" />';
            })
            ->editColumn('shop_name', function ($produk) use ($shops) {
                $u = auth()->user();
                if (! $u || ! ($u->inv_update ?? false)) {
                    $name = (string) ($produk->shop_name ?? '');
                    if ($name !== '' && mb_strlen($name) > 22) {
                        $esc = e($name);

                        return '<span class="produk-cell-ellipsis" title="'.$esc.'">'.e(Str::limit($name, 22)).'</span>';
                    }

                    return e($name ?: '-');
                }
                $url = route('produk.update', $produk->id_produk);
                $sid = (int) $produk->shop_id;
                $html = '<select class="form-control input-sm js-inline-produk" data-field="shop_id" data-update-url="'.e($url).'" data-original="'.$sid.'">';
                foreach ($shops as $id => $name) {
                    $sel = ((int) $id === $sid) ? ' selected' : '';
                    $html .= '<option value="'.(int) $id.'"'.$sel.'>'.e($name).'</option>';
                }
                $html .= '</select>';

                return $html;
            })
            ->editColumn('supplier_name', function ($produk) use ($suppliers) {
                $u = auth()->user();
                if (! $u || ! ($u->inv_update ?? false)) {
                    $name = (string) ($produk->supplier_name ?? '');
                    if ($name !== '' && mb_strlen($name) > 22) {
                        $esc = e($name);

                        return '<span class="produk-cell-ellipsis" title="'.$esc.'">'.e(Str::limit($name, 22)).'</span>';
                    }

                    return e($name ?: '-');
                }
                $url = route('produk.update', $produk->id_produk);
                $spid = (int) $produk->id_supplier;
                $html = '<select class="form-control input-sm js-inline-produk" data-field="id_supplier" data-update-url="'.e($url).'" data-original="'.$spid.'">';
                foreach ($suppliers as $id => $name) {
                    $sel = ((int) $id === $spid) ? ' selected' : '';
                    $html .= '<option value="'.(int) $id.'"'.$sel.'>'.e($name).'</option>';
                }
                $html .= '</select>';

                return $html;
            })
            ->editColumn('harga_beli', function ($produk) {
                $u = auth()->user();
                if (! $u || ! ($u->inv_update ?? false)) {
                    return format_uang($produk->harga_beli);
                }
                $url = route('produk.update', $produk->id_produk);
                $hb = (float) ($produk->harga_beli ?? 0);

                return '<input type="number" step="0.01" min="0" class="form-control input-sm js-inline-produk" data-field="harga_beli" data-update-url="'.e($url).'" data-original="'.$hb.'" value="'.$hb.'" style="max-width:110px;" />';
            })
            ->editColumn('harga_jual', function ($produk) {
                $u = auth()->user();
                if (! $u || ! ($u->inv_update ?? false)) {
                    return format_uang($produk->harga_jual);
                }
                $url = route('produk.update', $produk->id_produk);
                $hj = (float) ($produk->harga_jual ?? 0);

                return '<input type="number" step="0.01" min="0" class="form-control input-sm js-inline-produk" data-field="harga_jual" data-update-url="'.e($url).'" data-original="'.$hj.'" value="'.$hj.'" style="max-width:110px;" />';
            })
            ->editColumn('reorder_level', function ($produk) {
                $u = auth()->user();
                if (! $u || ! ($u->inv_update ?? false)) {
                    return (string) ($produk->reorder_level ?? $produk->reorder ?? '0');
                }
                $url = route('produk.update', $produk->id_produk);
                $rl = (int) ($produk->reorder_level ?? $produk->reorder ?? 0);

                return '<input type="number" min="0" step="1" class="form-control input-sm js-inline-produk" data-field="reorder_level" data-update-url="'.e($url).'" data-original="'.$rl.'" value="'.$rl.'" style="max-width:80px;" />';
            })
            ->editColumn('item_in', function ($produk) {
                $u = auth()->user();
                $itemIn = (int) round((float) ($produk->item_in ?? 0));
                if (! $u || ! ($u->inv_update ?? false)) {
                    return '<span class="label label-info">'. format_uang($itemIn) .'</span>';
                }
                $url = route('produk.update', $produk->id_produk);
                $title = 'Total received (Left + Sold). Saving updates Left and fixes stock-in history to match.';

                return '<input type="number" step="1" class="form-control input-sm js-inline-produk" data-field="item_in" data-inline-item-in="1" data-update-url="'.e($url).'" data-original="'.$itemIn.'" value="'.$itemIn.'" title="'.e($title).'" style="max-width:90px;" />';
            })
            ->addColumn('items_sold', function ($produk) {
                $sold = $produk->items_sold ?? 0;
                return '<span class="label label-warning">'. format_uang($sold) .'</span>';
            })
            ->addColumn('remaining', function ($produk) {
                $u = auth()->user();
                $stok = (int) ($produk->stok ?? 0);
                if ($u && ($u->inv_update ?? false)) {
                    $updateUrl = route('produk.update', $produk->id_produk);

                    return '<input type="number" step="1" '
                        .'class="form-control produk-inline-stok js-inline-remaining" '
                        .'data-update-url="'.e($updateUrl).'" '
                        .'data-id-produk="'.(int) $produk->id_produk.'" '
                        .'data-original="'.e((string) $stok).'" '
                        .'value="'.e((string) $stok).'" '
                        .'style="max-width:96px;min-width:72px;display:inline-block;" />';
                }

                $labelClass = 'label-success';
                if ($produk->remaining <= 0) {
                    $labelClass = 'label-danger';
                } elseif ($produk->reorder && $produk->remaining <= $produk->reorder) {
                    $labelClass = 'label-warning';
                }

                return '<span class="label '. $labelClass .'">'. format_uang($produk->remaining) .'</span>';
            })
            ->addColumn('stok', function ($produk) {
                $labelClass = 'label-success';
                if ($produk->stok <= 0) {
                    $labelClass = 'label-danger';
                } elseif ($produk->reorder && $produk->stok <= $produk->reorder) {
                    $labelClass = 'label-warning';
                }

                return '<span class="label '. $labelClass .'">'. format_uang($produk->stok) .'</span>';
            })
            ->addColumn('stok_raw', function ($produk) {
                return $produk->stok;
            })
            ->editColumn('created_at', function ($produk) {
                $date = $produk->date_in ?? $produk->created_at;

                return $date ? Carbon::parse($date)->format('d/m/Y') : '-';
            })
            ->rawColumns(['aksi', 'item_code', 'select_all', 'nama_produk', 'shop_name', 'supplier_name', 'harga_beli', 'harga_jual', 'reorder_level', 'stok', 'item_in', 'items_sold', 'remaining'])
            ->make(true);
    }

    /**
     * JSON timeline of stock movement (same data as PDF export).
     */
    public function stockMovement(Request $request, $produk)
    {
        $id = (int) $produk;
        Produk::findOrFail($id);

        return response()->json($this->buildStockMovementData($id));
    }

    /**
     * PDF export — stock movement timeline (landscape).
     */
    public function exportStockMovementPdf(Request $request, $produk)
    {
        try {
            @ini_set('memory_limit', '256M');
            set_time_limit(120);

            $id = (int) $produk;
            Produk::findOrFail($id);

            $data = $this->buildStockMovementData($id);
            $setting = Setting::first();

            $pdf = PDF::loadView('produk.stock_movement_pdf', [
                'setting' => $setting,
                'product' => $data['product'],
                'events' => $data['events'],
                'note' => $data['note'],
                'generatedAt' => now(),
            ]);
            $pdf->setPaper('a4', 'landscape');

            $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $data['product']['name'] ?? 'product');
            $safe = trim($safe, '_') ?: 'product';

            return $pdf->stream('stock_movement_'.$safe.'_'.date('Y-m-d_His').'.pdf');
        } catch (\Throwable $e) {
            \Log::error('exportStockMovementPdf: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Failed to generate PDF: '.$e->getMessage()], 500);
        }
    }

    /**
     * Build product + events + note for stock movement (JSON + PDF).
     */
    private function buildStockMovementData(int $id): array
    {
        $product = Produk::with(['supplier', 'shop'])->findOrFail($id);

        $events = [];
        $histories = collect();

        if (Schema::hasTable('produk_history')) {
            $histories = ProdukHistory::where('id_produk', $id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($histories as $h) {
                $at = $h->created_at ? Carbon::parse($h->created_at) : now();
                $delta = (int) ($h->restock_amount ?? 0);
                $events[] = [
                    'kind' => 'history',
                    'history_type' => (string) ($h->type ?? ''),
                    'sort_at' => $at->format('Y-m-d H:i:s'),
                    'sort_key' => $at->timestamp * 100000 + (int) $h->id,
                    'date_label' => $at->format('d/m/Y'),
                    'time_label' => $at->format('H:i'),
                    'title' => $this->stockMovementHistoryTitle($h->type ?? ''),
                    'detail' => (string) ($h->notes ?? ''),
                    'previous_stock' => (int) ($h->previous_stock ?? 0),
                    'change' => $delta,
                    'balance_after' => (int) ($h->current_stock ?? 0),
                ];
            }
        }

        // Purchases (imports / supplier PO) from pembelian — skip lines already covered by restock history (same calendar day + qty)
        if (Schema::hasTable('pembelian') && Schema::hasTable('pembelian_detail')) {
            $restockMatchSlots = $this->stockMovementRestockMatchSlots($histories);
            $purchaseRows = DB::table('pembelian_detail as pdd')
                ->join('pembelian as pb', 'pb.id_pembelian', '=', 'pdd.id_pembelian')
                ->leftJoin('supplier as s', 's.id_supplier', '=', 'pb.id_supplier')
                ->where('pdd.id_produk', $id)
                ->select([
                    'pdd.id_pembelian_detail',
                    'pdd.jumlah',
                    'pdd.harga_beli',
                    'pb.id_pembelian',
                    'pb.purchasedate2',
                    'pb.created_at as pembelian_created_at',
                    's.nama as supplier_name',
                ])
                ->orderBy('pb.purchasedate2')
                ->orderBy('pb.id_pembelian')
                ->orderBy('pdd.id_pembelian_detail')
                ->get();

            foreach ($purchaseRows as $row) {
                $qty = (int) ($row->jumlah ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $purchaseDate = $row->purchasedate2 ? Carbon::parse($row->purchasedate2)->format('Y-m-d') : '';

                if ($this->stockMovementConsumeRestockMatch($restockMatchSlots, $purchaseDate, $qty)) {
                    continue;
                }

                if ($this->stockMovementPurchaseIsAutoFromCashSale($id, $row)) {
                    continue;
                }

                $at = $row->purchasedate2 ? Carbon::parse($row->purchasedate2)->startOfDay() : now();
                if (! empty($row->pembelian_created_at)) {
                    $tc = Carbon::parse($row->pembelian_created_at);
                    $at->setTime($tc->hour, $tc->minute, $tc->second);
                }

                $supplierLabel = $row->supplier_name ? (string) $row->supplier_name : '';
                $detailParts = ['Purchase #'.(int) $row->id_pembelian];
                if ($supplierLabel !== '') {
                    $detailParts[] = 'Supplier: '.$supplierLabel;
                }
                $detailParts[] = 'Qty '.$qty;
                if (! empty($row->harga_beli)) {
                    $detailParts[] = '@ '.format_uang((float) $row->harga_beli);
                }

                $events[] = [
                    'kind' => 'purchase',
                    'sort_at' => $at->format('Y-m-d H:i:s'),
                    'sort_key' => $at->timestamp * 100000 + (int) $row->id_pembelian_detail + 250000,
                    'date_label' => $at->format('d/m/Y'),
                    'time_label' => $at->format('H:i'),
                    'title' => 'Purchase (supplier / import)',
                    'detail' => implode(' · ', $detailParts),
                    'previous_stock' => null,
                    'change' => $qty,
                    'balance_after' => null,
                ];
            }
        }

        $hasPenjualanStatus = Schema::hasColumn('penjualan', 'status');
        $saleQuery = DB::table('penjualan_detail as pd')
            ->join('penjualan as p', 'p.id_penjualan', '=', 'pd.id_penjualan')
            ->where('pd.id_produk', $id)
            ->select([
                'pd.id_penjualan_detail',
                'pd.jumlah',
                'pd.created_at as detail_created_at',
                'p.id_penjualan',
                'p.saledate',
                'p.created_at as penjualan_created_at',
                'p.updated_at as penjualan_updated_at',
                'p.receiptno',
            ]);

        if ($hasPenjualanStatus) {
            $saleQuery->where('p.status', 'completed');
        }

        $sales = $saleQuery
            ->orderBy('p.saledate')
            ->orderBy('p.id_penjualan')
            ->orderBy('pd.id_penjualan_detail')
            ->get();

        foreach ($sales as $s) {
            $at = $this->stockMovementSaleTimestamp($s);

            $qty = (int) ($s->jumlah ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $receipt = $s->receiptno ?? '';
            $detail = $receipt !== '' ? ('Receipt '.$receipt) : 'Sale';

            $events[] = [
                'kind' => 'sale',
                'sort_at' => $at->format('Y-m-d H:i:s'),
                'sort_key' => $at->timestamp * 100000 + (int) $s->id_penjualan_detail,
                'date_label' => $at->format('d/m/Y'),
                'time_label' => $at->format('H:i'),
                'title' => 'Items sold',
                'detail' => $detail,
                'previous_stock' => null,
                'change' => -$qty,
                'balance_after' => null,
            ];
        }

        if (Schema::hasTable('produk_edit_log')) {
            $editLogService = app(ProdukEditLogService::class);
            $editLogs = ProdukEditLog::where('id_produk', $id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($editLogs as $log) {
                $at = $log->created_at ? Carbon::parse($log->created_at) : now();
                $changes = is_array($log->changes) ? $log->changes : [];
                $stokChange = null;
                $balanceAfterEdit = null;
                foreach ($changes as $c) {
                    if (($c['field'] ?? '') !== 'stok') {
                        continue;
                    }
                    $oldStok = (int) preg_replace('/\D/', '', (string) ($c['old'] ?? '0'));
                    $newStok = (int) preg_replace('/\D/', '', (string) ($c['new'] ?? '0'));
                    $stokChange = $newStok - $oldStok;
                    $balanceAfterEdit = $newStok;
                    break;
                }
                $events[] = [
                    'kind' => 'edit',
                    'sort_at' => $at->format('Y-m-d H:i:s'),
                    'sort_key' => $at->timestamp * 100000 + (int) $log->id + 400000,
                    'date_label' => $at->format('d/m/Y'),
                    'time_label' => $at->format('H:i'),
                    'title' => 'Product edited',
                    'detail' => $editLogService->formatChangesDetail($changes, $log->user_name),
                    'previous_stock' => null,
                    'change' => $stokChange,
                    'balance_after' => $balanceAfterEdit,
                ];
            }
        }

        usort($events, function ($a, $b) {
            if ($a['sort_at'] === $b['sort_at']) {
                return $a['sort_key'] <=> $b['sort_key'];
            }

            return strcmp($a['sort_at'], $b['sort_at']);
        });

        $soldSoFar = 0;
        $running = 0;
        foreach ($events as &$ev) {
            if ($ev['kind'] === 'sale') {
                $running += (int) ($ev['change'] ?? 0);
                $soldSoFar += abs((int) ($ev['change'] ?? 0));
            } elseif ($ev['kind'] === 'history') {
                $historyType = (string) ($ev['history_type'] ?? '');
                if ($historyType === 'stock_list_reconcile') {
                    $totalIn = (int) ($ev['balance_after'] ?? 0);
                    $running = $totalIn - $soldSoFar;
                } else {
                    $running = (int) ($ev['balance_after'] ?? $running);
                }
            } elseif ($ev['kind'] === 'edit') {
                if ($ev['balance_after'] !== null) {
                    $running = (int) $ev['balance_after'];
                } elseif ($ev['change'] !== null) {
                    $running += (int) $ev['change'];
                }
            } else {
                $running += (int) ($ev['change'] ?? 0);
            }
            $ev['balance_display'] = $running;
        }
        unset($ev);

        $product->refresh();
        $systemStock = (int) ($product->stok ?? 0);
        $expectedFromLedger = $this->expectedStokFromLedger($id);
        $currentStock = $expectedFromLedger ?? $systemStock;
        $note = '';
        if ($expectedFromLedger !== null && $expectedFromLedger !== $systemStock) {
            $note = 'Current stock on file ('.$systemStock.') does not match stock received minus completed sales ('.$expectedFromLedger.'). '
                .'Run: php artisan produk:reconcile-stock --code='.($product->kode_produk ?: $product->item_code ?: $id).' to align.';
        }

        return [
            'product' => [
                'id' => $product->id_produk,
                'name' => $product->nama_produk,
                'code' => ! empty($product->kode_produk) ? $product->kode_produk : ($product->item_code ?? ''),
                'current_stock' => $currentStock,
                'system_stock' => $systemStock,
                'expected_stock' => $expectedFromLedger,
                'supplier' => optional($product->supplier)->nama,
                'shop' => optional($product->shop)->shop_name,
            ],
            'events' => array_values($events),
            'note' => $note,
        ];
    }

    /**
     * Best timestamp for a sale line (avoid 00:00 when saledate is date-only).
     *
     * @param  object  $s  Row with detail_created_at, penjualan_created_at, penjualan_updated_at, saledate
     */
    private function stockMovementSaleTimestamp($s): Carbon
    {
        $line = ! empty($s->detail_created_at) ? Carbon::parse($s->detail_created_at) : null;
        $u = ! empty($s->penjualan_updated_at) ? Carbon::parse($s->penjualan_updated_at) : null;
        $c = ! empty($s->penjualan_created_at) ? Carbon::parse($s->penjualan_created_at) : null;

        // Prefer line or header timestamps that are not midnight-only
        foreach ([$line, $u, $c] as $candidate) {
            if ($candidate && $candidate->format('H:i:s') !== '00:00:00') {
                return $candidate;
            }
        }

        if ($line) {
            return $line;
        }
        if ($u) {
            return $u;
        }
        if ($c) {
            return $c;
        }
        if (! empty($s->saledate)) {
            return Carbon::parse($s->saledate);
        }

        return now();
    }

    /**
     * Pembelian created when a cash-supplier sale completes (PenjualanController) — not a separate stock receipt.
     */
    private function stockMovementPurchaseIsAutoFromCashSale(int $idProduk, $pddRow): bool
    {
        $supplierId = DB::table('pembelian')->where('id_pembelian', $pddRow->id_pembelian)->value('id_supplier');
        if (! $supplierId) {
            return false;
        }

        $supplier = Supplier::find($supplierId);
        if (! $supplier || strtoupper(trim($supplier->mop ?? '')) !== 'CASH') {
            return false;
        }

        $qty = (int) ($pddRow->jumlah ?? 0);
        $purchaseDate = $pddRow->purchasedate2 ? Carbon::parse($pddRow->purchasedate2)->format('Y-m-d') : '';
        if ($purchaseDate === '' || $qty <= 0) {
            return false;
        }

        $pembelianAt = ! empty($pddRow->pembelian_created_at) ? Carbon::parse($pddRow->pembelian_created_at) : null;
        if (! $pembelianAt) {
            return false;
        }

        $hasPenjualanStatus = Schema::hasColumn('penjualan', 'status');
        $q = DB::table('penjualan as p')
            ->join('penjualan_detail as pd', 'pd.id_penjualan', '=', 'p.id_penjualan')
            ->where('pd.id_produk', $idProduk)
            ->where('pd.jumlah', $qty)
            ->whereDate('p.saledate', $purchaseDate)
            ->select(['p.created_at', 'p.updated_at']);

        if ($hasPenjualanStatus) {
            $q->where('p.status', 'completed');
        }

        foreach ($q->get() as $sale) {
            $saleAt = ! empty($sale->updated_at) ? Carbon::parse($sale->updated_at) : Carbon::parse($sale->created_at);
            if (abs($pembelianAt->diffInSeconds($saleAt)) <= 900) {
                return true;
            }
        }

        return false;
    }

    /**
     * History rows that can pair with a pembelian line (same day + qty = one movement).
     *
     * @param  \Illuminate\Support\Collection|\Traversable|array  $histories
     */
    private function stockMovementRestockMatchSlots($histories): array
    {
        $slots = [];
        foreach ($histories as $h) {
            $t = strtolower((string) ($h->type ?? ''));
            if (! in_array($t, ['restock', 'purchase'], true)) {
                continue;
            }
            $q = (int) ($h->restock_amount ?? 0);
            if ($q <= 0) {
                continue;
            }
            $d = $h->created_at ? Carbon::parse($h->created_at)->format('Y-m-d') : '';
            if ($d === '') {
                continue;
            }
            $slots[] = ['date' => $d, 'qty' => $q, 'used' => false];
        }

        return $slots;
    }

    /**
     * Mark one matching restock slot as used (greedy). Returns true if a slot was consumed.
     */
    private function stockMovementConsumeRestockMatch(array &$slots, string $purchaseDateYmd, int $qty): bool
    {
        if ($purchaseDateYmd === '' || $qty <= 0) {
            return false;
        }

        foreach ($slots as $i => $slot) {
            if (! empty($slot['used'])) {
                continue;
            }
            if (($slot['qty'] ?? 0) !== $qty) {
                continue;
            }
            if (($slot['date'] ?? '') !== $purchaseDateYmd) {
                continue;
            }
            $slots[$i]['used'] = true;

            return true;
        }

        return false;
    }

    /**
     * Human-readable label for produk_history.type
     */
    private function stockMovementHistoryTitle(string $type): string
    {
        $map = [
            'restock' => 'Stock in (restock)',
            'manual_update' => 'Stock adjustment',
            'initial_stock' => 'Initial stock',
            'withdrawal' => 'Supplier withdrawal',
            'purchase' => 'Purchase / goods in',
            'stock_list_reconcile' => 'Stock list reconcile',
        ];
        $t = strtolower($type);

        return $map[$t] ?? ucfirst(str_replace('_', ' ', $type ?: 'Stock change'));
    }

    /**
     * Export Stock List to PDF (uses same filters as the table: shop, search, optional future params).
     */
    public function exportStockListPdf(Request $request)
    {
        try {
            @ini_set('memory_limit', '512M');
            set_time_limit(300);

            $query = $this->stockListFilteredQuery($request);
            // MySQL string literals must use '' not "" — avoid orderByRaw that breaks sql_mode
            $items = $query->orderBy('produk.nama_produk')
                ->orderBy('produk.kode_produk')
                ->orderBy('produk.item_code')
                ->get();

            $setting = Setting::first();

            $shopFilterName = null;
            if ($request->filled('shop_id')) {
                $shop = Shop::find((int) $request->input('shop_id'));
                $shopFilterName = $shop ? $shop->shop_name : null;
            }

            $searchTerm = trim((string) $request->input('q', ''));
            if ($searchTerm === '') {
                $searchTerm = trim((string) $request->input('search.value', ''));
            }
            if ($searchTerm === '' && $request->filled('match_produk_id')) {
                $one = Produk::query()->find((int) $request->input('match_produk_id'));
                if ($one) {
                    $searchTerm = 'Selected: '.$one->nama_produk;
                }
            }

            $pdf = PDF::loadView('produk.stock_list_pdf', [
                'items' => $items,
                'setting' => $setting,
                'shopFilterName' => $shopFilterName,
                'searchTerm' => $searchTerm !== '' ? $searchTerm : null,
                'generatedAt' => now(),
                'rowCount' => $items->count(),
            ]);

            $pdf->setPaper('a4', 'landscape');

            return $pdf->stream('stock_list_'.date('Y-m-d_His').'.pdf');
        } catch (\Throwable $e) {
            \Log::error('exportStockListPdf: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Failed to generate PDF: '.$e->getMessage()], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            // Trim item_code to remove any whitespace
            $itemCode = trim($request->item_code);
            
            // Check if item_code is empty after trimming
            if (empty($itemCode)) {
                return response()->json('Item code is required.', 422);
            }
            
            $request->merge(['item_code' => $itemCode]);
            
            // Base validation (stock fields validated after we know new vs existing in shop)
            $request->validate([
                'nama_produk' => 'required|string|max:255',
                'item_code' => 'required|string|max:255',
                'shop_id' => 'required|integer|exists:shops,id',
                'id_supplier' => 'required',
                'mop' => 'required',
                'harga_beli' => 'required|numeric|min:0',
                'harga_jual' => 'required|numeric|min:0',
                'reorder' => 'required|numeric|min:0',
                'date_in' => 'required|date',
            ], [
                'nama_produk.required' => 'Product name is required.',
                'item_code.required' => 'Item code is required.',
                'shop_id.required' => 'Shop is required.',
                'shop_id.exists' => 'Please select a valid shop.',
                'id_supplier.required' => 'Supplier is required.',
                'mop.required' => 'Mode of payment is required.',
                'harga_beli.required' => 'Purchase price is required.',
                'harga_jual.required' => 'Selling price is required.',
                'reorder.required' => 'Reorder level is required.',
                'date_in.required' => 'Date in is required.',
            ]);

            $shopId = (int) $request->input('shop_id', 0);
            $existing = $this->resolveExistingProdukForAddStock($request, $shopId, $itemCode);
            if ($existing) {
                $request->validate([
                    'stock_to_add' => 'required|integer|min:1',
                ], [
                    'stock_to_add.required' => 'Enter how many units to add to current stock.',
                    'stock_to_add.min' => 'Quantity to add must be at least 1.',
                ]);

                return $this->mergeAddStockIntoExistingProduk($request, $existing);
            }

            $request->validate([
                'stok' => 'required|numeric|min:1',
            ], [
                'stok.required' => 'Stock quantity is required.',
                'stok.min' => 'Stock quantity must be at least 1.',
            ]);

            // Create Produk with item_code as kode_produk
            $produk = new Produk();
            $produk->shop_id = $request->shop_id;
            $produk->id_supplier = $request->id_supplier;
            $produk->id_kategori = $request->id_kategori ?? 1;
            $produk->mop = $this->normalizeProdukMopInput($request->mop);
            $produk->item_code = $request->item_code;
            $produk->kode_produk = $request->item_code; // Use item_code as kode_produk
            $produk->nama_produk = $request->nama_produk;
            $produk->harga_beli = $request->harga_beli;
            $produk->harga_jual = $request->harga_jual;
            $this->assignProdukReorderFromRequest($request, $produk);
            // Set stock to the requested value directly
            $produk->stok = $request->stok;
            $produk->date_in = $this->normalizeProdukDateInInput($request->date_in);
            $produk->save();

            $initialStock = (int) $produk->stok;
            if ($initialStock > 0) {
                $this->logStockIncreaseToHistory(
                    (int) $produk->id_produk,
                    0,
                    $initialStock,
                    'initial_stock',
                    'Initial stock via Add Stock (new product)',
                    $produk->date_in ?? now()
                );
            }

            // Return success response without redirect
            return response()->json([
                'message' => 'Product saved successfully'
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error: ' . json_encode($e->errors()));
            // Get all validation error messages
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                $errorMessages = array_merge($errorMessages, $messages);
            }
            // Return the first error message (custom messages are already applied)
            return response()->json($errorMessages[0] ?? 'Validation failed. Please check your input.', 422);
        } catch (QueryException $e) {
            \Log::error('Database error saving product: ' . $e->getMessage());
            
            // Check for duplicate entry error
            if ($e->getCode() == 23000) {
                $errorMessage = $e->getMessage();
                
                // Check if it's a duplicate item code
                if (strpos($errorMessage, 'item_code') !== false || 
                    (strpos($errorMessage, 'Duplicate entry') !== false && strpos($errorMessage, 'item_code') !== false)) {
                    $itemCode = $request->item_code ?? 'this code';
                    return response()->json('Item code "' . $itemCode . '" already exists in this shop. Open it and update stock/details.', 422);
                }
                
                // Generic duplicate entry message
                return response()->json('This product already exists in the system. Please check the item code.', 422);
            }
            
            // Other database errors
            return response()->json('Unable to save product. Please check your input and try again.', 500);
        } catch (\Exception $e) {
            \Log::error('Error saving product: ' . $e->getMessage());
            return response()->json('Unable to save product. Please try again later.', 500);
        }
    }

    /**
     * Same shop + item code / kode_produk (case-insensitive) as used by Add Stock modal.
     * Prefers highest id_produk to match quickLookup() (avoids updating the wrong row when duplicates exist).
     */
    private function findExistingProdukInShopByCode(int $shopId, string $itemCode): ?Produk
    {
        if ($shopId <= 0 || trim($itemCode) === '') {
            return null;
        }

        $norm = strtoupper(trim($itemCode));

        return Produk::query()
            ->with(['supplier', 'shop'])
            ->where('shop_id', $shopId)
            ->where(function ($q) use ($norm) {
                $q->whereRaw('UPPER(TRIM(COALESCE(item_code, ""))) = ?', [$norm])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(kode_produk, ""))) = ?', [$norm]);
            })
            ->orderByDesc('id_produk')
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Produk>
     */
    private function findProduksMatchingCodeExact(string $norm, ?int $shopId, int $limit)
    {
        if ($norm === '') {
            return collect();
        }

        $query = Produk::with(['supplier', 'shop'])
            ->where(function ($q) use ($norm) {
                $q->whereRaw('UPPER(TRIM(COALESCE(item_code, ""))) = ?', [$norm])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(kode_produk, ""))) = ?', [$norm]);
            });

        if ($shopId !== null && $shopId > 0) {
            $query->where('shop_id', $shopId);
        }

        return $query->orderByDesc('id_produk')->limit($limit)->get();
    }

    /** Normalized code match (e.g. fsd434 vs SD434) within one shop — bounded candidate scan. */
    private function findProdukInShopByNormalizedCodeKey(int $shopId, string $itemCode): ?Produk
    {
        if ($shopId <= 0 || trim($itemCode) === '') {
            return null;
        }

        $codeKey = $this->normalizeProductCodeKey($itemCode);
        if ($codeKey === '') {
            return null;
        }

        $core = strlen($codeKey) >= 4 ? substr($codeKey, -6) : $codeKey;

        $candidates = Produk::with(['supplier', 'shop'])
            ->where('shop_id', $shopId)
            ->where(function ($q) use ($core) {
                $q->where('item_code', 'like', '%'.$core.'%')
                    ->orWhere('kode_produk', 'like', '%'.$core.'%');
            })
            ->orderByDesc('id_produk')
            ->limit(30)
            ->get();

        return $candidates->first(function (Produk $p) use ($codeKey) {
            return $this->normalizeProductCodeKey($p->kode_produk ?: $p->item_code) === $codeKey;
        });
    }

    /**
     * Whether this row’s item_code / kode_produk matches the submitted code (case-insensitive, trimmed).
     */
    private function produkMatchesNormalizedItemCode(Produk $p, string $itemCode): bool
    {
        $norm = strtoupper(trim($itemCode));
        $ic = strtoupper(trim((string) ($p->item_code ?? '')));
        $kp = strtoupper(trim((string) ($p->kode_produk ?? '')));

        return $ic === $norm || $kp === $norm;
    }

    /**
     * Row to merge into: UI sends existing_id_produk from quick_lookup; otherwise same rule as quickLookup (newest id in shop).
     */
    private function resolveExistingProdukForAddStock(Request $request, int $shopId, string $itemCode): ?Produk
    {
        $preferredId = (int) $request->input('existing_id_produk', 0);
        if ($preferredId > 0 && $shopId > 0) {
            $pref = Produk::query()
                ->where('id_produk', $preferredId)
                ->where('shop_id', $shopId)
                ->first();
            if ($pref && $this->produkMatchesNormalizedItemCode($pref, $itemCode)) {
                return $pref;
            }
        }

        return $this->findExistingProdukInShopByCode($shopId, $itemCode);
    }

    /**
     * Normalize date_in from form (HTML date, ISO, or localized) for DB date column.
     */
    private function normalizeProdukDateInInput($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return is_string($value) ? trim($value) : null;
        }
    }

    /**
     * Mode of payment: store uppercase for consistent display and comparisons.
     */
    private function normalizeProdukMopInput($value): string
    {
        return mb_strtoupper(trim((string) ($value ?? '')), 'UTF-8');
    }

    /**
     * Apply reorder from Add/Edit stock form — many DBs have reorder_level only, some have reorder, some both.
     */
    private function assignProdukReorderFromRequest(Request $request, Produk $produk): void
    {
        $raw = $request->input('reorder', $request->input('reorder_level'));
        if ($raw === null || $raw === '') {
            return;
        }
        $v = (int) $raw;
        if (Schema::hasColumn('produk', 'reorder_level')) {
            $produk->reorder_level = $v;
        }
        if (Schema::hasColumn('produk', 'reorder')) {
            $produk->reorder = $v;
        }
    }

    /**
     * Stock History filter: match product name, kode_produk, or item_code (e.g. RO50).
     */
    private function applyUpdatedItemsProductSearch($query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $norm = strtoupper($term);
        $query->where(function ($q) use ($like, $norm) {
            $q->where('produk.nama_produk', 'like', $like)
                ->orWhere('produk.kode_produk', 'like', $like)
                ->orWhere('produk.item_code', 'like', $like)
                ->orWhereRaw('UPPER(TRIM(COALESCE(produk.kode_produk, ""))) = ?', [$norm])
                ->orWhereRaw('UPPER(TRIM(COALESCE(produk.item_code, ""))) = ?', [$norm]);
        });
    }

    /**
     * Products matching Stock History search (for backfill when old rows never logged history).
     */
    private function productsMatchingUpdatedItemsSearch(string $term)
    {
        $builder = Produk::query();
        $this->applyUpdatedItemsProductSearch($builder, $term);

        return $builder;
    }

    /**
     * Record one stock-increase history row when a product has stock but no prior increase was logged
     * (legacy Add Stock / imports). Uses date_in or created_at for the history date.
     */
    private function ensureStockIncreaseHistoryRecordedForProduct(Produk $produk): void
    {
        if (! Schema::hasTable('produk_history')) {
            return;
        }
        $stok = (int) ($produk->stok ?? 0);
        $sold = $this->sumQuantitySoldOnCompletedSales((int) $produk->id_produk);
        $totalReceived = $stok + $sold;
        if ($totalReceived <= 0) {
            return;
        }
        $hasIncrease = ProdukHistory::query()
            ->where('id_produk', $produk->id_produk)
            ->whereRaw('(current_stock - previous_stock) > 0')
            ->exists();
        if ($hasIncrease) {
            return;
        }
        try {
            $historyDate = $produk->date_in ?? $produk->created_at ?? now();
            if (is_string($historyDate)) {
                $historyDate = Carbon::parse($historyDate);
            }
            $hist = new ProdukHistory();
            $hist->timestamps = false;
            $hist->fill([
                'id_produk' => $produk->id_produk,
                'previous_stock' => 0,
                'restock_amount' => $totalReceived,
                'current_stock' => $totalReceived,
                'type' => 'initial_stock',
                'notes' => 'Initial stock recorded for stock history (product existed without a prior log entry).',
                'created_at' => $historyDate,
                'updated_at' => $historyDate,
            ]);
            $hist->save();
        } catch (\Throwable $e) {
            \Log::warning('ensureStockIncreaseHistoryRecordedForProduct: '.$e->getMessage(), [
                'id_produk' => $produk->id_produk,
            ]);
        }
    }

    /**
     * Log a stock increase to produk_history (shared by Add Stock, edit, restock).
     */
    private function logStockIncreaseToHistory(
        int $idProduk,
        int $previousStock,
        int $newStock,
        string $type,
        string $notes,
        $historyDate = null
    ): void {
        if (! Schema::hasTable('produk_history') || $newStock <= $previousStock) {
            return;
        }
        try {
            $historyDate = $historyDate ?? now();
            if (is_string($historyDate)) {
                $historyDate = Carbon::parse($historyDate);
            }
            $hist = new ProdukHistory();
            $hist->timestamps = false;
            $hist->fill([
                'id_produk' => $idProduk,
                'previous_stock' => $previousStock,
                'restock_amount' => $newStock - $previousStock,
                'current_stock' => $newStock,
                'type' => $type,
                'notes' => $notes,
                'created_at' => $historyDate,
                'updated_at' => $historyDate,
            ]);
            $hist->save();
        } catch (\Throwable $e) {
            \Log::warning('logStockIncreaseToHistory: '.$e->getMessage(), ['id_produk' => $idProduk]);
        }
    }

    /**
     * Replace stock-in history with one row totalling $targetTotalIn (after Stock List "In" correction).
     */
    private function reconcileProdukHistoryToTotalReceived(int $idProduk, int $targetTotalIn): void
    {
        if (! Schema::hasTable('produk_history')) {
            return;
        }

        $targetTotalIn = max(0, $targetTotalIn);

        DB::transaction(function () use ($idProduk, $targetTotalIn) {
            ProdukHistory::query()
                ->where('id_produk', $idProduk)
                ->whereRaw('(current_stock - previous_stock) > 0')
                ->delete();

            if ($targetTotalIn > 0) {
                $this->logStockIncreaseToHistory(
                    $idProduk,
                    0,
                    $targetTotalIn,
                    'stock_list_reconcile',
                    'Total received corrected from Stock List (In = Left + Sold).',
                    now()
                );
            }
        });

        $this->rebuildProdukHistoryChain($idProduk);
    }

    /**
     * Fix previous_stock / current_stock on each stock-in row so they chain (0→a→b→c).
     */
    private function rebuildProdukHistoryChain(int $idProduk): void
    {
        if (! Schema::hasTable('produk_history')) {
            return;
        }

        $rows = ProdukHistory::query()
            ->where('id_produk', $idProduk)
            ->whereRaw('(current_stock - previous_stock) > 0')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $running = 0;
        foreach ($rows as $row) {
            $added = max(0, (int) $row->current_stock - (int) $row->previous_stock);
            $newPrevious = $running;
            $newCurrent = $running + $added;

            if ((int) $row->previous_stock !== $newPrevious
                || (int) $row->current_stock !== $newCurrent
                || (int) ($row->restock_amount ?? 0) !== $added) {
                DB::table('produk_history')->where('id', $row->id)->update([
                    'previous_stock' => $newPrevious,
                    'restock_amount' => $added,
                    'current_stock' => $newCurrent,
                ]);
            }

            $running = $newCurrent;
        }

        $this->applyProdukStokFromHistoryAndSales($idProduk);
    }

    /**
     * Set produk.stok from sum(stock-in history) minus completed sales.
     */
    private function applyProdukStokFromHistoryAndSales(int $idProduk): void
    {
        $produk = Produk::find($idProduk);
        if (! $produk) {
            return;
        }

        $totalIn = $this->sumQuantityStockReceivedFromHistory($idProduk);
        $sold = $this->sumSoldQuantityForProduk($idProduk);
        $produk->stok = max(0, $totalIn - $sold);
        $produk->save();
    }

    /**
     * Add Stock submitted as POST /produk/store: update the existing row and add quantity to current stock
     * (does not insert a second product or replace the row).
     */
    private function mergeAddStockIntoExistingProduk(Request $request, Produk $produk)
    {
        $wasIncompleteBeforeEdit = $this->produkRowIsIncomplete($produk);
        $previousStock = (int) $produk->stok;

        $produk->shop_id = (int) $request->shop_id;
        $produk->id_supplier = (int) $request->id_supplier;
        $produk->id_kategori = (int) ($request->id_kategori ?? 1);
        $produk->mop = $this->normalizeProdukMopInput($request->mop);
        $produk->item_code = trim((string) $request->item_code);
        $produk->kode_produk = trim((string) $request->item_code);
        $produk->nama_produk = trim((string) $request->nama_produk);
        $produk->harga_beli = (float) $request->harga_beli;
        $produk->harga_jual = (float) $request->harga_jual;
        // Re-order level is not changed from Add Stock; use Edit Stock or inline on the list.

        $addQty = max(0, (int) $request->input('stock_to_add', 0));
        $produk->stok = $previousStock + $addQty;
        $normalizedDate = $this->normalizeProdukDateInInput($request->date_in);
        if ($normalizedDate !== null) {
            $produk->date_in = $normalizedDate;
        }

        $shopId = $produk->shop_id;
        $supplierId = $produk->id_supplier;
        $mop = $produk->mop;
        $hargaBeli = (float) $produk->harga_beli;
        $hargaJual = (float) $produk->harga_jual;
        $stok = (int) $produk->stok;
        $dateIn = $produk->date_in;

        $isComplete = ! empty($shopId) && ! empty($supplierId) && ! empty($mop)
            && $hargaBeli >= 0 && $hargaJual >= 0 && $stok >= 0 && ! empty($dateIn);

        $newSupplierId = $produk->id_supplier;
        $newHargaBeli = (float) $produk->harga_beli;
        $newStock = (int) $produk->stok;
        $stockChanged = $previousStock !== $newStock;

        // Add Stock always updates this resolved row (quantity + details). Do not block when another
        // row in the same shop shares the same code; use the dedicated merge tool to consolidate duplicates.

        if ($isComplete && Schema::hasColumn('produk', 'is_incomplete')) {
            $produk->is_incomplete = false;
        }

        try {
            $produk->save();
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('mergeAddStockIntoExistingProduk save failed: '.$e->getMessage(), ['id' => $produk->id_produk]);
            $msg = 'Unable to save. Please try again.';
            if ((string) $e->getCode() === '23000' || strpos($e->getMessage(), 'Duplicate') !== false) {
                $msg = 'That item code conflicts with another product. Use a different code.';
            } elseif (config('app.debug')) {
                $msg = $e->getMessage();
            }

            return response()->json(['message' => $msg], 422);
        } catch (\Throwable $e) {
            \Log::error('mergeAddStockIntoExistingProduk: '.$e->getMessage(), ['id' => $produk->id_produk]);

            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Unable to save. Please try again.',
            ], 500);
        }

        if ($stockChanged && $newStock > $previousStock) {
            try {
                $historyDate = $dateIn
                    ? Carbon::parse($dateIn)->startOfDay()
                    : now();
                $idProduk = (int) $produk->id_produk;
                $itemsSold = $this->sumQuantitySoldOnCompletedSales($idProduk);
                $chainStok = max(0, $this->sumQuantityStockReceivedFromHistory($idProduk) - $itemsSold);
                $this->logStockIncreaseToHistory(
                    $idProduk,
                    $chainStok,
                    $newStock,
                    'manual_update',
                    'Stock added via Add New Stock (existing item)',
                    $historyDate
                );
                $this->rebuildProdukHistoryChain($idProduk);
            } catch (\Exception $e) {
                \Log::warning('mergeAddStockIntoExistingProduk: could not log produk_history: '.$e->getMessage());
            }
        }

        if ($wasIncompleteBeforeEdit && $isComplete && $newSupplierId && $newHargaBeli > 0) {
            try {
                $this->createInvoiceItemsForIncompleteProductSales($produk->id_produk, $newSupplierId, $newHargaBeli);
            } catch (\Throwable $e) {
                \Log::error('createInvoiceItems after mergeAddStock: '.$e->getMessage(), ['id' => $produk->id_produk]);
            }
        }

        return response()->json([
            'message' => 'Product already existed in this shop — details updated and '.$addQty.' unit(s) added to stock (new total: '.$newStock.').',
            'updated_existing' => true,
            'id_produk' => $produk->id_produk,
        ], 200);
    }

    /**
     * Quick add a new product during a sale with minimal details.
     * This is designed to be used from the sales screen so that
     * cashiers can immediately sell an item that is not yet in the system
     * and complete the rest of the product details later.
     */
    public function quickLookup(Request $request)
    {
        $itemCode = trim((string) $request->query('item_code', ''));
        $selectedShopId = (int) $request->query('shop_id', 0);
        // Receipt confirmation / disambiguation: require match in selected shop (no cross-shop fallback).
        $strictShop = $request->boolean('strict_shop');
        if ($itemCode === '') {
            return response()->json([
                'found' => false,
                'message' => 'Item code is required.',
            ], 422);
        }

        $norm = strtoupper(trim($itemCode));

        // Fast path: resolve in selected shop first (indexed), then fall back to a limited global scan.
        $existingProduct = null;
        if ($selectedShopId > 0) {
            $existingProduct = $this->findExistingProdukInShopByCode($selectedShopId, $itemCode)
                ?? $this->findProdukInShopByNormalizedCodeKey($selectedShopId, $itemCode);
        }

        $matchingProducts = $this->findProduksMatchingCodeExact($norm, null, 40);
        if ($matchingProducts->isEmpty() && $existingProduct) {
            $matchingProducts = collect([$existingProduct]);
        }

        if (! $existingProduct && $matchingProducts->isNotEmpty()) {
            if ($selectedShopId > 0) {
                $existingProduct = $matchingProducts->first(function ($p) use ($selectedShopId) {
                    return (int) ($p->shop_id ?? 0) === $selectedShopId;
                });
            }
            if (! $existingProduct && ! ($strictShop && $selectedShopId > 0)) {
                $existingProduct = $matchingProducts->first();
            }
        }

        $matches = $matchingProducts->map(function ($p) {
            return [
                'id_produk' => (int) ($p->id_produk ?? 0),
                'item_code' => (string) ($p->item_code ?? ''),
                'kode_produk' => (string) ($p->kode_produk ?? ''),
                'nama_produk' => (string) ($p->nama_produk ?? ''),
                'harga_beli' => (float) ($p->harga_beli ?? 0),
                'harga_jual' => (float) ($p->harga_jual ?? 0),
                'stok' => (int) ($p->stok ?? 0),
                'reorder' => (int) ($p->reorder_level ?? $p->reorder ?? 0),
                'shop_id' => (int) ($p->shop_id ?? 0),
                'shop_code' => (string) ($p->shop->shop_code ?? ''),
                'shop_name' => (string) ($p->shop->shop_name ?? ''),
                'id_supplier' => (int) ($p->id_supplier ?? 0),
                'mop' => (string) ($p->mop ?? ($p->supplier->mop ?? '')),
            ];
        })->values();

        // Resolve shop from item code prefix (for new items or as a hint)
        $derivedShopCode = '';
        if (strpos($itemCode, '-') !== false) {
            $derivedShopCode = strtoupper(trim(substr($itemCode, 0, strpos($itemCode, '-'))));
        } else {
            $derivedShopCode = strtoupper(substr($itemCode, 0, 1));
        }
        $resolvedShop = null;
        if ($derivedShopCode !== '') {
            $resolvedShop = Shop::whereRaw('UPPER(TRIM(shop_code)) = ?', [$derivedShopCode])->first();
        }

        if ($existingProduct) {
            return response()->json([
                'found' => true,
                'strict_shop' => $strictShop,
                'multiple' => $matches->count() > 1,
                'matches' => $matches,
                'id_produk' => (int) $existingProduct->id_produk,
                'item_code' => (string) ($existingProduct->item_code ?? $itemCode),
                'nama_produk' => (string) ($existingProduct->nama_produk ?? ''),
                'harga_beli' => (float) ($existingProduct->harga_beli ?? 0),
                'harga_jual' => (float) ($existingProduct->harga_jual ?? 0),
                'stok' => (int) ($existingProduct->stok ?? 0),
                'reorder' => (int) ($existingProduct->reorder_level ?? $existingProduct->reorder ?? 0),
                'shop_id' => (int) ($existingProduct->shop_id ?? 0),
                'id_supplier' => (int) ($existingProduct->id_supplier ?? 0),
                'mop' => (string) ($existingProduct->mop ?? ($existingProduct->supplier->mop ?? '')),
                'shop_code' => (string) ($existingProduct->shop->shop_code ?? $derivedShopCode),
                'shop_name' => (string) ($existingProduct->shop->shop_name ?? ''),
            ]);
        }

        $noMatchInShop = $strictShop && $selectedShopId > 0 && $matchingProducts->count() > 0;

        return response()->json([
            'found' => false,
            'strict_shop' => $strictShop,
            'no_match_in_selected_shop' => $noMatchInShop,
            'multiple' => $matches->count() > 1,
            'matches' => $matches,
            'item_code' => $itemCode,
            'shop_id' => $resolvedShop ? (int) $resolvedShop->id : null,
            'shop_code' => $resolvedShop ? (string) ($resolvedShop->shop_code ?? '') : $derivedShopCode,
            'shop_name' => $resolvedShop ? (string) ($resolvedShop->shop_name ?? '') : '',
            'message' => $noMatchInShop
                ? 'This code exists in another shop, but not in the shop selected on this row. Change the shop dropdown or the code.'
                : ($matchingProducts->count() > 0
                    ? 'Could not resolve a single product for this code and shop.'
                    : 'New item code. You can continue adding details.'),
        ]);
    }

    /**
     * Item code typeahead suggestions for stock edit/add modal.
     * Returns matching existing codes, but UI still allows typing a brand-new code.
     */
    public function itemCodeSuggestions(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '' || strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $shopId = (int) $request->query('shop_id', 0);

        $rows = Produk::query()
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->where(function ($w) use ($q) {
                $w->where('produk.item_code', 'like', "%{$q}%")
                  ->orWhere('produk.kode_produk', 'like', "%{$q}%");
            })
            ->when($shopId > 0, function ($query) use ($shopId) {
                $query->where('produk.shop_id', $shopId);
            })
            ->orderBy('produk.item_code')
            ->limit(20)
            ->get([
                'produk.item_code',
                'produk.kode_produk',
                'produk.nama_produk',
                'shops.shop_code',
                'shops.shop_name',
            ]);

        $results = [];
        foreach ($rows as $r) {
            $code = trim((string) ($r->item_code ?: $r->kode_produk));
            if ($code === '') {
                continue;
            }
            $results[] = [
                'code' => $code,
                'label' => trim(($r->shop_code ?? '-') . ' | ' . ($r->shop_name ?? 'Unknown Shop') . ' | ' . ($r->nama_produk ?? '-')),
            ];
        }

        return response()->json(['results' => $results]);
    }

    public function quickAdd(Request $request)
    {
        try {
            // Trim item_code to remove any whitespace
            $itemCode = trim($request->item_code);
            
            // Check if item_code is empty after trimming
            if (empty($itemCode)) {
                return response()->json([
                    'message' => 'Item code is required.',
                ], 422);
            }
            
            $request->merge(['item_code' => $itemCode]);
            
            // Check if item_code exists in this shop — use existing product in the sale (no duplicate quick-add row)
            $derivedShopCode = '';
            if (strpos($itemCode, '-') !== false) {
                $derivedShopCode = strtoupper(trim(substr($itemCode, 0, strpos($itemCode, '-'))));
            } else {
                $derivedShopCode = strtoupper(substr(trim($itemCode), 0, 1));
            }

            $resolvedShop = null;
            if (! empty($derivedShopCode)) {
                $resolvedShop = Shop::whereRaw('UPPER(TRIM(shop_code)) = ?', [$derivedShopCode])->first();
            }
            if (! $resolvedShop && $request->filled('shop_id')) {
                $resolvedShop = Shop::find((int) $request->shop_id);
            }

            $existingProduct = null;
            if ($resolvedShop) {
                $existingProduct = $this->findExistingProdukInShopByCode((int) $resolvedShop->id, $itemCode);
                if (! $existingProduct) {
                    $norm = $this->normalizeProductCodeKey($itemCode);
                    if ($norm !== '') {
                        $existingProduct = Produk::query()
                            ->where('shop_id', (int) $resolvedShop->id)
                            ->get()
                            ->first(function (Produk $p) use ($norm) {
                                return $this->normalizeProductCodeKey($p->kode_produk ?: $p->item_code) === $norm;
                            });
                    }
                }
            }

            if ($existingProduct) {
                return response()->json([
                    'message'      => 'Existing product added to sale.',
                    'id_produk'    => $existingProduct->id_produk,
                    'kode_produk'  => $existingProduct->kode_produk ?? $existingProduct->item_code,
                    'nama_produk'  => $existingProduct->nama_produk,
                    'harga_jual'   => $existingProduct->harga_jual,
                    'stok'         => $existingProduct->stok,
                ], 200);
            }

            // Resolve shop from item code prefix first, then from posted shop_id.
            // Item code format expected: SHOPCODE-ITEMCODE (legacy one-letter prefix also supported).
            if (! $resolvedShop) {
                return response()->json([
                    'message' => 'Invalid or missing shop code in item code. Start with a valid shop code (e.g. A-ITEM001).',
                ], 422);
            }

            // Minimal validation for quick add
            $request->validate([
                'item_code'   => 'required|string|max:255',
                'harga_jual'  => 'required|numeric|min:0',
            ], [
                'item_code.required'   => 'Item code / barcode is required.',
                'harga_jual.required'  => 'Selling price is required.',
            ]);

            // Quick-add without explicit product name:
            // use entered name when provided, otherwise fallback to item code.
            $quickName = trim((string) ($request->nama_produk ?? ''));
            if ($quickName === '') {
                $quickName = $itemCode;
            }

            // Use a placeholder supplier instead of defaulting to unrelated supplier.
            // This makes it clear admin must complete supplier details later.
            $placeholderSupplier = Supplier::whereRaw('TRIM(nama) = ?', ['-'])->first();
            if (! $placeholderSupplier) {
                $placeholderSupplier = Supplier::create([
                    'nama' => '-',
                    'alamat' => '-',
                    'telepon' => '-',
                    'mop' => '-',
                ]);
            }

            $produk = new Produk();
            $produk->shop_id     = $resolvedShop->id;
            $produk->id_supplier = $placeholderSupplier->id_supplier;
            $produk->id_kategori = 1; // Default category
            $produk->mop         = '-';

            $produk->item_code   = $request->item_code;
            $produk->kode_produk = $request->item_code;
            $produk->nama_produk = $quickName;

            // Purchase price is not entered at POS; admin sets it in Products.
            $produk->harga_beli = 0;
            $produk->harga_jual  = $request->harga_jual;
            $produk->reorder     = 0;

            // Give minimal stock so it can be sold immediately; user can adjust later.
            $produk->stok        = $request->stok ?? 1;
            $produk->date_in     = $request->date_in ?? Carbon::now()->toDateString();
            
            // Mark as incomplete so user can complete details later
            $produk->is_incomplete = true;
            if (Schema::hasColumn('produk', 'quick_added')) {
                $produk->quick_added = 1;
            }

            $produk->save();

            return response()->json([
                'message'      => 'Product added successfully',
                'id_produk'    => $produk->id_produk,
                'kode_produk'  => $produk->kode_produk,
                'nama_produk'  => $produk->nama_produk,
                'harga_jual'   => $produk->harga_jual,
                'stok'         => $produk->stok,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessages = [];
            foreach ($e->errors() as $messages) {
                $errorMessages = array_merge($errorMessages, $messages);
            }
            return response()->json([
                'message' => $errorMessages[0] ?? 'Validation failed. Please check your input.',
            ], 422);
        } catch (QueryException $e) {
            \Log::error('Database error quick-adding product: ' . $e->getMessage());
            return response()->json([
                'message' => 'Unable to quick add product. Please check your input and try again.',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Error quick-adding product: ' . $e->getMessage());
            return response()->json([
                'message' => 'Unable to quick add product. Please try again later.',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $produk = Produk::with('supplier')->find($id);
        if (! $produk) {
            return response()->json([
                'message' => 'This quick-add item was linked to inventory and is no longer available as a separate product.',
            ], 404);
        }

        $data = $produk->toArray();
        $data['supplier_mop'] = $produk->supplier->mop ?? null;
        $data['quantity_sold'] = $this->sumSoldQuantityForProduk((int) $produk->id_produk);

        return response()->json($data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $produk = Produk::find($id);
        if (! $produk) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Quick-add item linked to inventory.',
                    'auto_merged' => true,
                    'already_removed' => true,
                ]);
            }

            abort(404);
        }
        $editLogService = app(ProdukEditLogService::class);
        $editSnapshot = $editLogService->snapshot($produk);
        // Snapshot before any mutations (hidden fields / casts can make is_incomplete unreliable later).
        $wasIncompleteBeforeEdit = $this->produkRowIsIncomplete($produk);

        // Quick-add edit: save product details only — no supplier-reassign or price-retro modals.
        if ($wasIncompleteBeforeEdit) {
            if (! $request->filled('supplier_sales_decision')) {
                $request->merge(['supplier_sales_decision' => 'skip']);
            }
            if (! $request->filled('price_retro_decision')) {
                $request->merge(['price_retro_decision' => 'skip']);
            }
        }

        // If product is incomplete, require purchase price (full modal edit only — not inline saves)
        if ($produk->is_incomplete && ! $request->boolean('inline_stock_only') && ! $request->boolean('inline_fields_only')) {
            $request->validate([
                'harga_beli' => 'required|numeric|min:0',
            ], [
                'harga_beli.required' => 'Purchase price is required for incomplete products.',
                'harga_beli.numeric' => 'Purchase price must be a number.',
                'harga_beli.min' => 'Purchase price must be greater than or equal to 0.',
            ]);
        }

        $oldSupplierId = (int) ($produk->id_supplier ?? 0);
        $proposedSupplierId = $oldSupplierId;
        if ($request->filled('id_supplier')) {
            $proposedSupplierId = (int) $request->input('id_supplier');
        }
        $supplierChanged = $proposedSupplierId !== $oldSupplierId && $proposedSupplierId > 0;

        $oldHargaBeli = (float) $produk->harga_beli;
        $oldHargaJual = (float) $produk->harga_jual;
        $proposedHargaBeli = $oldHargaBeli;
        $proposedHargaJual = $oldHargaJual;
        if ($request->filled('harga_beli') || $request->input('harga_beli') === '0') {
            $proposedHargaBeli = (float) $request->input('harga_beli');
        }
        if ($request->filled('harga_jual') || $request->input('harga_jual') === '0') {
            $proposedHargaJual = (float) $request->input('harga_jual');
        }
        $beliPriceChanged = abs($proposedHargaBeli - $oldHargaBeli) > 0.0001;
        $jualPriceChanged = abs($proposedHargaJual - $oldHargaJual) > 0.0001;

        $supplierReassignCounts = null;
        if ($supplierChanged && $proposedSupplierId > 0) {
            $preview = app(ProductSupplierSalesReassignService::class)->preview(
                (int) $produk->id_produk,
                $oldSupplierId,
                $proposedSupplierId
            );
            if ($preview['sale_lines'] > 0 && ! $request->filled('supplier_sales_decision')) {
                return response()->json([
                    'requires_supplier_sales_choice' => true,
                    'message' => 'This product has completed sales linked to the previous supplier. Do you want to move unpaid consignment and cash-generated lines to the new supplier?',
                    'old_supplier_id' => $oldSupplierId,
                    'new_supplier_id' => $proposedSupplierId,
                    'preview' => $preview,
                ]);
            }
        }

        $retroApplyPayload = null;
        $retroCounts = null;
        if ($beliPriceChanged || $jualPriceChanged) {
            if (! $request->filled('price_retro_decision')) {
                // Always return JSON here: inline edits and some proxies omit X-Requested-With / Accept hints.
                return response()->json([
                    'requires_price_retro_choice' => true,
                    'message' => 'Do you want this price change to update past sale lines, consignment, and/or cash purchases? Choose a date range and options below, or save for new activity only.',
                    'old_harga_beli' => $oldHargaBeli,
                    'old_harga_jual' => $oldHargaJual,
                    'new_harga_beli' => $proposedHargaBeli,
                    'new_harga_jual' => $proposedHargaJual,
                    'beli_changed' => $beliPriceChanged,
                    'jual_changed' => $jualPriceChanged,
                ]);
            } elseif ($request->input('price_retro_decision') === 'apply') {
                $request->validate([
                    'retro_date_from' => 'required|date',
                    'retro_date_to' => 'required|date|after_or_equal:retro_date_from',
                ], [
                    'retro_date_from.required' => 'Start date is required when updating past records.',
                    'retro_date_to.required' => 'End date is required when updating past records.',
                ]);

                $retroSales = $request->boolean('retro_update_sales');
                $retroCons = $request->boolean('retro_update_consignment');
                $retroCash = $request->boolean('retro_update_cash_pembelian');

                $canApplySales = $jualPriceChanged && $retroSales;
                $canApplyCons = $beliPriceChanged && $retroCons;
                $canApplyCash = $beliPriceChanged && $retroCash;

                if (! $canApplySales && ! $canApplyCons && ! $canApplyCash) {
                    return response()->json([
                        'message' => 'Select at least one target that matches this change: past sale lines (selling price), consignment unpaid lines (buying price), or unpaid cash purchases (buying price).',
                    ], 422);
                }

                $retroApplyPayload = [
                    'from' => Carbon::parse($request->input('retro_date_from'))->toDateString(),
                    'to' => Carbon::parse($request->input('retro_date_to'))->toDateString(),
                    'sales' => $retroSales,
                    'consignment' => $retroCons,
                    'cash' => $retroCash,
                ];
            }
        }

        $previousStock = (int) $produk->stok;
        $intendedRemaining = null;

        // Item-In inline: total received = In; Left = In - completed sales; sync stock-in history to match.
        if ($request->boolean('inline_item_in') && $request->has('item_in')) {
            $itemsSold = $this->sumSoldQuantityForProduk((int) $produk->id_produk);
            $newItemIn = (int) $request->input('item_in');
            $produk->stok = $newItemIn - $itemsSold;
            $intendedRemaining = (int) $produk->stok;
            $this->reconcileProdukHistoryToTotalReceived((int) $produk->id_produk, max(0, $newItemIn));
        }
        
        // Set only existing columns on the model (no reorder); use request or keep existing for empty
        if ($request->filled('shop_id')) {
            $produk->shop_id = (int) $request->shop_id;
        }
        if ($request->filled('id_supplier')) {
            $produk->id_supplier = (int) $request->id_supplier;
        }
        if ($request->boolean('inline_fields_only') && $request->filled('id_supplier')) {
            $sup = Supplier::find((int) $request->id_supplier);
            if ($sup && ! empty($sup->mop)) {
                $produk->mop = $this->normalizeProdukMopInput($sup->mop);
            }
        }
        if ($request->filled('id_kategori')) {
            $produk->id_kategori = (int) $request->id_kategori;
        }
        if ($request->filled('mop')) {
            $produk->mop = $this->normalizeProdukMopInput($request->mop);
        }
        if ($request->filled('item_code')) {
            $produk->item_code = trim((string) $request->item_code);
            $produk->kode_produk = trim((string) $request->item_code);
        }
        if ($request->filled('nama_produk')) {
            $produk->nama_produk = trim((string) $request->nama_produk);
        }
        if ($request->filled('harga_beli') || $request->input('harga_beli') === '0') {
            $produk->harga_beli = (float) $request->input('harga_beli', $produk->harga_beli);
        }
        if ($request->filled('harga_jual') || $request->input('harga_jual') === '0') {
            $produk->harga_jual = (float) $request->input('harga_jual', $produk->harga_jual);
        }
        if ($request->filled('diskon') || $request->input('diskon') === '0') {
            $produk->diskon = (int) $request->input('diskon', $produk->diskon);
        }
        if ($request->has('reorder_level') && $request->input('reorder_level') !== '' && $request->input('reorder_level') !== null) {
            $produk->reorder_level = (int) $request->input('reorder_level');
        }
        // Stock: optional on edit; keep existing if empty (skip if set via inline_item_in)
        if (! $request->boolean('inline_item_in')) {
            $stokVal = $request->input('stok');
            if ($stokVal !== '' && $stokVal !== null) {
                // Incomplete quick-add: field is "initial / units received"; DB stok is remaining (sales already deducted at POS).
                if ($wasIncompleteBeforeEdit
                    && ! $request->boolean('inline_stock_only')
                    && ! $request->boolean('inline_fields_only')) {
                    $soldCompleted = $this->sumSoldQuantityForProduk((int) $produk->id_produk);
                    $produk->stok = max(0, (int) $stokVal - $soldCompleted);
                } else {
                    $produk->stok = (int) $stokVal;
                }
                $intendedRemaining = (int) $produk->stok;
            }
        }
        if ($request->filled('date_in')) {
            $produk->date_in = $request->date_in;
        }
        
        $shopId = $produk->shop_id;
        $supplierId = $produk->id_supplier;
        $mop = $produk->mop;
        $hargaBeli = (float) $produk->harga_beli;
        $hargaJual = (float) $produk->harga_jual;
        $stok = (int) $produk->stok;
        $dateIn = $produk->date_in;
        
        $isComplete = !empty($shopId) && !empty($supplierId) && !empty($mop)
            && $hargaBeli >= 0 && $hargaJual >= 0 && $stok >= 0 && !empty($dateIn);

        $newSupplierId = $produk->id_supplier;
        $newHargaBeli = (float) $produk->harga_beli;
        
        $newStock = (int) $produk->stok;
        $stockChanged = $previousStock !== $newStock;

        // For "No Supplier" inline assignment (id_supplier only), allow changing supplier even when
        // duplicate item codes exist in the same shop. Duplicate handling is still enforced for
        // normal/full edits and item-code changes.
        $supplierOnlyInlineAssign = $request->boolean('inline_fields_only')
            && $request->filled('id_supplier')
            && ! $request->filled('item_code')
            && ! $request->filled('nama_produk')
            && ! $request->filled('shop_id')
            && ! $request->filled('id_kategori')
            && ! $request->filled('harga_beli')
            && ! $request->filled('harga_jual')
            && ! $request->filled('stok')
            && ! $request->filled('mop');

        // Duplicate check must run BEFORE clearing is_incomplete when the row becomes "complete",
        // otherwise merge pairing sees two complete rows and omits merge (user only gets a dead-end alert).
        $code = trim((string) ($produk->item_code ?? ''));
        if ($code !== '' && ! $supplierOnlyInlineAssign) {
            $norm = strtoupper($code);
            $selfId = (int) $produk->id_produk;
            $duplicates = Produk::where('id_produk', '<>', $selfId)
                ->where('shop_id', $produk->shop_id)
                ->where(function ($q) use ($norm) {
                    $q->whereRaw('UPPER(TRIM(COALESCE(item_code, ""))) = ?', [$norm])
                        ->orWhereRaw('UPPER(TRIM(COALESCE(kode_produk, ""))) = ?', [$norm]);
                })
                ->orderByRaw('CASE WHEN COALESCE(id_supplier, 0) > 0 THEN 0 ELSE 1 END')
                ->orderBy('id_produk', 'asc')
                ->get();
            if ($duplicates->isEmpty()) {
                $codeKey = $this->normalizeProductCodeKey($code);
                if ($codeKey !== '') {
                    $duplicates = Produk::query()
                        ->where('id_produk', '<>', $selfId)
                        ->where('shop_id', $produk->shop_id)
                        ->get()
                        ->filter(function (Produk $p) use ($codeKey) {
                            return $this->normalizeProductCodeKey($p->kode_produk ?: $p->item_code) === $codeKey;
                        })
                        ->sortBy(function (Produk $p) {
                            return ((int) ($p->id_supplier ?? 0) > 0 ? 0 : 1).str_pad((string) $p->id_produk, 12, '0', STR_PAD_LEFT);
                        })
                        ->values();
                }
            }
            $duplicate = $duplicates->first();
            if ($duplicate) {
                $pair = null;
                foreach ($duplicates as $dupCandidate) {
                    $candidatePair = $this->resolveMergeDuplicatePair($produk, $dupCandidate);
                    if ($candidatePair) {
                        $pair = $candidatePair;
                        break;
                    }
                }
                if ($pair
                    && $wasIncompleteBeforeEdit
                    && (int) $pair['remove']->id_produk === (int) $produk->id_produk) {
                    return $this->finalizeIncompleteViaInventoryMatch(
                        $produk,
                        $pair['keep'],
                        $request,
                        $isComplete,
                        $editLogService,
                        $oldSupplierId,
                        $supplierChanged,
                        $proposedSupplierId,
                        $retroApplyPayload,
                        $beliPriceChanged,
                        $jualPriceChanged
                    );
                }

                $duplicateSupplierName = null;
                if (!empty($duplicate->id_supplier)) {
                    $dupSupplier = Supplier::find((int) $duplicate->id_supplier);
                    $duplicateSupplierName = $dupSupplier ? trim((string) $dupSupplier->nama) : null;
                }
                if ($duplicateSupplierName === null || $duplicateSupplierName === '') {
                    $duplicateSupplierName = 'No supplier';
                }

                $payload = [
                    'message' => 'This item code is already used by another product in this shop under supplier: '.$duplicateSupplierName.'. Use a different code, or merge the incomplete duplicate so sales, stock, and supplier records stay on one product.',
                    'duplicate' => [
                        'product_id' => (int) $duplicate->id_produk,
                        'product_name' => (string) ($duplicate->nama_produk ?? ''),
                        'supplier_name' => $duplicateSupplierName,
                        'shop_id' => (int) ($duplicate->shop_id ?? 0),
                        'item_code' => (string) ($duplicate->item_code ?? $duplicate->kode_produk ?? ''),
                    ],
                ];
                if ($pair) {
                    $payload['merge'] = [
                        'keep_id' => (int) $pair['keep']->id_produk,
                        'remove_id' => (int) $pair['remove']->id_produk,
                        'keep_name' => $pair['keep']->nama_produk,
                        'remove_name' => $pair['remove']->nama_produk,
                    ];
                }

                return response()->json($payload, 422);
            }
        }

        if ($isComplete) {
            $produk->is_incomplete = false;
        }
        
        try {
            $produk->save();
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Produk update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $msg = 'Unable to save. Please try again.';
            if ($e->getCode() === '23000' || strpos($e->getMessage(), 'Duplicate') !== false) {
                $msg = 'That item code is already used by another product in this shop. Use a different code or update the existing item.';
            } elseif (config('app.debug')) {
                $msg = $e->getMessage();
            }
            $status = ($e->getCode() === '23000' || strpos($e->getMessage(), 'Duplicate') !== false) ? 422 : 500;

            return response()->json(['message' => $msg], $status);
        } catch (\Throwable $e) {
            \Log::error('Produk update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Unable to save. Please try again.',
            ], 500);
        }

        try {
            $editLogService->logDiff((int) $produk->id_produk, $editSnapshot, $produk->fresh(), $request);
        } catch (\Throwable $e) {
            \Log::warning('produk_edit_log: '.$e->getMessage());
        }

        if ($supplierChanged
            && $request->input('supplier_sales_decision') === 'apply'
            && (int) $produk->id_supplier === $proposedSupplierId) {
            try {
                $supplierReassignCounts = app(ProductSupplierSalesReassignService::class)->apply(
                    $produk->fresh(),
                    $oldSupplierId
                );
            } catch (\Throwable $e) {
                \Log::error('ProductSupplierSalesReassignService: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

                return response()->json([
                    'message' => config('app.debug') ? $e->getMessage() : 'Product saved, but reassigning past sales to the new supplier failed.',
                ], 500);
            }
        }

        if ($retroApplyPayload !== null) {
            try {
                $retroCounts = app(ProductPriceRetroactiveApplyService::class)->apply(
                    (int) $produk->id_produk,
                    (float) $produk->harga_beli,
                    (float) $produk->harga_jual,
                    $retroApplyPayload['from'],
                    $retroApplyPayload['to'],
                    $retroApplyPayload['sales'],
                    $retroApplyPayload['consignment'],
                    $retroApplyPayload['cash'],
                    $beliPriceChanged,
                    $jualPriceChanged
                );
            } catch (\Throwable $e) {
                \Log::error('ProductPriceRetroactiveApplyService: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

                return response()->json([
                    'message' => config('app.debug') ? $e->getMessage() : 'Product saved, but updating past records failed. Check the application log.',
                ], 500);
            }
        }
        
        // Stock-in history: keep one chained timeline (Stock History + product card).
        // Quick-add completion uses reconcileQuickAddStockAfterEdit instead (avoids double reconcile).
        if ($stockChanged && ! $request->boolean('inline_item_in') && ! $wasIncompleteBeforeEdit) {
            $idProduk = (int) $produk->id_produk;
            $itemsSold = $this->sumQuantitySoldOnCompletedSales($idProduk);
            $targetTotalIn = $newStock + $itemsSold;

            try {
                if ($request->boolean('inline_stock_only')) {
                    $this->reconcileProdukHistoryToTotalReceived($idProduk, max(0, $targetTotalIn));
                } elseif ($newStock > $previousStock) {
                    $chainStok = max(0, $this->sumQuantityStockReceivedFromHistory($idProduk) - $itemsSold);
                    $historyDate = $dateIn ? Carbon::parse($dateIn)->startOfDay() : now();
                    $this->logStockIncreaseToHistory(
                        $idProduk,
                        $chainStok,
                        $newStock,
                        'manual_update',
                        'Stock updated via product edit',
                        $historyDate
                    );
                    $this->rebuildProdukHistoryChain($idProduk);
                } else {
                    $this->reconcileProdukHistoryToTotalReceived($idProduk, max(0, $targetTotalIn));
                }
            } catch (\Exception $e) {
                \Log::warning('Could not update produk_history: '.$e->getMessage());
            }

            if ($intendedRemaining !== null) {
                $produk->refresh();
                if ((int) $produk->stok !== $intendedRemaining) {
                    $produk->stok = $intendedRemaining;
                    $produk->save();
                }
            }
        }

        if ($wasIncompleteBeforeEdit
            && ! $request->boolean('inline_stock_only')
            && ! $request->boolean('inline_fields_only')) {
            try {
                $produk->refresh();
                $this->syncQuickAddSaleLinePricesFromProduct($produk);
                $this->reconcileQuickAddStockAfterEdit($produk, $request);
            } catch (\Throwable $e) {
                \Log::error('Quick-add post-save sync failed: '.$e->getMessage(), ['id' => $produk->id_produk]);
            }
        }

        // If product was incomplete and is now complete, create/update invoice items for past sales
        if ($wasIncompleteBeforeEdit && $isComplete && $newSupplierId && $newHargaBeli > 0) {
            try {
                $this->createInvoiceItemsForIncompleteProductSales($produk->id_produk, $newSupplierId, $newHargaBeli);
            } catch (\Throwable $e) {
                \Log::error('createInvoiceItemsForIncompleteProductSales after update: '.$e->getMessage(), ['id' => $produk->id_produk]);
            }
        }

        $payload = ['message' => 'Data saved successfully'];
        if ($retroCounts !== null) {
            $payload['price_retro_applied'] = $retroCounts;
        }
        if ($supplierReassignCounts !== null) {
            $payload['supplier_sales_reassigned'] = $supplierReassignCounts;
        }

        return response()->json($payload, 200);
    }

    /**
     * Quick-add incomplete flag from DB (0/1); avoids loose truthy checks on int/string/null.
     */
    private function produkRowIsIncomplete(Produk $p): bool
    {
        return (int) ($p->is_incomplete ?? 0) === 1;
    }

    /**
     * Total units sold on completed sales for this product (POS lines).
     */
    private function sumQuantitySoldOnCompletedSales(int $idProduk): int
    {
        $q = DB::table('penjualan_detail')->where('penjualan_detail.id_produk', $idProduk);
        if (Schema::hasColumn('penjualan', 'status')) {
            $q->join('penjualan', 'penjualan.id_penjualan', '=', 'penjualan_detail.id_penjualan')
                ->where('penjualan.status', 'completed');
        }

        return (int) $q->sum('penjualan_detail.jumlah');
    }

    /**
     * Total units received from produk_history increases (stock-in / restock rows).
     */
    private function sumQuantityStockReceivedFromHistory(int $idProduk): int
    {
        if (! Schema::hasTable('produk_history')) {
            return 0;
        }

        return (int) ProdukHistory::query()
            ->where('id_produk', $idProduk)
            ->sum(DB::raw('GREATEST(0, current_stock - previous_stock)'));
    }

    /**
     * Expected remaining stock = total received (history) minus completed sales.
     * Returns null when there is no stock-in history to anchor the calculation.
     */
    private function expectedStokFromLedger(int $idProduk): ?int
    {
        $totalIn = $this->sumQuantityStockReceivedFromHistory($idProduk);
        if ($totalIn <= 0) {
            return null;
        }

        return max(0, $totalIn - $this->sumSoldQuantityForProduk($idProduk));
    }

    /**
     * Align produk.stok with stock received (history) minus completed sales.
     *
     * @return array{changed: bool, previous: int, new: int, total_in: int, sold: int}
     */
    public function reconcileProdukStokFromLedger(Produk $produk, bool $dryRun = false): array
    {
        $expected = $this->expectedStokFromLedger((int) $produk->id_produk);
        $previous = (int) ($produk->stok ?? 0);
        if ($expected === null) {
            return [
                'changed' => false,
                'previous' => $previous,
                'new' => $previous,
                'total_in' => 0,
                'sold' => $this->sumQuantitySoldOnCompletedSales((int) $produk->id_produk),
            ];
        }

        if (! $dryRun && $expected !== $previous) {
            $produk->stok = $expected;
            $produk->save();
        }

        return [
            'changed' => $expected !== $previous,
            'previous' => $previous,
            'new' => $expected,
            'total_in' => $this->sumQuantityStockReceivedFromHistory((int) $produk->id_produk),
            'sold' => $this->sumQuantitySoldOnCompletedSales((int) $produk->id_produk),
        ];
    }

    /**
     * Decide which produk row to keep when two rows share the same code.
     * Prefer the complete product; if both have same completeness state, keep lower id_produk.
     */
    private function resolveMergeDuplicatePair(Produk $a, Produk $b): ?array
    {
        if ((int) $a->id_produk === (int) $b->id_produk) {
            return null;
        }

        if ($this->produkRowIsIncomplete($a) && ! $this->produkRowIsIncomplete($b)) {
            return ['keep' => $b, 'remove' => $a];
        }
        if (! $this->produkRowIsIncomplete($a) && $this->produkRowIsIncomplete($b)) {
            return ['keep' => $a, 'remove' => $b];
        }
        if ($this->produkRowIsIncomplete($a) && $this->produkRowIsIncomplete($b)) {
            return (int) $a->id_produk < (int) $b->id_produk
                ? ['keep' => $a, 'remove' => $b]
                : ['keep' => $b, 'remove' => $a];
        }

        // Both complete: allow merge as well, keep deterministic lower id.
        return (int) $a->id_produk < (int) $b->id_produk
            ? ['keep' => $a, 'remove' => $b]
            : ['keep' => $b, 'remove' => $a];
    }

    /**
     * Same-code merge: combine stock, move FKs, delete the incomplete row.
     */
    private function executeMergeIncompletePairInternal(Produk $keep, Produk $remove): void
    {
        $keepId = (int) $keep->id_produk;
        $removeId = (int) $remove->id_produk;

        $first = min($keepId, $removeId);
        $second = max($keepId, $removeId);
        Produk::where('id_produk', $first)->lockForUpdate()->first();
        Produk::where('id_produk', $second)->lockForUpdate()->first();

        $keepFresh = Produk::find($keepId);
        $removeFresh = Produk::find($removeId);
        if (! $keepFresh || ! $removeFresh) {
            throw new \RuntimeException('Product not found.');
        }

        $keepFresh->stok = (int) $keepFresh->stok + (int) $removeFresh->stok;
        $keepFresh->save();

        $this->reassignProdukForeignKeys($removeId, $keepId);
        $removeFresh->delete();
    }

    /**
     * Different-code link: move sales to real stock row and adjust stock for units already sold on the quick-add line.
     */
    private function executeLinkIncompletePairInternal(Produk $keep, Produk $remove): void
    {
        $keepId = (int) $keep->id_produk;
        $removeId = (int) $remove->id_produk;

        $first = min($keepId, $removeId);
        $second = max($keepId, $removeId);
        Produk::where('id_produk', $first)->lockForUpdate()->first();
        Produk::where('id_produk', $second)->lockForUpdate()->first();

        $keepFresh = Produk::find($keepId);
        $removeFresh = Produk::find($removeId);
        if (! $keepFresh || ! $removeFresh) {
            throw new \RuntimeException('Product not found.');
        }

        $soldQty = $this->sumSoldQuantityForProduk($removeId);
        $removeStok = (int) $removeFresh->stok;
        $keepFresh->stok = (int) $keepFresh->stok + $removeStok - $soldQty;
        $keepFresh->save();

        $this->reassignProdukForeignKeys($removeId, $keepId);
        $removeFresh->delete();
    }

    private function produkCodesMatchForMerge(Produk $a, Produk $b): bool
    {
        $codeA = trim((string) ($a->item_code ?? ''));
        $codeB = trim((string) ($b->item_code ?? ''));
        if ($codeA !== '' && $codeB !== '' && strcasecmp($codeA, $codeB) === 0) {
            return true;
        }

        $normA = $this->normalizeProductCodeKey($a->kode_produk ?: $a->item_code);
        $normB = $this->normalizeProductCodeKey($b->kode_produk ?: $b->item_code);

        return $normA !== '' && $normA === $normB;
    }

    /**
     * Quick-add save: item code matches existing inventory — merge/link automatically and apply entered details to the kept row.
     */
    private function finalizeIncompleteViaInventoryMatch(
        Produk $incompleteDraft,
        Produk $keep,
        Request $request,
        bool $isComplete,
        ProdukEditLogService $editLogService,
        int $oldSupplierId,
        bool $supplierChanged,
        int $proposedSupplierId,
        ?array $retroApplyPayload,
        bool $beliPriceChanged,
        bool $jualPriceChanged
    ) {
        $keepId = (int) $keep->id_produk;
        $removeId = (int) $incompleteDraft->id_produk;

        try {
            DB::transaction(function () use ($incompleteDraft, $keep, $keepId, $removeId) {
                if ($this->produkCodesMatchForMerge($incompleteDraft, $keep)) {
                    $this->executeMergeIncompletePairInternal($keep, $incompleteDraft);
                } else {
                    $this->executeLinkIncompletePairInternal($keep, $incompleteDraft);
                }
            });
        } catch (\Throwable $e) {
            \Log::error('finalizeIncompleteViaInventoryMatch: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Could not link to existing inventory. Please try again.',
            ], 500);
        }

        $keepFresh = Produk::find($keepId);
        if (! $keepFresh) {
            return response()->json(['message' => 'Existing product not found after merge.'], 500);
        }

        $keepBefore = $keepFresh->replicate();
        if ($incompleteDraft->shop_id) {
            $keepFresh->shop_id = (int) $incompleteDraft->shop_id;
        }
        if ($incompleteDraft->id_supplier) {
            $keepFresh->id_supplier = (int) $incompleteDraft->id_supplier;
        }
        if ($incompleteDraft->id_kategori) {
            $keepFresh->id_kategori = (int) $incompleteDraft->id_kategori;
        }
        if (trim((string) ($incompleteDraft->mop ?? '')) !== '') {
            $keepFresh->mop = $incompleteDraft->mop;
        }
        if (trim((string) ($incompleteDraft->item_code ?? '')) !== '') {
            $keepFresh->item_code = trim((string) $incompleteDraft->item_code);
            $keepFresh->kode_produk = trim((string) $incompleteDraft->item_code);
        }
        if (trim((string) ($incompleteDraft->nama_produk ?? '')) !== '') {
            $keepFresh->nama_produk = trim((string) $incompleteDraft->nama_produk);
        }
        if ((float) $incompleteDraft->harga_beli >= 0) {
            $keepFresh->harga_beli = (float) $incompleteDraft->harga_beli;
        }
        if ((float) $incompleteDraft->harga_jual >= 0) {
            $keepFresh->harga_jual = (float) $incompleteDraft->harga_jual;
        }
        if ($incompleteDraft->diskon !== null) {
            $keepFresh->diskon = (int) $incompleteDraft->diskon;
        }
        if ($incompleteDraft->reorder_level !== null) {
            $keepFresh->reorder_level = (int) $incompleteDraft->reorder_level;
        }
        if ($incompleteDraft->date_in) {
            $keepFresh->date_in = $incompleteDraft->date_in;
        }
        $keepFresh->is_incomplete = $isComplete ? false : (int) ($keepFresh->is_incomplete ?? 0);
        $keepFresh->save();

        try {
            $editLogService->logDiff($keepId, $editLogService->snapshot($keepBefore), $keepFresh->fresh(), $request);
        } catch (\Throwable $e) {
            \Log::warning('produk_edit_log after inventory match: '.$e->getMessage());
        }

        $supplierReassignCounts = null;
        if ($supplierChanged
            && $request->input('supplier_sales_decision') === 'apply'
            && (int) $keepFresh->id_supplier === $proposedSupplierId) {
            try {
                $supplierReassignCounts = app(ProductSupplierSalesReassignService::class)->apply(
                    $keepFresh->fresh(),
                    $oldSupplierId
                );
            } catch (\Throwable $e) {
                \Log::error('ProductSupplierSalesReassignService after inventory match: '.$e->getMessage());
            }
        }

        $retroCounts = null;
        if ($retroApplyPayload !== null) {
            try {
                $retroCounts = app(ProductPriceRetroactiveApplyService::class)->apply(
                    $keepId,
                    (float) $keepFresh->harga_beli,
                    (float) $keepFresh->harga_jual,
                    $retroApplyPayload['from'],
                    $retroApplyPayload['to'],
                    $retroApplyPayload['sales'],
                    $retroApplyPayload['consignment'],
                    $retroApplyPayload['cash'],
                    $beliPriceChanged,
                    $jualPriceChanged
                );
            } catch (\Throwable $e) {
                \Log::error('ProductPriceRetroactiveApplyService after inventory match: '.$e->getMessage());
            }
        }

        $this->syncSupplierEntriesAfterIncompleteMerge($keepId);

        try {
            $keepFresh = Produk::find($keepId);
            if ($keepFresh) {
                $this->syncQuickAddSaleLinePricesFromProduct($keepFresh);
                $this->reconcileQuickAddStockAfterEdit($keepFresh, $request);
            }
        } catch (\Throwable $e) {
            \Log::error('Quick-add inventory-match post-save sync failed: '.$e->getMessage(), ['keep_id' => $keepId]);
        }

        if ($isComplete && (int) $keepFresh->id_supplier > 0 && (float) $keepFresh->harga_beli > 0) {
            try {
                $this->createInvoiceItemsForIncompleteProductSales($keepId, (int) $keepFresh->id_supplier, (float) $keepFresh->harga_beli);
            } catch (\Throwable $e) {
                \Log::error('createInvoiceItemsForIncompleteProductSales after inventory match: '.$e->getMessage());
            }
        }

        $payload = [
            'message' => 'Matched existing inventory product #'.$keepId.' ('.$keepFresh->nama_produk.'). Sales and stock were linked automatically.',
            'merged_into_keep_id' => $keepId,
            'auto_merged' => true,
        ];
        if ($retroCounts !== null) {
            $payload['price_retro_applied'] = $retroCounts;
        }
        if ($supplierReassignCounts !== null) {
            $payload['supplier_sales_reassigned'] = $supplierReassignCounts;
        }

        return response()->json($payload, 200);
    }

    /**
     * Merge an incomplete duplicate into the kept row: all sales lines, consignment invoice items,
     * purchase lines, and history point to the kept product; remaining stock is combined (sold qty
     * was already deducted on each row).
     */
    public function mergeIncompleteDuplicate(Request $request)
    {
        $request->validate([
            'keep_id' => 'required|integer|exists:produk,id_produk',
            'remove_id' => 'required|integer|exists:produk,id_produk',
        ]);

        $keep = Produk::findOrFail((int) $request->keep_id);
        $remove = Produk::findOrFail((int) $request->remove_id);

        $pair = $this->resolveMergeDuplicatePair($keep, $remove);
        if (! $pair
            || (int) $pair['keep']->id_produk !== (int) $keep->id_produk
            || (int) $pair['remove']->id_produk !== (int) $remove->id_produk) {
            return response()->json([
                'message' => 'Invalid merge. Refresh the page and try again, or choose a different item code.',
            ], 422);
        }

        $codeKeep = trim((string) ($keep->item_code ?? ''));
        $codeRemove = trim((string) ($remove->item_code ?? ''));
        if ($codeKeep === '' || $codeKeep !== $codeRemove) {
            return response()->json([
                'message' => 'Same-code merge requires matching item codes. Saving the quick-add row with the correct inventory code will link automatically.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($keep, $remove) {
                $this->executeMergeIncompletePairInternal($keep, $remove);
            });
        } catch (\Throwable $e) {
            \Log::error('mergeIncompleteDuplicate: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Merge failed. Please try again.',
            ], 500);
        }

        $this->syncSupplierEntriesAfterIncompleteMerge((int) $request->keep_id);

        return response()->json([
            'message' => 'Merged successfully. All sales and stock now use product #'.(int) $request->keep_id.'. Complete supplier details on that product if needed so consignment/cash can generate.',
        ]);
    }

    /**
     * Total units sold on this product line (for stock correction when linking a wrong quick-add row to real stock).
     * Uses completed sales only when penjualan.status exists.
     */
    private function sumSoldQuantityForProduk(int $produkId): int
    {
        $q = DB::table('penjualan_detail')->where('penjualan_detail.id_produk', $produkId);
        if (Schema::hasColumn('penjualan', 'status')) {
            $q->join('penjualan', 'penjualan.id_penjualan', '=', 'penjualan_detail.id_penjualan')
                ->where(function ($statusQ) {
                    $statusQ->where('penjualan.status', 'completed')
                        ->orWhereNull('penjualan.status')
                        ->orWhere('penjualan.status', '');
                })
                ->whereNotIn('penjualan.status', ['active', 'suspended', 'edit_initiated']);
        }

        return (int) $q->sum('penjualan_detail.jumlah');
    }

    /**
     * After a quick-add edit, align past sale lines with the product's current selling price.
     */
    private function syncQuickAddSaleLinePricesFromProduct(Produk $produk): int
    {
        $produkId = (int) $produk->id_produk;
        $hargaJual = (float) $produk->harga_jual;

        $query = PenjualanDetail::query()->where('penjualan_detail.id_produk', $produkId);
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->join('penjualan', 'penjualan.id_penjualan', '=', 'penjualan_detail.id_penjualan')
                ->where(function ($statusQ) {
                    $statusQ->where('penjualan.status', 'completed')
                        ->orWhereNull('penjualan.status')
                        ->orWhere('penjualan.status', '');
                })
                ->whereNotIn('penjualan.status', ['active', 'suspended', 'edit_initiated']);
        }

        $details = $query->select('penjualan_detail.*')->get();
        $penjualanIds = [];
        $updated = 0;
        $priceService = app(ProductPriceRetroactiveApplyService::class);

        foreach ($details as $detail) {
            $jumlah = (int) $detail->jumlah;
            $diskPct = min(100, max(0, (float) ($detail->diskon ?? 0)));
            $subtotal = (int) round($hargaJual * $jumlah * (1 - $diskPct / 100), 0);

            if (abs((float) $detail->harga_jual - $hargaJual) < 0.0001
                && (int) $detail->subtotal === $subtotal) {
                continue;
            }

            $detail->harga_jual = $hargaJual;
            $detail->subtotal = $subtotal;
            $detail->save();
            $updated++;
            $penjualanIds[(int) $detail->id_penjualan] = true;
        }

        foreach (array_keys($penjualanIds) as $penjualanId) {
            $priceService->recalculatePenjualanTotals((int) $penjualanId);
        }

        return $updated;
    }

    /**
     * Quick-add edit: remaining stock = initial units received minus units already sold on finalized sales.
     */
    private function reconcileQuickAddStockAfterEdit(Produk $produk, Request $request): void
    {
        $idProduk = (int) $produk->id_produk;
        $soldQty = $this->sumSoldQuantityForProduk($idProduk);

        $initialReceived = null;
        if ($request->has('stok') && $request->input('stok') !== '' && $request->input('stok') !== null) {
            $initialReceived = max(0, (int) $request->input('stok'));
        } else {
            $initialReceived = max(0, (int) $produk->stok + $soldQty);
        }

        $remaining = max(0, $initialReceived - $soldQty);
        if ((int) $produk->stok !== $remaining) {
            $produk->stok = $remaining;
            $produk->save();
        }

        if (! Schema::hasTable('produk_history')) {
            return;
        }

        $totalInHistory = $this->sumQuantityStockReceivedFromHistory($idProduk);
        if ($totalInHistory !== $initialReceived) {
            $this->reconcileProdukHistoryToTotalReceived($idProduk, $initialReceived);
        }

        $this->applyProdukStokFromHistoryAndSales($idProduk);
    }

    /**
     * Link a quick-add (incomplete) line to the correct existing product when codes differed at POS.
     * Moves sales & supplier refs to the real row and adjusts stock: those sales had reduced the wrong line only,
     * so the target row is reduced by units sold and increased by the wrong line's remaining stock.
     */
    public function linkIncompleteToExisting(Request $request)
    {
        $request->validate([
            'keep_id' => 'required|integer|exists:produk,id_produk',
            'remove_id' => 'required|integer|exists:produk,id_produk',
        ]);

        $keep = Produk::findOrFail((int) $request->keep_id);
        $remove = Produk::findOrFail((int) $request->remove_id);

        if ((int) $keep->id_produk === (int) $remove->id_produk) {
            return response()->json(['message' => 'Choose two different products.'], 422);
        }

        if (! $this->produkRowIsIncomplete($remove)) {
            return response()->json([
                'message' => 'Only the incomplete quick-add row can be linked away.',
            ], 422);
        }

        if ($this->produkRowIsIncomplete($keep)) {
            return response()->json([
                'message' => 'Link to a product that is already completed in stock (not another incomplete line).',
            ], 422);
        }

        try {
            DB::transaction(function () use ($keep, $remove) {
                $this->executeLinkIncompletePairInternal($keep, $remove);
            });
        } catch (\Throwable $e) {
            \Log::error('linkIncompleteToExisting: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Link failed. Please try again.',
            ], 500);
        }

        $this->syncSupplierEntriesAfterIncompleteMerge((int) $request->keep_id);

        return response()->json([
            'message' => 'Linked successfully. Past sales now use product #'.(int) $request->keep_id
                .' and stock was adjusted (remaining from the quick-add line minus units that should have reduced the correct product).',
        ]);
    }

    /**
     * Normalize item code for duplicate detection (letters/digits only, uppercase).
     */
    private function normalizeProductCodeKey(?string $code): string
    {
        $trimmed = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));

        return $trimmed;
    }

    /**
     * Merge two stock rows with different codes (e.g. fsd434 vs SD434) into one product.
     * All sales, purchases, history, and supplier references move to the kept row.
     */
    public function mergeStockDuplicateProducts(Request $request)
    {
        $request->validate([
            'keep_id' => 'required|integer|exists:produk,id_produk',
            'remove_id' => 'required|integer|exists:produk,id_produk',
        ]);

        $keepId = (int) $request->keep_id;
        $removeId = (int) $request->remove_id;

        if ($keepId === $removeId) {
            return response()->json(['message' => 'Choose two different products.'], 422);
        }

        $keep = Produk::findOrFail($keepId);
        $remove = Produk::findOrFail($removeId);

        $codeKeep = trim((string) ($keep->item_code ?? ''));
        $codeRemove = trim((string) ($remove->item_code ?? ''));
        if ($codeKeep !== '' && strcasecmp($codeKeep, $codeRemove) === 0) {
            return $this->mergeIncompleteDuplicate($request);
        }

        $normKeep = $this->normalizeProductCodeKey($keep->kode_produk ?: $keep->item_code);
        $normRemove = $this->normalizeProductCodeKey($remove->kode_produk ?: $remove->item_code);
        if ($normKeep !== '' && $normKeep === $normRemove) {
            return $this->mergeIncompleteDuplicate($request);
        }

        if ($this->produkRowIsIncomplete($remove) && ! $this->produkRowIsIncomplete($keep)) {
            return $this->linkIncompleteToExisting($request);
        }
        if ($this->produkRowIsIncomplete($keep) && ! $this->produkRowIsIncomplete($remove)) {
            $request->merge(['keep_id' => $removeId, 'remove_id' => $keepId]);

            return $this->linkIncompleteToExisting($request);
        }

        if ($keep->shop_id && $remove->shop_id && (int) $keep->shop_id !== (int) $remove->shop_id) {
            return response()->json([
                'message' => 'Both products must belong to the same shop before they can be merged.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($keepId, $removeId) {
                $first = min($keepId, $removeId);
                $second = max($keepId, $removeId);
                Produk::where('id_produk', $first)->lockForUpdate()->first();
                Produk::where('id_produk', $second)->lockForUpdate()->first();

                $keepFresh = Produk::find($keepId);
                $removeFresh = Produk::find($removeId);
                if (! $keepFresh || ! $removeFresh) {
                    throw new \RuntimeException('Product not found.');
                }

                $keepFresh->stok = (int) $keepFresh->stok + (int) $removeFresh->stok;
                $keepFresh->save();

                $this->reassignProdukForeignKeys($removeId, $keepId);

                $removeFresh->delete();
            });
        } catch (\Throwable $e) {
            \Log::error('mergeStockDuplicateProducts: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => config('app.debug') ? $e->getMessage() : 'Merge failed. Please try again.',
            ], 500);
        }

        $this->syncSupplierEntriesAfterIncompleteMerge($keepId);

        return response()->json([
            'message' => 'Merged successfully. Product #'.$keepId.' now has all sales and combined stock. The duplicate row was removed.',
            'keep_id' => $keepId,
        ]);
    }

    /**
     * Select2 search for choosing which product to keep when merging duplicates.
     */
    public function searchProductsForMerge(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $excludeId = (int) $request->query('exclude_id', 0);
        $shopId = (int) $request->query('shop_id', 0);

        if (strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.$term.'%';

        $query = Produk::query()
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->where(function ($q) use ($like) {
                $q->where('produk.nama_produk', 'like', $like)
                    ->orWhere('produk.item_code', 'like', $like)
                    ->orWhere('produk.kode_produk', 'like', $like);
            });

        if ($excludeId > 0) {
            $query->where('produk.id_produk', '!=', $excludeId);
        }
        if ($shopId > 0) {
            $query->where('produk.shop_id', $shopId);
        }

        $rows = $query->select(
            'produk.id_produk',
            'produk.item_code',
            'produk.kode_produk',
            'produk.nama_produk',
            'produk.stok',
            'produk.is_incomplete',
            'shops.shop_name'
        )
            ->orderBy('produk.nama_produk')
            ->limit(25)
            ->get();

        $results = $rows->map(function ($r) {
            $code = trim((string) ($r->kode_produk ?? $r->item_code ?? ''));
            $shop = trim((string) ($r->shop_name ?? ''));
            $incomplete = (int) ($r->is_incomplete ?? 0) === 1;
            $label = ($code !== '' ? $code.' — ' : '').($r->nama_produk ?? '')
                .($shop !== '' ? ' | '.$shop : '')
                .' | Stok: '.(int) ($r->stok ?? 0)
                .($incomplete ? ' | Incomplete' : '');

            return [
                'id' => (int) $r->id_produk,
                'text' => $label,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Select2 search: products that are not incomplete (real stock rows) for linking a wrong quick-add line.
     */
    public function searchProductsForIncompleteLink(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $excludeId = (int) $request->query('exclude_id', 0);

        if (strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.$term.'%';

        $query = Produk::query()
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->where(function ($q) use ($like) {
                $q->where('produk.nama_produk', 'like', $like)
                    ->orWhere('produk.item_code', 'like', $like)
                    ->orWhere('produk.kode_produk', 'like', $like);
            })
            ->whereRaw('COALESCE(produk.is_incomplete, 0) = 0');

        if ($excludeId > 0) {
            $query->where('produk.id_produk', '!=', $excludeId);
        }

        $rows = $query->select('produk.id_produk', 'produk.item_code', 'produk.kode_produk', 'produk.nama_produk', 'produk.stok', 'shops.shop_name')
            ->orderBy('produk.nama_produk')
            ->limit(25)
            ->get();

        $results = $rows->map(function ($r) {
            $code = trim((string) ($r->item_code ?? $r->kode_produk ?? ''));
            $shop = trim((string) ($r->shop_name ?? ''));
            $label = ($code !== '' ? $code.' — ' : '').($r->nama_produk ?? '')
                .($shop !== '' ? ' | '.$shop : '')
                .' | Stok: '.(int) ($r->stok ?? 0);

            return [
                'id' => (int) $r->id_produk,
                'text' => $label,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Point all references from a removed produk id to the kept id.
     */
    private function reassignProdukForeignKeys(int $fromId, int $toId): void
    {
        DB::table('penjualan_detail')->where('id_produk', $fromId)->update(['id_produk' => $toId]);
        DB::table('invoice_items')->where('produk_id', $fromId)->update(['produk_id' => $toId]);
        DB::table('produk_history')->where('id_produk', $fromId)->update(['id_produk' => $toId]);
        DB::table('pembelian_detail')->where('id_produk', $fromId)->update(['id_produk' => $toId]);

        $tables = [
            'supplier_withdrawals' => 'produk_id',
            'purchase_order_batch_items' => 'produk_id',
            'purchase_orders' => 'produk_id',
            'purchase_order_receipts' => 'produk_id',
            'goods_received_items' => 'produk_id',
        ];

        foreach ($tables as $table => $column) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where($column, $fromId)->update([$column => $toId]);
            }
        }
    }
    
    /**
     * Create or update invoice items for sales that occurred while product was incomplete.
     * After admin updates a quick-added product (sets supplier, purchase price, etc.), this ensures
     * the sale reflects on the individual supplier's consignment or cash so the supplier can be paid.
     * - Consignment suppliers: create/update InvoiceItem (shows in Pending Consignments / Consignment overview).
     * - Cash suppliers: create Pembelian + PembelianDetail (shows in purchases for payment).
     */
    private function createInvoiceItemsForIncompleteProductSales($produkId, $supplierId, $hargaBeli)
    {
        // Get supplier to check mode of payment (case-insensitive: Consignment vs Cash)
        $supplier = Supplier::find($supplierId);
        if (!$supplier) {
            \Log::warning("Supplier not found: {$supplierId}");
            return;
        }
        
        $supplierMOP = strtoupper(trim($supplier->mop ?? ''));
        $isCashSupplier = ($supplierMOP === 'CASH');
        
        // Find all sales (PenjualanDetail) for this product that belong to completed sales
        $penjualanDetails = PenjualanDetail::where('id_produk', $produkId)
            ->with('penjualan')
            ->get();
        
        // Get schema to check if status column exists
        $hasStatusColumn = DB::getSchemaBuilder()->hasColumn('penjualan', 'status');
        
        // Group sales by sale date and penjualan for cash suppliers to create one Pembelian per sale
        $salesByPenjualan = [];
        $ledger = app(EnsureSaleSupplierLedgerService::class);

        foreach ($penjualanDetails as $detail) {
            $penjualan = $detail->penjualan;
            
            // Only process completed sales
            if (!$penjualan) {
                continue;
            }

            if (! $ledger->detailEligibleForLedgerPosting($penjualan, $detail)) {
                continue;
            }
            
            // Only finalized sales. Legacy rows often have NULL/empty status (treat as completed).
            // Do not backfill carts: active/suspended/edit_initiated sales can have penjualan_detail rows.
            if ($hasStatusColumn) {
                $saleStatus = $penjualan->status;
                if (in_array($saleStatus, ['active', 'suspended', 'edit_initiated'], true)) {
                    continue;
                }
                if ($saleStatus !== null && $saleStatus !== '' && $saleStatus !== 'completed') {
                    continue;
                }
            }
            
            $saleDate = $penjualan->saledate ? \Carbon\Carbon::parse($penjualan->saledate)->format('Y-m-d') 
                                              : $detail->created_at->format('Y-m-d');
            
            // Calculate the correct amount
            $correctAmount = $detail->jumlah * $hargaBeli;
            
            if ($isCashSupplier) {
                // For cash suppliers, group by penjualan to create one Pembelian per sale
                $penjualanId = $penjualan->id_penjualan;
                if (!isset($salesByPenjualan[$penjualanId])) {
                    $salesByPenjualan[$penjualanId] = [
                        'penjualan' => $penjualan,
                        'saleDate' => $saleDate,
                        'items' => []
                    ];
                }
                $salesByPenjualan[$penjualanId]['items'][] = [
                    'detail' => $detail,
                    'harga_beli' => $hargaBeli,
                    'jumlah' => $detail->jumlah,
                    'subtotal' => $correctAmount
                ];
            } else {
                // For consignment suppliers, create InvoiceItem as before
                // Find existing invoice items for this product that might have been created during the sale
                $existingInvoiceItems = InvoiceItem::where('produk_id', $produkId)
                    ->whereDate('created_at', $saleDate)
                    ->where('quantity', $detail->jumlah)
                    ->get();
                
                $invoiceItemFound = false;
                
                // Check if any existing invoice item needs updating
                foreach ($existingInvoiceItems as $existingItem) {
                    // Update if amount is 0 or supplier is wrong
                    if ($existingItem->amount == 0 || $existingItem->supplier_id != $supplierId) {
                        $oldInvoiceId = $existingItem->invoice_id;
                        $oldAmountPaid = $existingItem->amount_paid ?? 0;
                        
                        // Find or create invoice for the correct supplier (use sale date so it reflects in supplier consignment)
                        $invoice = Invoice::where('id_supplier', $supplierId)
                            ->whereDate('created_at', $saleDate)
                            ->first();
                        
                        if (!$invoice) {
                            $invoice = new Invoice();
                            $invoice->total = 0;
                            $invoice->id_supplier = $supplierId;
                            $saleDateTime = Carbon::parse($saleDate)->startOfDay();
                            $invoice->created_at = $saleDateTime;
                            $invoice->updated_at = $saleDateTime;
                            $invoice->save();
                        }
                        
                        // Update invoice item
                        $existingItem->supplier_id = $supplierId;
                        $existingItem->invoice_id = $invoice->id;
                        $existingItem->amount = $correctAmount;
                        $existingItem->amount_paid = $oldAmountPaid; // Preserve any existing payments
                        $existingItem->balance = $correctAmount - $oldAmountPaid;
                        if (DB::getSchemaBuilder()->hasColumn('invoice_items', 'penjualan_id')) {
                            $existingItem->penjualan_id = $penjualan->id_penjualan;
                        }
                        $existingItem->save();
                        
                        // Update invoice totals
                        $invoice->total = InvoiceItem::where('invoice_id', $invoice->id)->sum('amount');
                        $invoice->save();
                        
                        // Update old invoice total if different
                        if ($oldInvoiceId != $invoice->id) {
                            $oldInvoice = Invoice::find($oldInvoiceId);
                            if ($oldInvoice) {
                                $oldInvoiceTotal = InvoiceItem::where('invoice_id', $oldInvoiceId)->sum('amount');
                                $oldInvoice->total = $oldInvoiceTotal;
                                $oldInvoice->save();
                            }
                        }
                        
                        $invoiceItemFound = true;
                        break;
                    } else {
                        // Invoice item is already correct
                        $invoiceItemFound = true;
                        break;
                    }
                }
                
                // If no invoice item exists, create one (so it reflects on individual supplier consignment for payment)
                if (! $invoiceItemFound && $correctAmount > 0) {
                    $supplierModel = Supplier::find($supplierId);
                    $produkModel = Produk::find($produkId);
                    $ledger = app(EnsureSaleSupplierLedgerService::class);
                    if ($supplierModel && $produkModel && Schema::hasColumn('invoice_items', 'penjualan_id')) {
                        $posted = $ledger->createConsignmentInvoiceItemIfNeeded(
                            $penjualan,
                            $produkModel,
                            $supplierModel,
                            (int) $detail->jumlah,
                            (int) ($detail->diskon ?? 0)
                        );
                        $invoiceItemFound = $posted !== null || $ledger->remainingConsignmentQtyToPost(
                            (int) $penjualan->id_penjualan,
                            (int) $produkId,
                            (int) $supplierId
                        ) <= 0;
                    } elseif ($supplierModel) {
                        // Legacy DB without penjualan_id on invoice_items
                        $invoice = Invoice::where('id_supplier', $supplierId)
                            ->whereDate('created_at', $saleDate)
                            ->first();

                        if (! $invoice) {
                            $invoice = new Invoice();
                            $invoice->total = 0;
                            $invoice->id_supplier = $supplierId;
                            $saleDateTime = Carbon::parse($saleDate)->startOfDay();
                            $invoice->created_at = $saleDateTime;
                            $invoice->updated_at = $saleDateTime;
                            $invoice->save();
                        }

                        $invoiceItem = new InvoiceItem();
                        $invoiceItem->uniqid = uniqid();
                        $invoiceItem->produk_id = $produkId;
                        $invoiceItem->supplier_id = $supplierId;
                        $invoiceItem->invoice_id = $invoice->id;
                        $invoiceItem->status = 'Not paid';
                        $invoiceItem->discount = $detail->diskon ?? 0;
                        $invoiceItem->quantity = $detail->jumlah;
                        $invoiceItem->amount = $correctAmount;
                        $invoiceItem->balance = $correctAmount;
                        $invoiceItem->amount_paid = 0;
                        $saleDateTime = Carbon::parse($saleDate)->startOfDay();
                        $invoiceItem->created_at = $saleDateTime;
                        $invoiceItem->updated_at = $saleDateTime;
                        $invoiceItem->save();

                        $invoice->total = InvoiceItem::where('invoice_id', $invoice->id)->sum('amount');
                        $invoice->save();
                        $invoiceItemFound = true;
                    }
                }
            }
        }
        
        // For cash suppliers, create Pembelian records grouped by sale
        if ($isCashSupplier && !empty($salesByPenjualan)) {
            foreach ($salesByPenjualan as $penjualanId => $saleData) {
                $penjualan = $saleData['penjualan'];
                $saleDate = $saleData['saleDate'];
                $items = $saleData['items'];
                
                // Check if PembelianDetail already exists for this product in this sale.
                // Prefer penjualan_id match when available to avoid false positives from same-date manual purchases.
                $pembelianDetailExists = false;
                foreach ($items as $itemData) {
                    $existingPembelianDetailQuery = DB::table('pembelian_detail')
                        ->join('pembelian', 'pembelian_detail.id_pembelian', '=', 'pembelian.id_pembelian')
                        ->where('pembelian_detail.id_produk', $produkId)
                        ->where('pembelian.id_supplier', $supplierId);
                    if (Schema::hasColumn('pembelian', 'penjualan_id')) {
                        $existingPembelianDetailQuery->where('pembelian.penjualan_id', $penjualan->id_penjualan);
                    } else {
                        $existingPembelianDetailQuery
                            ->where('pembelian_detail.jumlah', $itemData['jumlah'])
                            ->where('pembelian_detail.harga_beli', $hargaBeli)
                            ->whereDate('pembelian.purchasedate2', $saleDate);
                    }
                    $existingPembelianDetail = $existingPembelianDetailQuery->first();
                    
                    if ($existingPembelianDetail) {
                        $pembelianDetailExists = true;
                        break;
                    }
                }
                
                if (!$pembelianDetailExists) {
                    // Check if a Pembelian already exists for this sale and supplier
                    // If so, we'll add details to it; otherwise create a new one
                    $existingPembelianQuery = Pembelian::where('id_supplier', $supplierId);
                    if (Schema::hasColumn('pembelian', 'penjualan_id')) {
                        $existingPembelianQuery->where('penjualan_id', $penjualan->id_penjualan);
                    } else {
                        $existingPembelianQuery->whereDate('purchasedate2', $saleDate);
                    }
                    $existingPembelian = $existingPembelianQuery->first();
                    
                    // Calculate totals for this product's items
                    $totalItem = 0;
                    $totalHarga = 0;
                    $pembelianDetails = [];
                    
                    foreach ($items as $itemData) {
                        $totalItem += $itemData['jumlah'];
                        $totalHarga += $itemData['subtotal'];
                        $pembelianDetails[] = $itemData;
                    }
                    
                    if ($totalHarga > 0) {
                        if ($existingPembelian) {
                            // Add to existing Pembelian
                            $pembelian = $existingPembelian;
                            
                            // Update Pembelian totals
                            $pembelian->total_item += $totalItem;
                            $pembelian->total_harga += $totalHarga;
                            if (Schema::hasColumn('pembelian', 'status') && empty($pembelian->status)) {
                                $pembelian->status = 'completed';
                            }
                            if (Schema::hasColumn('pembelian', 'payment_method') && empty($pembelian->payment_method)) {
                                $pembelian->payment_method = 'Cash';
                            }
                            if (Schema::hasColumn('pembelian', 'type') && empty($pembelian->type)) {
                                $pembelian->type = 'cash_sale';
                            }
                            if (Schema::hasColumn('pembelian', 'penjualan_id') && empty($pembelian->penjualan_id)) {
                                $pembelian->penjualan_id = $penjualan->id_penjualan;
                            }
                            $pembelian->save();
                        } else {
                            // Create new Pembelian record
                            $pembelianData = [
                                'id_supplier' => $supplierId,
                                'total_item' => $totalItem,
                                'total_harga' => $totalHarga,
                                'reorder' => 0,
                                'bayar' => 0, // Outstanding - will be paid later
                                'purchasedate2' => $saleDate,
                            ];
                            if (Schema::hasColumn('pembelian', 'status')) {
                                $pembelianData['status'] = 'completed';
                            }
                            if (Schema::hasColumn('pembelian', 'payment_method')) {
                                $pembelianData['payment_method'] = 'Cash';
                            }
                            if (Schema::hasColumn('pembelian', 'type')) {
                                $pembelianData['type'] = 'cash_sale';
                            }
                            if (Schema::hasColumn('pembelian', 'penjualan_id')) {
                                $pembelianData['penjualan_id'] = $penjualan->id_penjualan;
                            }
                            $pembelian = Pembelian::create($pembelianData);
                        }
                        
                        // Create PembelianDetail records
                        foreach ($pembelianDetails as $itemData) {
                            PembelianDetail::create([
                                'id_pembelian' => $pembelian->id_pembelian,
                                'id_produk' => $produkId,
                                'harga_beli' => $itemData['harga_beli'],
                                'jumlah' => $itemData['jumlah'],
                                'subtotal' => $itemData['subtotal'],
                            ]);
                        }
                        
                        \Log::info('Auto-created/updated Pembelian for cash supplier from incomplete product completion', [
                            'penjualan_id' => $penjualan->id_penjualan,
                            'supplier_id' => $supplierId,
                            'pembelian_id' => $pembelian->id_pembelian,
                            'total_harga' => $pembelian->total_harga,
                            'produk_id' => $produkId,
                            'was_existing' => $existingPembelian ? true : false,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * After merge/link moves penjualan_detail onto the kept product, create consignment invoice items or
     * cash Pembelian rows for those past sales (same as completing an incomplete product in the modal).
     */
    private function syncSupplierEntriesAfterIncompleteMerge(int $keepId): void
    {
        $p = Produk::find($keepId);
        if (! $p || $this->produkRowIsIncomplete($p)) {
            return;
        }
        if (! $p->id_supplier || (float) $p->harga_beli <= 0) {
            return;
        }
        try {
            $this->createInvoiceItemsForIncompleteProductSales(
                $keepId,
                (int) $p->id_supplier,
                (float) $p->harga_beli
            );
        } catch (\Throwable $e) {
            \Log::error('syncSupplierEntriesAfterIncompleteMerge: '.$e->getMessage(), ['keep_id' => $keepId]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $produk = Produk::find($id);
        $produk->delete();

        return response()->json('Data deleted successfully', 200);
    }

    public function restockPage()
    {
        $products = Produk::orderBy('nama_produk')->get();
        return view('produk.restock', compact('products'));
    }

    public function restock(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'id_produk' => 'required|exists:produk,id_produk',
                'additional_stock' => 'required|integer|min:1',
                'date_in' => 'required|date'
            ]);

            // Find the product
            $produk = Produk::find($request->id_produk);
            
            // Store previous stock before update
            $previousStock = $produk->stok;
            
            // Calculate new stock
            $newStock = $produk->stok + $request->additional_stock;
            
            // Update product stock
            $produk->update([
                'stok' => $newStock,
                'date_in' => $request->date_in
            ]);

            // Log stock update to produk_history
            try {
                // Store the submission date from `date_in`.
                $historyDate = $request->date_in ?? now();
                $hist = new ProdukHistory();
                $hist->timestamps = false;
                $hist->fill([
                    'id_produk' => $produk->id_produk,
                    'previous_stock' => $previousStock,
                    'restock_amount' => $request->additional_stock,
                    'current_stock' => $newStock,
                    'type' => 'restock',
                    'notes' => 'Stock restocked via restock function',
                    'created_at' => $historyDate,
                    'updated_at' => $historyDate,
                ]);
                $hist->save();
            } catch (\Exception $e) {
                \Log::warning('Could not log to produk_history: ' . $e->getMessage());
            }

            // Create Pembelian record
            $total = $request->additional_stock * $produk->harga_beli;
            
            // Get supplier to check payment method
            $supplier = $produk->supplier;
            // For cash suppliers, set bayar to 0 (outstanding), for consignment set to total (paid immediately)
            $bayar = ($supplier && strtoupper(trim((string) ($supplier->mop ?? ''))) === 'CASH') ? 0 : $total;
            
            $pembelian = Pembelian::create([
                'id_supplier' => $produk->id_supplier,
                'total_item' => $request->additional_stock,
                'total_harga' => $total,
                'reorder' => 0,
                'bayar' => $bayar,
                'purchasedate2' => $request->date_in,
            ]);
            
            // Create Pembelian detail
            PembelianDetail::create([
                'id_pembelian' => $pembelian->id_pembelian,
                'id_produk' => $produk->id_produk,
                'harga_beli' => $produk->harga_beli,
                'jumlah' => $request->additional_stock,
                'subtotal' => $total,
            ]);

            return response()->json('Stock updated successfully', 200);
        } catch (\Exception $e) {
            \Log::error('Error restocking product: ' . $e->getMessage());
            return response()->json('Unable to update stock: ' . $e->getMessage(), 500);
        }
    }

    public function deleteSelected(Request $request)
    {
        foreach ($request->id_produk as $id) {
            $produk = Produk::find($id);
            $produk->delete();
        }

        return response(null, 204);
    }

    /**
     * Get products with pagination for POS search
     * Returns 20 items per page with server-side pagination
     * Optimized for performance with database indexes
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProducts(Request $request)
    {
        // Items per page - optimized for POS speed
        $perPage = 20;
        
        // Build query with efficient joins
        $query = Produk::select('produk.id_produk', 'produk.nama_produk', 'produk.stok', 'produk.item_code', 'produk.harga_jual')
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id');
        
        // Filter by shop_code - required
        if ($request->has('shop_code') && !empty($request->shop_code)) {
            $query->where('shops.shop_code', $request->shop_code);
        } else {
            // If no shop_code provided, return empty result
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false]
            ]);
        }
        
        // Filter by search query if provided (product code or name)
        // Uses LIKE with indexes for fast search
        if ($request->has('q') && !empty(trim($request->q))) {
            $searchTerm = trim($request->q);
            $query->where(function($q) use ($searchTerm) {
                $q->where('produk.nama_produk', 'like', '%' . $searchTerm . '%')
                  ->orWhere('produk.item_code', 'like', '%' . $searchTerm . '%');
            });
        }
        
        // Order by product name for consistent results
        $query->orderBy('produk.nama_produk');
        
        // Get current page from request (Select2 sends 'page' parameter)
        $page = $request->get('page', 1);
        
        // Use paginate with LIMIT/OFFSET for efficient server-side pagination
        try {
            $products = $query->paginate($perPage, ['*'], 'page', $page);
            
            // Format response for Select2 with pagination metadata
            // This maintains search filters when paginating
            return response()->json([
                'results' => $products->items() ?? [],
                'pagination' => [
                    'more' => $products->hasMorePages() ?? false
                ]
            ]);
        } catch (\Exception $e) {
            // Return empty results on error to prevent frontend issues
            \Log::error('Product search error: ' . $e->getMessage());
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false]
            ]);
        }
    }

    /**
     * Display incomplete products page
     *
     * @return \Illuminate\Http\Response
     */
    public function incompleteIndex()
    {
        $kategori = Kategori::all()->pluck('nama_kategori', 'id_kategori');
        $shop = Shop::all()->pluck('shop_name', 'id');
        $supplier = Supplier::all()->pluck('nama', 'id_supplier');

        return view('produk.incomplete', compact('kategori', 'shop', 'supplier'));
    }

    /**
     * Display updated products page
     *
     * @return \Illuminate\Http\Response
     */
    public function updatedIndex()
    {
        $shops = Shop::orderBy('shop_name')->pluck('shop_name', 'id');
        $suppliers = Supplier::orderBy('nama')->pluck('nama', 'id_supplier');
        $selectedProductName = trim((string) request('product_name', ''));

        return view('produk.updated', compact('shops', 'suppliers', 'selectedProductName'));
    }

    /**
     * Typeahead for Updated Items product-name filter (Select2 AJAX).
     * Avoids loading every distinct name into the page (slow client-side filtering).
     */
    public function updatedProductNameSearch(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = Produk::query()
            ->select('id_produk', 'nama_produk', 'kode_produk', 'item_code')
            ->where(function ($w) use ($like) {
                $w->where('nama_produk', 'like', $like)
                    ->orWhere('kode_produk', 'like', $like)
                    ->orWhere('item_code', 'like', $like);
            })
            ->orderBy('nama_produk')
            ->limit(30)
            ->get();

        $results = $rows->map(function ($p) {
            $code = trim((string) ($p->kode_produk ?? ''));
            if ($code === '') {
                $code = trim((string) ($p->item_code ?? ''));
            }
            $name = trim((string) ($p->nama_produk ?? ''));
            $filterValue = $code !== '' ? $code : $name;
            $text = $code !== '' && $name !== '' && strcasecmp($code, $name) !== 0
                ? $code.' — '.$name
                : ($code !== '' ? $code : $name);

            return [
                'id' => $filterValue,
                'text' => $text !== '' ? $text : ('#'.$p->id_produk),
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Get updated products data for DataTables
     * Shows products where updated_at is different from created_at
     *
     * @return \Illuminate\Http\Response
     */
    public function updatedData(Request $request)
    {
        // Use server-side processing for DataTables
        // This only loads the current page of results instead of all products
        // Show ALL products where stock was increased (quantity_added > 0)
        // Display all history entries, even if same product appears multiple times
        
        // Build base query for summary calculations (without select to optimize)
        $summaryQuery = Produk::leftJoin('kategori', 'kategori.id_kategori', 'produk.id_kategori')
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
            ->join('produk_history', 'produk_history.id_produk', '=', 'produk.id_produk')
            ->whereRaw('(produk_history.current_stock - produk_history.previous_stock) > 0');

        // Build main query for DataTables
        $query = Produk::leftJoin('kategori', 'kategori.id_kategori', 'produk.id_kategori')
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
            ->join('produk_history', 'produk_history.id_produk', '=', 'produk.id_produk')
            ->select(
                'produk.id_produk',
                'produk.nama_produk',
                'produk.kode_produk',
                'produk.item_code',
                'shops.shop_name',
                'produk_history.id as history_id',
                'produk_history.previous_stock',
                'produk_history.restock_amount',
                'produk_history.current_stock',
                DB::raw('(produk_history.current_stock - produk_history.previous_stock) as quantity_added'),
                DB::raw('DATE(produk_history.created_at) as stock_date'),
                'produk_history.created_at as stock_updated_at'
            )
            ->whereRaw('(produk_history.current_stock - produk_history.previous_stock) > 0');

        $productSearchTerm = trim((string) $request->input('product_name', ''));
        if ($productSearchTerm !== '') {
            $this->applyUpdatedItemsProductSearch($query, $productSearchTerm);
            $this->applyUpdatedItemsProductSearch($summaryQuery, $productSearchTerm);
            foreach ($this->productsMatchingUpdatedItemsSearch($productSearchTerm)->limit(25)->get() as $produkRow) {
                $this->ensureStockIncreaseHistoryRecordedForProduct($produkRow);
            }
        }

        // Apply shop filter
        if ($request->filled('shop_id')) {
            $shopId = (int) $request->input('shop_id');
            if ($shopId > 0) {
                $query->where('produk.shop_id', $shopId);
                $summaryQuery->where('produk.shop_id', $shopId);
            }
        }
        if ($request->filled('supplier_id')) {
            $supplierId = (int) $request->input('supplier_id');
            if ($supplierId > 0) {
                $query->where('produk.id_supplier', $supplierId);
                $summaryQuery->where('produk.id_supplier', $supplierId);
            }
        }

        // Apply date filters
        if ($request->filled('start_date')) {
            $query->whereDate('produk_history.created_at', '>=', $request->input('start_date'));
            $summaryQuery->whereDate('produk_history.created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('produk_history.created_at', '<=', $request->input('end_date'));
            $summaryQuery->whereDate('produk_history.created_at', '<=', $request->input('end_date'));
        }

        // Calculate summary metrics BEFORE pagination
        $totalItemsUpdated = $summaryQuery->count();
        $totalQuantityAdded = $summaryQuery->sum(DB::raw('(produk_history.current_stock - produk_history.previous_stock)'));

        // Do not call $query->orderBy() here — Yajra applies ordering from the DataTables request.
        // Default sort is set in updated.blade.php (date column desc). Without that, ordering by
        // column 0 (DT_RowIndex) would generate invalid SQL unless mapped below.

        return datatables()
            ->of($query)
            ->filterColumn('item_code', function($query, $keyword) {
                // Custom filter for item_code - search in both kode_produk and item_code
                $query->where(function($q) use ($keyword) {
                    $q->where('produk.kode_produk', 'like', "%{$keyword}%")
                      ->orWhere('produk.item_code', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('nama_produk', function($query, $keyword) {
                $query->where('produk.nama_produk', 'like', "%{$keyword}%");
            })
            ->filterColumn('shop_name', function($query, $keyword) {
                $query->where('shops.shop_name', 'like', "%{$keyword}%");
            })
            ->orderColumn('DT_RowIndex', 'produk_history.id $1')
            ->addIndexColumn()
            ->addColumn('item_code', function ($produk) {
                // Use kode_produk (Product Code) instead of item_code
                $code = !empty($produk->kode_produk) ? $produk->kode_produk : ($produk->item_code ?? '-');
                return '<span class="label label-success">'. $code .'</span>';
            })
            ->addColumn('shop_name', function ($produk) {
                return e($produk->shop_name ?? '—');
            })
            ->addColumn('previous_stock_edit', function ($produk) {
                $hid = (int) ($produk->history_id ?? 0);
                $val = (int) ($produk->previous_stock ?? 0);

                return '<input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control stock-history-edit-field js-inp-prev-stock" data-history-id="'
                    .$hid.'" data-original="'.e((string) $val).'" value="'.e((string) $val).'" style="min-width:88px;max-width:120px;" />';
            })
            ->addColumn('quantity_added', function ($produk) {
                $hid = (int) ($produk->history_id ?? 0);
                $amount = (int) ($produk->quantity_added ?? 0);

                return '<input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control stock-history-edit-field js-inp-qty-added" data-history-id="'
                    .$hid.'" data-original="'.e((string) $amount).'" value="'.e((string) $amount).'" style="min-width:88px;max-width:120px;" />';
            })
            ->addColumn('stock_after_update', function ($produk) {
                $after = (int) ($produk->current_stock ?? 0);
                $prev = (int) ($produk->previous_stock ?? 0);
                $added = (int) ($produk->quantity_added ?? 0);

                return '<span class="stock-after-update" title="'
                    .e((string) $prev).' + '.e((string) $added).' = '.e((string) $after).'" style="font-weight:bold;font-size:1em;">'
                    .number_format($after).'</span>';
            })
            ->addColumn('stock_date', function ($produk) {
                $hid = (int) ($produk->history_id ?? 0);
                $d = $produk->stock_date ?? '';
                if ($d === '' && ! empty($produk->stock_updated_at)) {
                    $d = \Carbon\Carbon::parse($produk->stock_updated_at)->format('Y-m-d');
                }
                $displayD = '';
                if ($d !== '' && $d !== null) {
                    try {
                        $displayD = \Carbon\Carbon::parse($d)->format('d/m/Y');
                    } catch (\Throwable $e) {
                        $displayD = '';
                    }
                }

                return '<input type="text" class="form-control stock-history-edit-field js-inp-stock-date" data-history-id="'
                    .$hid.'" data-original="'.e($displayD).'" value="'.e($displayD).'" placeholder="DD/MM/YYYY" autocomplete="off" '
                    .'title="Date as DD/MM/YYYY. You may also use YYYY-MM-DD." style="min-width:140px;max-width:180px;" />';
            })
            ->addColumn('product_movement', function ($produk) {
                $id = (int) ($produk->id_produk ?? 0);
                if ($id <= 0) {
                    return '—';
                }

                return '<button type="button" class="btn btn-xs btn-info btn-flat btn-open-stock-movement" '
                    .'data-url="'.e(route('produk.stock-movement', $id)).'" '
                    .'data-produk-id="'.$id.'" '
                    .'data-product-name="'.e($produk->nama_produk ?? '').'" '
                    .'title="Product movement"><i class="fa fa-id-card"></i></button>';
            })
            ->orderColumn('stock_date', 'produk_history.created_at $1')
            ->orderColumn('item_code', 'produk.kode_produk $1')
            ->orderColumn('nama_produk', 'produk.nama_produk $1')
            ->orderColumn('shop_name', 'shops.shop_name $1')
            ->orderColumn('previous_stock_edit', 'produk_history.previous_stock $1')
            ->orderColumn('quantity_added', '(produk_history.current_stock - produk_history.previous_stock) $1')
            ->orderColumn('stock_after_update', 'produk_history.current_stock $1')
            ->with([
                'total_items_updated' => $totalItemsUpdated,
                'total_quantity_added' => $totalQuantityAdded
            ])
            ->rawColumns(['item_code', 'previous_stock_edit', 'quantity_added', 'stock_after_update', 'stock_date', 'product_movement'])
            ->make(true);
    }

    /**
     * JSON for editing a single produk_history row (Stock History page).
     */
    public function editProdukHistory(ProdukHistory $history)
    {
        $qtyAdded = (int) $history->current_stock - (int) $history->previous_stock;
        if ($qtyAdded <= 0) {
            return response()->json(['message' => 'This record cannot be edited from this screen.'], 422);
        }

        $produk = Produk::find($history->id_produk);

        return response()->json([
            'id' => $history->id,
            'id_produk' => $history->id_produk,
            'nama_produk' => $produk->nama_produk ?? '',
            'kode_produk' => ! empty($produk->kode_produk) ? $produk->kode_produk : ($produk->item_code ?? ''),
            'previous_stock' => (int) $history->previous_stock,
            'quantity_added' => $qtyAdded,
            'current_stock' => (int) $history->current_stock,
            'stock_date' => $history->created_at ? Carbon::parse($history->created_at)->format('Y-m-d') : '',
            'notes' => $history->notes,
        ]);
    }

    /**
     * Update a produk_history row and adjust product stock by the change in quantity added.
     */
    public function updateProdukHistory(Request $request, ProdukHistory $history)
    {
        $oldQtyAdded = (int) $history->current_stock - (int) $history->previous_stock;
        if ($oldQtyAdded <= 0) {
            return response()->json(['message' => 'This record cannot be edited from this screen.'], 422);
        }

        $oldPrevious = (int) $history->previous_stock;
        $oldCurrent = (int) $history->current_stock;
        $oldDateLabel = $history->created_at
            ? Carbon::parse($history->created_at)->format('d/m/Y')
            : '';

        $validated = $request->validate([
            'previous_stock' => 'required|integer|min:0',
            'quantity_added' => 'required|integer|min:1',
            'stock_date' => 'required|date',
            'notes' => 'sometimes|nullable|string',
        ]);

        $newPrevious = (int) $validated['previous_stock'];
        $newQtyAdded = (int) $validated['quantity_added'];
        $newCurrent = $newPrevious + $newQtyAdded;

        $date = Carbon::parse($validated['stock_date'])->startOfDay();

        try {
            DB::transaction(function () use ($history, $newPrevious, $newQtyAdded, $newCurrent, $validated, $date) {
                $produk = Produk::lockForUpdate()->find($history->id_produk);
                if (! $produk) {
                    throw new \RuntimeException('PRODUCT_NOT_FOUND');
                }

                $historyUpdate = [
                    'previous_stock' => $newPrevious,
                    'restock_amount' => $newQtyAdded,
                    'current_stock' => $newCurrent,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
                if (array_key_exists('notes', $validated)) {
                    $historyUpdate['notes'] = $validated['notes'];
                }

                DB::table('produk_history')->where('id', $history->id)->update($historyUpdate);

                $this->rebuildProdukHistoryChain((int) $history->id_produk);

                $produk->refresh();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'PRODUCT_NOT_FOUND') {
                return response()->json(['message' => 'Product not found.'], 404);
            }
            throw $e;
        }

        try {
            $newDateLabel = $date->format('d/m/Y');
            $historyChanges = [];
            if ($oldPrevious !== $newPrevious) {
                $historyChanges[] = [
                    'field' => 'previous_stock',
                    'label' => 'Previous stock',
                    'old' => (string) $oldPrevious,
                    'new' => (string) $newPrevious,
                ];
            }
            if ($oldQtyAdded !== $newQtyAdded) {
                $historyChanges[] = [
                    'field' => 'quantity_added',
                    'label' => 'Quantity added',
                    'old' => (string) $oldQtyAdded,
                    'new' => (string) $newQtyAdded,
                ];
            }
            if ($oldCurrent !== $newCurrent) {
                $historyChanges[] = [
                    'field' => 'stock_after',
                    'label' => 'Stock after update',
                    'old' => (string) $oldCurrent,
                    'new' => (string) $newCurrent,
                ];
            }
            if ($oldDateLabel !== $newDateLabel) {
                $historyChanges[] = [
                    'field' => 'stock_date',
                    'label' => 'Date',
                    'old' => $oldDateLabel ?: '—',
                    'new' => $newDateLabel,
                ];
            }
            app(ProdukEditLogService::class)->logExplicit(
                (int) $history->id_produk,
                $historyChanges,
                'stock_history'
            );
        } catch (\Throwable $e) {
            \Log::warning('produk_edit_log stock_history: '.$e->getMessage());
        }

        return response()->json(['message' => 'Stock history updated successfully.']);
    }

    /**
     * Display out of stock products page
     *
     * @return \Illuminate\Http\Response
     */
    public function outOfStockIndex()
    {
        return view('produk.out_of_stock');
    }

    /**
     * Get out of stock products data for DataTables
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function outOfStockData(Request $request)
    {
        $query = Produk::leftJoin('kategori', 'kategori.id_kategori', 'produk.id_kategori')
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
            ->select(
                'produk.id_produk',
                'produk.nama_produk',
                'produk.kode_produk',
                'produk.stok',
                'produk.harga_beli',
                'produk.harga_jual',
                'produk.reorder',
                'kategori.nama_kategori',
                'shops.shop_name',
                'supplier.nama as supplier_name',
                'produk.created_at'
            )
            ->where(function($q) {
                $q->where('produk.stok', '<=', 0)
                  ->orWhereNull('produk.stok');
            });

        // Apply filters
        if ($request->filled('shop_id')) {
            $query->where('produk.shop_id', $request->input('shop_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('produk.id_supplier', $request->input('supplier_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('produk.id_kategori', $request->input('category_id'));
        }

        $produk = $query->orderBy('produk.nama_produk')
            ->get();

        // Calculate summary
        $totalOutOfStock = $produk->count();

        return datatables()
            ->of($produk)
            ->addIndexColumn()
            ->addColumn('stok', function ($produk) {
                $stock = $produk->stok ?? 0;
                return '<span class="label label-danger">'. format_uang($stock) .'</span>';
            })
            ->addColumn('harga_beli', function ($produk) {
                return 'KES ' . number_format($produk->harga_beli, 2);
            })
            ->addColumn('harga_jual', function ($produk) {
                return 'KES ' . number_format($produk->harga_jual, 2);
            })
            ->addColumn('reorder', function ($produk) {
                $reorder = $produk->reorder ?? 0;
                if ($reorder > 0) {
                    return '<span class="label label-warning">'. format_uang($reorder) .'</span>';
                }
                return '<span class="text-muted">-</span>';
            })
            ->addColumn('aksi', function ($produk) {
                $productName = addslashes($produk->nama_produk . ' (' . $produk->kode_produk . ')');
                $currentStock = $produk->stok ?? 0;
                return '
                    <button type="button" class="btn btn-xs btn-warning btn-flat" onclick="updateStock('.$produk->id_produk.', \''.$productName.'\', '.$currentStock.')">
                        <i class="fa fa-edit"></i> Update Stock
                    </button>
                ';
            })
            ->with([
                'total_out_of_stock' => $totalOutOfStock
            ])
            ->rawColumns(['stok', 'reorder', 'aksi'])
            ->make(true);
    }

    /**
     * Base query: products with no valid supplier (null / zero id, or supplier row missing).
     */
    protected function buildMissingSupplierQuery(Request $request)
    {
        $query = Produk::query()
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
            ->select(
                'produk.id_produk',
                'produk.nama_produk',
                'produk.item_code',
                'produk.stok',
                'produk.harga_beli',
                'produk.harga_jual',
                'produk.reorder',
                'produk.id_supplier',
                'shops.shop_name'
            )
            ->where(function ($q) {
                $q->whereNull('produk.id_supplier')
                    ->orWhere('produk.id_supplier', '=', 0)
                    ->orWhereNull('supplier.id_supplier');
            });

        if ($request->filled('shop_id')) {
            $query->where('produk.shop_id', $request->input('shop_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('produk.id_kategori', $request->input('category_id'));
        }

        return $query;
    }

    /**
     * JSON for Select2: suppliers (search by name / phone), max 50 rows.
     */
    public function missingSupplierSuppliersSelect(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = Supplier::query()->orderBy('nama');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('nama', 'like', $like)
                    ->orWhere('telepon', 'like', $like);
            });
        }
        $rows = $query->limit(50)->get(['id_supplier', 'nama', 'mop']);
        $results = $rows->map(function ($s) {
            $mop = trim((string) ($s->mop ?? ''));
            $text = (string) ($s->nama ?? 'Supplier #'.$s->id_supplier);
            if ($mop !== '') {
                $text .= ' — '.$mop;
            }

            return ['id' => (int) $s->id_supplier, 'text' => $text];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Products in stock catalogue with no valid supplier (null / zero id, or supplier row missing).
     */
    public function missingSupplierIndex()
    {
        return view('produk.missing_supplier');
    }

    /**
     * DataTable JSON for products missing a supplier assignment.
     */
    public function missingSupplierData(Request $request)
    {
        $rows = $this->buildMissingSupplierQuery($request)->orderBy('produk.nama_produk')->get();
        $total = $rows->count();

        return datatables()
            ->of($rows)
            ->addIndexColumn()
            ->addColumn('stok', function ($row) {
                $stock = $row->stok ?? 0;
                $labelClass = 'label-danger';
                if ($stock > 0) {
                    $labelClass = ($row->reorder && $stock <= $row->reorder) ? 'label-warning' : 'label-success';
                }

                return '<span class="label '.$labelClass.'">'.format_uang($stock).'</span>';
            })
            ->addColumn('harga_beli', function ($row) {
                return 'KES '.number_format((float) $row->harga_beli, 2);
            })
            ->addColumn('harga_jual', function ($row) {
                return 'KES '.number_format((float) $row->harga_jual, 2);
            })
            ->addColumn('reorder', function ($row) {
                $reorder = $row->reorder ?? 0;
                if ($reorder > 0) {
                    return '<span class="label label-warning">'.format_uang($reorder).'</span>';
                }

                return '<span class="text-muted">-</span>';
            })
            ->addColumn('reason', function ($row) {
                if ($row->id_supplier === null) {
                    return '<span class="text-muted">Supplier not set</span>';
                }
                if ((int) $row->id_supplier === 0) {
                    return '<span class="text-muted">Supplier id is 0</span>';
                }

                return '<span class="label label-danger">Supplier #'.e((string) $row->id_supplier).' missing</span>';
            })
            ->addColumn('supplier_select', function ($row) {
                $pid = (int) $row->id_produk;

                return '<select class="form-control input-sm js-missing-supplier-sel" data-produk-id="'.$pid.'" data-placeholder="Search supplier…" style="min-width:200px"><option></option></select>';
            })
            ->with([
                'total_missing_supplier' => $total,
            ])
            ->rawColumns(['stok', 'reorder', 'reason', 'supplier_select'])
            ->make(true);
    }

    /**
     * PDF export for products missing a supplier (same filters as the on-screen list).
     */
    public function exportMissingSupplierPdf(Request $request)
    {
        try {
            $items = $this->buildMissingSupplierQuery($request)->orderBy('produk.nama_produk')->get();
            $setting = Setting::first();

            $shopFilterName = null;
            if ($request->filled('shop_id')) {
                $shop = Shop::find((int) $request->input('shop_id'));
                $shopFilterName = $shop ? $shop->shop_name : null;
            }

            $categoryFilterName = null;
            if ($request->filled('category_id')) {
                $kat = Kategori::find((int) $request->input('category_id'));
                $categoryFilterName = $kat ? $kat->nama_kategori : null;
            }

            $pdf = PDF::loadView('produk.missing_supplier_pdf', [
                'items' => $items,
                'setting' => $setting,
                'total' => $items->count(),
                'shopFilterName' => $shopFilterName,
                'categoryFilterName' => $categoryFilterName,
            ]);
            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('products_missing_supplier_'.date('Y-m-d_His').'.pdf');
        } catch (\Throwable $e) {
            \Log::error('exportMissingSupplierPdf: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response('Failed to generate PDF.', 500);
        }
    }

    /**
     * Export updated items to PDF
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportUpdatedItemsPdf(Request $request)
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $productName = $request->get('product_name');
            $shopId = $request->get('shop_id');
            $supplierId = $request->get('supplier_id');

            // Show ALL history entries, even if same product appears multiple times
            $query = Produk::leftJoin('kategori', 'kategori.id_kategori', 'produk.id_kategori')
                ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
                ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
                ->join('produk_history', 'produk_history.id_produk', '=', 'produk.id_produk')
                ->select(
                    'produk.id_produk',
                    'produk.nama_produk',
                    'produk.kode_produk',
                    'produk.item_code',
                    'produk_history.id as history_id',
                    'shops.shop_name',
                    'supplier.nama as supplier_name',
                    'produk_history.previous_stock',
                    'produk_history.restock_amount',
                    'produk_history.current_stock',
                    DB::raw('(produk_history.current_stock - produk_history.previous_stock) as quantity_added'),
                    'produk_history.created_at as stock_updated_at'
                )
                ->whereRaw('(produk_history.current_stock - produk_history.previous_stock) > 0');

            $pn = trim((string) ($productName ?? ''));
            if ($pn !== '') {
                $this->applyUpdatedItemsProductSearch($query, $pn);
                foreach ($this->productsMatchingUpdatedItemsSearch($pn)->limit(25)->get() as $produkRow) {
                    $this->ensureStockIncreaseHistoryRecordedForProduct($produkRow);
                }
            }

            if ($shopId) {
                $query->where('produk.shop_id', (int) $shopId);
            }
            if ($supplierId) {
                $query->where('produk.id_supplier', (int) $supplierId);
            }

            // Apply date filters
            if ($startDate) {
                $query->whereDate('produk_history.created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('produk_history.created_at', '<=', $endDate);
            }

            $historyOrder = $pn !== '' ? 'asc' : 'desc';
            $items = $query
                ->orderBy('produk_history.created_at', $historyOrder)
                ->orderBy('produk_history.id', $historyOrder)
                ->get();

            // Calculate summary
            $totalItemsUpdated = $items->count();
            $totalQuantityAdded = $items->sum('quantity_added');

            // Get company settings
            $setting = Setting::first();

            $shopFilterName = null;
            if ($shopId) {
                $shop = Shop::find((int) $shopId);
                $shopFilterName = $shop ? $shop->shop_name : null;
            }
            $supplierFilterName = null;
            if ($supplierId) {
                $supplier = Supplier::find((int) $supplierId);
                $supplierFilterName = $supplier ? $supplier->nama : null;
            }

            $pdf = PDF::loadView('produk.updated_pdf', [
                'items' => $items,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'shopFilterName' => $shopFilterName,
                'supplierFilterName' => $supplierFilterName,
                'totalItemsUpdated' => $totalItemsUpdated,
                'totalQuantityAdded' => $totalQuantityAdded,
                'setting' => $setting
            ]);

            $pdf->setPaper('a4', 'landscape');
            return $pdf->stream('updated_items_' . date('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            \Log::error('Error exporting updated items PDF: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export updated items to Excel
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportUpdatedItemsExcel(Request $request)
    {
        try {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $productName = $request->get('product_name');
            $shopId = $request->get('shop_id');
            $supplierId = $request->get('supplier_id');

            // Show ALL history entries, even if same product appears multiple times
            $query = Produk::leftJoin('kategori', 'kategori.id_kategori', 'produk.id_kategori')
                ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
                ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
                ->join('produk_history', 'produk_history.id_produk', '=', 'produk.id_produk')
                ->select(
                    'produk.id_produk',
                    'produk.nama_produk',
                    'produk.kode_produk',
                    'produk.item_code',
                    'produk_history.id as history_id',
                    'shops.shop_name',
                    'supplier.nama as supplier_name',
                    'produk_history.previous_stock',
                    'produk_history.restock_amount',
                    'produk_history.current_stock',
                    DB::raw('(produk_history.current_stock - produk_history.previous_stock) as quantity_added'),
                    'produk_history.created_at as stock_updated_at'
                )
                ->whereRaw('(produk_history.current_stock - produk_history.previous_stock) > 0');

            $pn = trim((string) ($productName ?? ''));
            if ($pn !== '') {
                $this->applyUpdatedItemsProductSearch($query, $pn);
                foreach ($this->productsMatchingUpdatedItemsSearch($pn)->limit(25)->get() as $produkRow) {
                    $this->ensureStockIncreaseHistoryRecordedForProduct($produkRow);
                }
            }

            if ($shopId) {
                $query->where('produk.shop_id', (int) $shopId);
            }
            if ($supplierId) {
                $query->where('produk.id_supplier', (int) $supplierId);
            }

            // Apply date filters
            if ($startDate) {
                $query->whereDate('produk_history.created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('produk_history.created_at', '<=', $endDate);
            }

            $historyOrder = $pn !== '' ? 'asc' : 'desc';
            $items = $query
                ->orderBy('produk_history.created_at', $historyOrder)
                ->orderBy('produk_history.id', $historyOrder)
                ->get();

            // Calculate summary
            $totalItemsUpdated = $items->count();
            $totalQuantityAdded = $items->sum('quantity_added');

            // Generate filename with date range if applicable
            $filename = 'updated_items_' . date('Y-m-d');
            if ($startDate || $endDate) {
                $filename .= '_' . ($startDate ?: 'all') . '_to_' . ($endDate ?: 'all');
            }
            $filename .= '.csv';

            // Create callback function for streaming CSV
            $callback = function() use ($items, $totalItemsUpdated, $totalQuantityAdded) {
                $file = fopen('php://output', 'w');
                
                // Add BOM for UTF-8 Excel compatibility
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

                // Write headers
                $headers = [
                    '#',
                    'Product Code',
                    'Product Name',
                    'Shop',
                    'Supplier',
                    'Previous Stock',
                    'Quantity Added',
                    'Current Stock',
                    'Date Updated'
                ];
                fputcsv($file, $headers);

                // Write data rows
                $index = 1;
                foreach ($items as $item) {
                    $code = !empty($item->kode_produk) ? $item->kode_produk : ($item->item_code ?? '-');
                    $row = [
                        $index++,
                        $code,
                        $item->nama_produk,
                        $item->shop_name ?? '-',
                        $item->supplier_name ?? '-',
                        $item->previous_stock ?? 0,
                        $item->quantity_added ?? 0,
                        $item->current_stock ?? 0,
                        $item->stock_updated_at ? Carbon::parse($item->stock_updated_at)->format('d/m/Y') : '-'
                    ];
                    fputcsv($file, $row);
                }

                // Write summary row
                fputcsv($file, []); // Empty row
                fputcsv($file, ['Summary']);
                fputcsv($file, ['Total Items Updated', $totalItemsUpdated]);
                fputcsv($file, ['Total Quantity Added', $totalQuantityAdded]);

                fclose($file);
            };

            return Response::stream($callback, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error exporting updated items Excel: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to generate Excel: ' . $e->getMessage()], 500);
        }
    }

    public function incompleteData()
    {
        $produk = Produk::leftJoin('kategori', 'kategori.id_kategori', 'produk.id_kategori')
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
            ->select(
                'produk.*',
                'nama_kategori',
                'shops.shop_name',
                'supplier.nama as supplier_name',
                DB::raw("(SELECT p.receiptno
                          FROM penjualan_detail pd
                          INNER JOIN penjualan p ON p.id_penjualan = pd.id_penjualan
                          WHERE pd.id_produk = produk.id_produk
                          ORDER BY p.id_penjualan DESC
                          LIMIT 1) as last_receipt_no")
            )
            ->where('produk.is_incomplete', true)
            ->orderBy('nama_produk')
            ->get();

        return datatables()
            ->of($produk)
            ->addIndexColumn()
            ->addColumn('select_all', function ($produk) {
                return '
                    <input type="checkbox" name="id_produk[]" value="'. $produk->id_produk .'">
                ';
            })
            ->addColumn('item_code', function ($produk) {
                return '<span class="label label-success">'. $produk->item_code .'</span>';
            })
            ->addColumn('harga_beli', function ($produk) {
                return format_uang($produk->harga_beli);
            })
            ->addColumn('harga_jual', function ($produk) {
                return format_uang($produk->harga_jual);
            })
            ->addColumn('reorder_level', function ($produk) {
                return $produk->reorder ?? 0;
            })
            ->addColumn('stok', function ($produk) {
                $labelClass = 'label-success';
                if ($produk->stok <= 0) {
                    $labelClass = 'label-danger';
                } elseif ($produk->reorder && $produk->stok <= $produk->reorder) {
                    $labelClass = 'label-warning';
                }

                return '<span class="label '. $labelClass .'">'. format_uang($produk->stok) .'</span>';
            })
            ->addColumn('stok_raw', function ($produk) {
                return $produk->stok;
            })
            ->addColumn('last_receipt_no', function ($produk) {
                $receiptNo = trim((string) ($produk->last_receipt_no ?? ''));
                if ($receiptNo === '') {
                    return '<span class="label label-default">-</span>';
                }

                $url = route('penjualan.index', ['receipt_number' => $receiptNo]);
                return '<a href="'.e($url).'" class="label label-info" title="Open Sales List filtered by this receipt">'.e($receiptNo).'</a>';
            })
            ->addColumn('aksi', function ($produk) {
                $u = auth()->user();
                $buttons = '<div class="btn-group">';
                if ($u && ($u->inv_update ?? false)) {
                    $buttons .= '<button type="button" onclick="editForm(`'. route('produk.update', $produk->id_produk) .'`)" class="btn btn-xs btn-primary btn-flat"><i class="fa fa-pencil"></i></button>';
                    $buttons .= '<button type="button" class="btn btn-xs btn-success btn-flat btn-open-update-stock" '
                        .'data-restock-url="'.e(route('produk.restock')).'" '
                        .'data-produk-id="'.(int) $produk->id_produk.'" '
                        .'data-product-name="'.e($produk->nama_produk ?? '').'" '
                        .'data-current-stock="'.(int) ($produk->stok ?? 0).'" '
                        .'title="Update Stock"><i class="fa fa-plus-circle"></i></button>';
                }
                if ($u && ($u->inv_delete ?? false)) {
                    $buttons .= '<button type="button" onclick="deleteData(`'. route('produk.destroy', $produk->id_produk) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>';
                }
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['aksi', 'item_code', 'select_all', 'reorder_level', 'stok', 'last_receipt_no'])
            ->make(true);
    }

    /**
     * Get incomplete products count
     *
     * @return \Illuminate\Http\Response
     */
    public function incompleteCount()
    {
        $count = Produk::where('is_incomplete', true)->count();
        return response()->json(['count' => $count]);
    }

    /**
     * Display sold out of stock items page
     *
     * @return \Illuminate\Http\Response
     */
    public function soldOutOfStockIndex()
    {
        return view('produk.sold_out_of_stock');
    }

    /**
     * Get sold out of stock products data for DataTables
     * Products that were sold when stock was insufficient
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function soldOutOfStockData(Request $request)
    {
        // Get all products with their total sold quantities from completed sales
        $query = Produk::leftJoin('kategori', 'kategori.id_kategori', 'produk.id_kategori')
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
            ->leftJoin('penjualan_detail', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('penjualan', function($join) {
                $join->on('penjualan.id_penjualan', '=', 'penjualan_detail.id_penjualan')
                     ->where('penjualan.status', '=', 'completed');
            })
            ->select(
                'produk.id_produk',
                'produk.nama_produk',
                'produk.kode_produk',
                'produk.item_code',
                'produk.stok as current_stock',
                'produk.harga_beli',
                'produk.harga_jual',
                'kategori.nama_kategori',
                'shops.shop_name',
                'supplier.nama as supplier_name',
                DB::raw('COALESCE(SUM(penjualan_detail.jumlah), 0) as total_sold')
            )
            ->groupBy(
                'produk.id_produk',
                'produk.nama_produk',
                'produk.kode_produk',
                'produk.item_code',
                'produk.stok',
                'produk.harga_beli',
                'produk.harga_jual',
                'kategori.nama_kategori',
                'shops.shop_name',
                'supplier.nama'
            )
            ->havingRaw('(COALESCE(produk.stok, 0) < 0 OR COALESCE(SUM(penjualan_detail.jumlah), 0) > COALESCE(produk.stok, 0))');

        // Apply filters
        if ($request->filled('shop_id')) {
            $query->where('produk.shop_id', $request->input('shop_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('produk.id_supplier', $request->input('supplier_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('produk.id_kategori', $request->input('category_id'));
        }

        $produk = $query->orderBy('produk.nama_produk')->get();

        // Calculate sold out of stock quantity for each product
        $data = [];
        foreach ($produk as $item) {
            $currentStock = $item->current_stock ?? 0;
            $totalSold = $item->total_sold ?? 0;
            
            // If current stock is negative, that's the deficit (sold out of stock)
            // If current stock is positive/zero, calculate: total_sold - current_stock
            if ($currentStock < 0) {
                $soldOutOfStock = abs($currentStock); // The negative value represents the deficit
            } else {
                $soldOutOfStock = max(0, $totalSold - $currentStock);
            }

            if ($soldOutOfStock > 0) {
                $code = !empty($item->kode_produk) ? $item->kode_produk : ($item->item_code ?? '-');
                $data[] = [
                    'id_produk' => $item->id_produk,
                    'kode_produk' => '<span class="label label-success">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</span>',
                    'nama_produk' => $item->nama_produk,
                    'nama_kategori' => $item->nama_kategori ?? '-',
                    'shop_name' => $item->shop_name ?? '-',
                    'supplier_name' => $item->supplier_name ?? '-',
                    'harga_beli' => format_uang($item->harga_beli),
                    'harga_jual' => format_uang($item->harga_jual),
                    'current_stock' => $currentStock,
                    'total_sold' => $totalSold,
                    'sold_out_of_stock' => $soldOutOfStock,
                    'aksi' => '<button onclick="restockProduct(' . $item->id_produk . ', ' . $soldOutOfStock . ')" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i> Add Stock</button>'
                ];
            }
        }

        return datatables()
            ->of($data)
            ->addIndexColumn()
            ->rawColumns(['aksi', 'kode_produk'])
            ->make(true);
    }

    /**
     * Restock product and automatically deduct sold out of stock quantities
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function restockSoldOutOfStock(Request $request)
    {
        try {
            $request->validate([
                'id_produk' => 'required|exists:produk,id_produk',
                'additional_stock' => 'required|integer|min:1',
                'date_in' => 'required|date'
            ]);

            $produk = Produk::findOrFail($request->id_produk);

            // Store previous stock before update
            $previousStock = $produk->stok ?? 0;
            
            // Calculate sold out of stock (deficit)
            // If current stock is negative, that's the deficit
            // If current stock is positive/zero, calculate from total sold
            if ($previousStock < 0) {
                $soldOutOfStock = abs($previousStock); // The negative value represents the deficit
            } else {
                // Calculate total sold from completed sales
                $totalSold = PenjualanDetail::join('penjualan', 'penjualan.id_penjualan', '=', 'penjualan_detail.id_penjualan')
                    ->where('penjualan_detail.id_produk', $produk->id_produk)
                    ->where('penjualan.status', 'completed')
                    ->sum('penjualan_detail.jumlah');
                $soldOutOfStock = max(0, $totalSold - $previousStock);
            }

            // Calculate new stock: additional_stock - sold_out_of_stock
            // Example: If stock is -1 (sold 1 out of stock) and we add 10, new stock = 10 - 1 = 9
            // This means: we add 10, but 1 is used to cover the deficit, leaving 9
            $newStock = $request->additional_stock - $soldOutOfStock;

            // Ensure we have enough stock to cover the deficit
            if ($request->additional_stock < $soldOutOfStock) {
                return response()->json([
                    'message' => 'Insufficient stock to add. Need at least ' . $soldOutOfStock . ' units to cover sold out of stock items. You are adding ' . $request->additional_stock . ' units.',
                    'sold_out_of_stock' => $soldOutOfStock
                ], 400);
            }

            // Update product stock
            $produk->stok = $newStock;
            $produk->date_in = $request->date_in;
            $produk->save();

            // Log stock update to produk_history
            try {
                // Store the submission date from `date_in`.
                $historyDate = $request->date_in ?? now();
                $hist = new ProdukHistory();
                $hist->timestamps = false;
                $hist->fill([
                    'id_produk' => $produk->id_produk,
                    'previous_stock' => $previousStock,
                    'restock_amount' => $request->additional_stock,
                    'current_stock' => $newStock,
                    'notes' => 'Restocked with automatic deduction of ' . $soldOutOfStock . ' units sold out of stock',
                    'created_at' => $historyDate,
                    'updated_at' => $historyDate,
                ]);
                $hist->save();
            } catch (\Exception $e) {
                \Log::warning('Could not create produk_history entry: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Stock updated successfully. Added ' . $request->additional_stock . ' units, deducted ' . $soldOutOfStock . ' units for sold out of stock items. New stock: ' . $newStock,
                'new_stock' => $newStock,
                'sold_out_of_stock' => $soldOutOfStock
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error restocking sold out of stock product: ' . $e->getMessage());
            return response()->json(['message' => 'Unable to update stock: ' . $e->getMessage()], 500);
        }
    }

    // visit "codeastro" for more projects!
    public function cetakBarcode(Request $request)
    {
        $dataproduk = array();
        foreach ($request->id_produk as $id) {
            $produk = Produk::find($id);
            $dataproduk[] = $produk;
        }

        $no  = 1;
        $pdf = PDF::loadView('produk.barcode', compact('dataproduk', 'no'));
        $pdf->setPaper('a4', 'potrait');
        return $pdf->stream('product.pdf');
    }
}
