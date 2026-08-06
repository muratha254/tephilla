<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\Setting;
use App\Models\Produk;
use App\Models\ProdukHistory;
use Illuminate\Support\Carbon;
use PDF;

class ShopController extends Controller
{
    //
    public function index()
    {
        return view('shop.index');
    }

    public function data()
    {
        $shop = Shop::orderBy('id')->get();

        return datatables()
            ->of($shop)
            ->addIndexColumn()
            ->addColumn('select_all', function ($shop) {
                return '
                    <input type="checkbox" name="id_shop[]" value="'. $shop->id.'">
                ';
            })
            ->addColumn('shop_code', function ($shop) {
                return '<span class="label label-success">'. $shop->shop_code .'<span>';
            })
            ->addColumn('action', function ($shop) {
                return '
                <div class="btn-group">
                    <a href="'. route('shop.products', $shop->id) .'" class="btn btn-xs btn-info btn-flat" title="View Products"><i class="fa fa-eye"></i></a>
                    <button type="button" onclick="editForm(`'. route('shop.update', $shop->id) .'`)" class="btn btn-xs btn-primary btn-flat" title="Edit"><i class="fa fa-pencil"></i></button>
                    <button type="button" onclick="deleteData(`'. route('shop.destroy', $shop->id) .'`)" class="btn btn-xs btn-danger btn-flat" title="Delete"><i class="fa fa-trash"></i></button>
                </div>
                ';
            })
            ->rawColumns(['action', 'select_all', 'shop_code'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $shop = Shop::latest()->first() ?? new Shop();
       // $shop_code = (int) $shop->kode_member +1;

        $shop = new Shop();
       // $member->kode_member = tambah_nol_didepan($kode_member, 5);
        $shop->shop_code = $request->shop_code;
        $shop->shop_name = $request->shop_name;
       
        $shop->save();

        return response()->json('Data saved successfully', 200);
    }

     public function show($id)
    {
        $shop = Shop::find($id);

        return response()->json($shop);
    }

    public function update(Request $request, $id)
    {
        $shop = Shop::find($id)->update($request->all());

        return response()->json('Data saved successfully', 200);
    }

    public function destroy($id)
    {
        $shop = Shop::find($id);
        $shop->delete();

        return response(null, 204);
    }

    public function deleteSelected(Request $request)
    {
        $request->validate([
            'id_shop' => 'required|array|min:1',
            'id_shop.*' => 'integer|distinct|exists:shops,id',
        ]);

        $deleted = Shop::query()->whereIn('id', $request->input('id_shop'))->delete();

        return response()->json([
            'message' => $deleted.' shop(s) deleted.',
            'deleted' => $deleted,
        ]);
    }

    public function products(Shop $shop)
    {
        return view('shop.products', compact('shop'));
    }

    public function productsData(Shop $shop)
    {
        $products = $shop->products()
            ->leftJoin('supplier', 'supplier.id_supplier', '=', 'produk.id_supplier')
            ->select([
                'produk.id_produk',
                'produk.item_code',
                'produk.kode_produk',
                'produk.nama_produk',
                'produk.harga_beli',
                'produk.harga_jual',
                'produk.stok',
                'produk.created_at',
                'supplier.nama as supplier_name',
            ])
            ->orderByRaw('CASE WHEN produk.stok <= 0 THEN 0 ELSE 1 END')
            ->orderBy('produk.nama_produk');

        return datatables()
            ->of($products)
            ->filterColumn('item_code', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('produk.kode_produk', 'like', "%{$keyword}%")
                      ->orWhere('produk.item_code', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('supplier_name', function ($query, $keyword) {
                $query->where('supplier.nama', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_produk', function ($query, $keyword) {
                $query->where('produk.nama_produk', 'like', "%{$keyword}%");
            })
            ->orderColumn('item_code', 'produk.kode_produk $1')
            ->orderColumn('supplier_name', 'supplier.nama $1')
            ->addIndexColumn()
            ->addColumn('item_code', function ($product) {
                return !empty($product->kode_produk) ? $product->kode_produk : ($product->item_code ?? '-');
            })
            ->addColumn('nama_produk', function ($product) {
                return $product->nama_produk ?? '-';
            })
            ->addColumn('supplier_name', function ($product) {
                return $product->supplier_name ?? '-';
            })
            ->addColumn('harga_beli', function ($product) {
                return 'KES ' . number_format($product->harga_beli, 2);
            })
            ->addColumn('harga_jual', function ($product) {
                return 'KES ' . number_format($product->harga_jual, 2);
            })
            ->addColumn('stok_raw', function ($product) {
                return $product->stok ?? 0;
            })
            ->addColumn('stok', function ($product) {
                $stock = $product->stok ?? 0;
                $isOutOfStock = $stock <= 0;
                $badgeClass = $isOutOfStock ? 'label-danger' : 'label-success';
                $badgeText = $isOutOfStock ? 'Out of Stock' : number_format($stock);
                return '<span class="label ' . $badgeClass . '">' . $badgeText . '</span>';
            })
            ->addColumn('aksi', function ($product) use ($shop) {
                $historyUrl = route('shop.products.history', [$shop->id, $product->id_produk]);
                $productName = addslashes($product->nama_produk . ' (' . $product->item_code . ')');
                $currentStock = $product->stok ?? 0;
                return '
                    <button type="button" class="btn btn-xs btn-info btn-flat" onclick="viewHistory('.$product->id_produk.')">
                        <i class="fa fa-history"></i> View History
                    </button>
                    <button type="button" class="btn btn-xs btn-warning btn-flat" onclick="updateStock('.$product->id_produk.', \''.$productName.'\', '.$currentStock.')">
                        <i class="fa fa-edit"></i> Update Stock
                    </button>
                ';
            })
            ->rawColumns(['aksi', 'stok'])
            ->make(true);
    }

    public function productHistory(Shop $shop, $productId)
    {
        $product = Produk::findOrFail($productId);
        
        if ($product->shop_id !== $shop->id) {
            abort(404);
        }

        $history = ProdukHistory::where('id_produk', $product->id_produk)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                $date = $entry->created_at ? Carbon::parse($entry->created_at)->format('d/m/Y') : '-';
                return [
                    'previous_stock' => $entry->previous_stock ?? 0,
                    'restock_amount' => $entry->restock_amount ?? 0,
                    'current_stock' => $entry->current_stock ?? 0,
                    'type' => ucfirst(str_replace('_', ' ', $entry->type ?? 'unknown')),
                    'notes' => $entry->notes ?? '',
                    'created_at' => $date,
                ];
            });

        // If no history exists, create an initial entry from product creation
        if ($history->isEmpty()) {
            $date = $product->created_at ? Carbon::parse($product->created_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y');
            $history->push([
                'previous_stock' => 0,
                'restock_amount' => $product->stok ?? 0,
                'current_stock' => $product->stok ?? 0,
                'type' => 'Initial',
                'notes' => 'Product created',
                'created_at' => $date,
            ]);
        }

        return response()->json([
            'product' => [
                'id' => $product->id_produk,
                'name' => $product->nama_produk,
                'item_code' => $product->item_code,
                'supplier' => optional($product->supplier)->nama ?? 'N/A',
            ],
            'history' => $history,
        ]);
    }

    public function productHistoryPrint(Shop $shop, $productId)
    {
        $product = Produk::findOrFail($productId);
        
        if ($product->shop_id !== $shop->id) {
            abort(404);
        }

        $history = ProdukHistory::where('id_produk', $product->id_produk)
            ->orderBy('created_at', 'desc')
            ->get();

        // If no history exists, create an initial entry
        if ($history->isEmpty()) {
            $history = collect([(object)[
                'previous_stock' => 0,
                'restock_amount' => $product->stok ?? 0,
                'current_stock' => $product->stok ?? 0,
                'type' => 'Initial',
                'notes' => 'Product created',
                'created_at' => $product->created_at ?? now(),
            ]]);
        }

        $pdf = PDF::loadView('shop.product_history_pdf', [
            'shop' => $shop,
            'product' => $product,
            'history' => $history,
        ]);

        return $pdf->download('product-history-' . $product->kode_produk . '.pdf');
    }
}
