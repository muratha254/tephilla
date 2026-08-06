<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pembelian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PembelianDetail;
use App\Models\Produk;
use App\Models\Supplier;
use App\Models\Shop;
use PDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PembelianController extends Controller
{
    public function index(Request $request)
    {
        $query = Pembelian::query();

        if ($request->filled('start_date')) {
            $query->whereDate('purchasedate2', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('purchasedate2', '<=', $request->input('end_date'));
        }

         $supplier = Supplier::orderBy('nama')->get();

       return view('pembelian.index', compact('supplier'));

   
    }
    
    public function data(Request $request)
    {

        $query = Pembelian::orderBy('id_pembelian', 'desc');


        // Date Filtering
        if ($request->filled('start_date')) {
            $query->whereDate('purchasedate2', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('purchasedate2', '<=', $request->input('end_date'));
        }

        $pembelian = $query->get();

            return datatables()
                ->of($pembelian)
                ->addIndexColumn()
                ->addColumn('total_item', function ($pembelian) {
                    return format_uang($pembelian->total_item);
                })
                ->addColumn('total_harga', function ($pembelian) {
                    return 'Ksh '. format_uang($pembelian->total_harga);
                })
                ->addColumn('bayar', function ($pembelian) {
                    return 'Ksh '. format_uang($pembelian->bayar);
                })
                ->addColumn('tanggal', function ($pembelian) {
                    return tanggal_indonesia($pembelian->created_at, false);
                })
                ->addColumn('supplier', function ($pembelian) {
                    return $pembelian->supplier->nama;
                })
                ->editColumn('reorder', function ($pembelian) {
                    return $pembelian->reorder . '%';
                })
                ->addColumn('aksi', function ($pembelian) {
                    $u = auth()->user();
                    $buttons = '<div class="btn-group">';
                    if ($u && ($u->inv_read ?? true)) {
                        $buttons .= '<button onclick="showDetail(`'. route('pembelian.show', $pembelian->id_pembelian) .'`)" class="btn btn-xs btn-primary btn-flat"><i class="fa fa-eye"></i></button>';
                    }
                    if ($u && ($u->inv_delete ?? false)) {
                        $buttons .= '<button onclick="deleteData(`'. route('pembelian.destroy', $pembelian->id_pembelian) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>';
                    }
                    $buttons .= '</div>';
                    return $buttons;
                })
                ->rawColumns(['aksi'])
                ->make(true);
    }

    public function create($id)
    {
        $purchasedate2 = Carbon::parse(request('purchasedate2'));

        $pembelian = new Pembelian();
        $pembelian->id_supplier = $id;
        $pembelian->total_item  = 0;
        $pembelian->total_harga = 0;
        $pembelian->reorder      = 0;
        $pembelian->bayar       = 0;
        $pembelian->purchasedate2 = $purchasedate2;
        $pembelian->save();

        session(['id_pembelian' => $pembelian->id_pembelian]);
        session(['id_supplier' => $pembelian->id_supplier]);

        return redirect()->route('pembelian_detail.index');
    }

    public function store(Request $request)
    {

        $pembelian = Pembelian::findOrFail($request->id_pembelian);
        $pembelian->total_item = $request->total_item;
        $pembelian->total_harga = $request->total;
        $pembelian->reorder = $request->reorder;
        $pembelian->bayar = $request->bayar;
        $pembelian->purchasedate2 = $request->purchasedate2;
        $pembelian->update();


        $detail = PembelianDetail::where('id_pembelian', $pembelian->id_pembelian)->get();

        foreach ($detail as $item) {

            $produk = Produk::find($item->id_produk);
            $produk->stok += $item->jumlah;
            $produk->update();
        }


        return redirect()->route('pembelian.index');
    }

    public function show($id)
    {
        $detail = PembelianDetail::with('produk')->where('id_pembelian', $id)->get();

        return datatables()
            ->of($detail)
            ->addIndexColumn()
            ->addColumn('kode_produk', function ($detail) {
                return '<span class="label label-success">'. $detail->produk->kode_produk .'</span>';
            })
            ->addColumn('nama_produk', function ($detail) {
                return $detail->produk->nama_produk;
            })
            ->addColumn('harga_beli', function ($detail) {
                return 'ksh '. format_uang($detail->harga_beli);
            })
            ->addColumn('jumlah', function ($detail) {
                return format_uang($detail->jumlah);
            })
            ->addColumn('subtotal', function ($detail) {
                return 'ksh '. format_uang($detail->subtotal);
            })
            ->rawColumns(['kode_produk'])
            ->make(true);
    }

    public function destroy($id)
    {
        $pembelian = Pembelian::find($id);
        $detail    = PembelianDetail::where('id_pembelian', $pembelian->id_pembelian)->get();
        foreach ($detail as $item) {
            $produk = Produk::find($item->id_produk);
            if ($produk) {
                $produk->stok -= $item->jumlah;
                $produk->update();
            }
            $item->delete();
        }

        $pembelian->delete();

        return response(null, 204);
    }

    /**
     * Show import form
     */
    public function importForm()
    {
        return view('pembelian.import');
    }

    /**
     * Import purchases from Excel file
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (count($rows) < 2) {
                return redirect()->route('pembelian.import')
                    ->withErrors(['error' => 'Excel file is empty or has no data rows.']);
            }

            // Skip header row (first row)
            $dataRows = array_slice($rows, 1);
            
            $importedCount = 0;
            $errors = [];
            $warnings = [];

            DB::beginTransaction();

            // Group rows by purchase date and supplier (to create one purchase per date/supplier combination)
            $groupedPurchases = [];
            
            foreach ($dataRows as $rowIndex => $row) {
                try {
                    // Map columns based on Excel structure:
                    // Column 0: Buying pri (Buying price)
                    // Column 1: Selling Pri (Selling Price)
                    // Column 2: Commo (Commodity/Product name)
                    // Column 3: Supplier r (Supplier)
                    // Column 4: Means of (Payment method - not needed for purchase)
                    // Column 5: STOCK IN (Quantity purchased)
                    // Column 6: Shops (Shop name)
                    // Column 7: DATEsubmitted (Purchase date)

                    $buyingPrice = isset($row[0]) ? floatval($row[0]) : 0;
                    $sellingPrice = isset($row[1]) ? floatval($row[1]) : 0;
                    $commodity = isset($row[2]) ? trim($row[2]) : '';
                    $supplierName = isset($row[3]) ? trim($row[3]) : '';
                    $stockIn = isset($row[5]) ? intval($row[5]) : 0;
                    $shopName = isset($row[6]) ? trim($row[6]) : '';
                    $purchaseDate = isset($row[7]) ? $this->parseDate($row[7]) : Carbon::today()->toDateString();

                    // Skip rows with empty commodity or invalid stock
                    if (empty($commodity) || $stockIn <= 0) {
                        $warnings[] = "Row " . ($rowIndex + 2) . ": Skipped - empty commodity or invalid stock quantity";
                        continue;
                    }

                    // Skip rows with empty supplier
                    if (empty($supplierName)) {
                        $warnings[] = "Row " . ($rowIndex + 2) . ": Skipped - empty supplier name";
                        continue;
                    }

                    // Create group key: date + supplier
                    $groupKey = $purchaseDate . '_' . $supplierName;

                    if (!isset($groupedPurchases[$groupKey])) {
                        $groupedPurchases[$groupKey] = [
                            'date' => $purchaseDate,
                            'supplier' => $supplierName,
                            'items' => []
                        ];
                    }

                    $groupedPurchases[$groupKey]['items'][] = [
                        'commodity' => $commodity,
                        'buying_price' => $buyingPrice,
                        'selling_price' => $sellingPrice,
                        'stock_in' => $stockIn,
                        'shop_name' => $shopName,
                    ];

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                    \Log::error('Error processing purchase row: ' . $e->getMessage(), [
                        'row_index' => $rowIndex + 2,
                        'row_data' => array_slice($row, 0, 8)
                    ]);
                }
            }

            // Process each grouped purchase
            foreach ($groupedPurchases as $groupKey => $purchaseData) {
                try {
                    $purchaseDate = $purchaseData['date'];
                    $supplierName = $purchaseData['supplier'];
                    $items = $purchaseData['items'];

                    if (empty($items)) {
                        continue;
                    }

                    // Find or create supplier
                    $supplier = Supplier::where('nama', $supplierName)->first();
                    if (!$supplier) {
                        $supplier = Supplier::create([
                            'nama' => $supplierName,
                            'alamat' => '',
                            'telepon' => '',
                        ]);
                    }

                    // Create or get purchase record for this date and supplier
                    $pembelian = Pembelian::where('id_supplier', $supplier->id_supplier)
                        ->whereDate('purchasedate2', $purchaseDate)
                        ->first();

                    if (!$pembelian) {
                        $pembelian = Pembelian::create([
                            'id_supplier' => $supplier->id_supplier,
                            'total_item' => 0,
                            'total_harga' => 0,
                            'diskon' => 0,
                            'bayar' => 0,
                            'purchasedate2' => $purchaseDate,
                        ]);
                    }

                    $totalItem = 0;
                    $totalHarga = 0;

                    // Process each item in this purchase
                    foreach ($items as $itemData) {
                        $commodity = $itemData['commodity'];
                        $buyingPrice = $itemData['buying_price'];
                        $sellingPrice = $itemData['selling_price'];
                        $stockIn = $itemData['stock_in'];
                        $shopName = $itemData['shop_name'];

                        // Find or create shop
                        $shop = null;
                        if (!empty($shopName)) {
                            $shop = Shop::where('shop_name', $shopName)->first();
                            if (!$shop) {
                                $shop = Shop::create([
                                    'shop_name' => $shopName,
                                ]);
                            }
                        }

                        // Find or create product
                        $produk = Produk::where('nama_produk', $commodity)->first();
                        if (!$produk) {
                            // Create product code from commodity
                            $productCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $commodity), 0, 10));
                            // Ensure unique code
                            $counter = 1;
                            $originalCode = $productCode;
                            while (Produk::where('kode_produk', $productCode)->exists()) {
                                $productCode = $originalCode . $counter;
                                $counter++;
                            }

                            $produk = Produk::create([
                                'kode_produk' => $productCode,
                                'nama_produk' => $commodity,
                                'id_kategori' => 1, // Default category
                                'id_supplier' => $supplier->id_supplier,
                                'harga_beli' => $buyingPrice > 0 ? $buyingPrice : 0,
                                'harga_jual' => $sellingPrice > 0 ? $sellingPrice : ($buyingPrice > 0 ? $buyingPrice * 1.5 : 0), // Default markup if no selling price
                                'stok' => 0, // Will be added below
                                'shop_id' => $shop ? $shop->id : null,
                                'is_incomplete' => false,
                            ]);
                        } else {
                            // Update product prices if provided
                            if ($buyingPrice > 0) {
                                $produk->harga_beli = $buyingPrice;
                            }
                            if ($sellingPrice > 0) {
                                $produk->harga_jual = $sellingPrice;
                            }
                            if ($shop) {
                                $produk->shop_id = $shop->id;
                            }
                            $produk->save();
                        }

                        // Create purchase detail
                        $subtotal = $buyingPrice * $stockIn;
                        PembelianDetail::create([
                            'id_pembelian' => $pembelian->id_pembelian,
                            'id_produk' => $produk->id_produk,
                            'harga_beli' => $buyingPrice,
                            'jumlah' => $stockIn,
                            'subtotal' => $subtotal,
                        ]);

                        // Add stock to product
                        $produk->stok += $stockIn;
                        $produk->save();

                        $totalItem += $stockIn;
                        $totalHarga += $subtotal;
                    }

                    // Update purchase totals
                    $pembelian->total_item += $totalItem;
                    $pembelian->total_harga += $totalHarga;
                    $pembelian->save();

                    $importedCount++;
                    \Log::info('Import: Successfully imported purchase', [
                        'date' => $purchaseDate,
                        'supplier' => $supplierName,
                        'items_processed' => count($items)
                    ]);

                } catch (\Exception $e) {
                    $errors[] = "Purchase group ({$purchaseData['date']} - {$purchaseData['supplier']}): " . $e->getMessage();
                    \Log::error('Error importing purchase: ' . $e->getMessage(), [
                        'purchase_data' => $purchaseData,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            DB::commit();

            $message = "Successfully imported {$importedCount} purchases.";
            if (!empty($errors)) {
                $message .= " " . count($errors) . " errors occurred.";
            }
            if (!empty($warnings)) {
                $message .= " " . count($warnings) . " warnings.";
            }

            return redirect()->route('pembelian.import')
                ->with('success', $message)
                ->with('errors', $errors)
                ->with('warnings', $warnings);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error importing purchases: ' . $e->getMessage());
            return redirect()->route('pembelian.import')
                ->withErrors(['error' => 'Failed to import purchases: ' . $e->getMessage()]);
        }
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return Carbon::today()->toDateString();
        }

        // Try to parse as Excel date (numeric)
        if (is_numeric($dateString)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateString);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Not an Excel date
            }
        }

        // Try Carbon parse
        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return Carbon::today()->toDateString();
        }
    }
}
