<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Models\InvoiceItem;
use App\Models\PaymentItem;
use App\Models\Payment;
use App\Models\Produk;
use App\Models\Shop;
use App\Models\PenjualanDetail;
use App\Models\Penjualan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade as PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Models\Setting;
use App\Models\SupplierWithdrawal;
use App\Models\ProdukHistory;
use App\Models\User;
use App\Services\SupplierExcelSyncService;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class SupplierController extends Controller
{
    protected function userCanSupplierStructuralChange(): bool
    {
        $u = auth()->user();
        if (! $u) {
            return false;
        }
        if (method_exists($u, 'hasRole') && $u->hasRole(User::ROLE_ADMIN)) {
            return true;
        }

        return (bool) ($u->can_update ?? false);
    }

    /**
     * Consignment lists join penjualan so date filters can use the sale business day (saledate),
     * not only invoice_items.created_at (which may differ after midnight or backdating).
     */
    protected function invoiceItemsForConsignmentQuery(int $supplierId): Builder
    {
        return InvoiceItem::query()
            ->from('invoice_items')
            ->leftJoin('penjualan', 'invoice_items.penjualan_id', '=', 'penjualan.id_penjualan')
            ->select('invoice_items.*')
            ->where('invoice_items.supplier_id', $supplierId)
            ->whereHas('produk', function ($q) {
                $q->whereRaw('COALESCE(produk.is_incomplete, 0) = 0');
            });
    }

    protected function applyConsignmentSaleDateFilters(Builder $query, ?string $startDate, ?string $endDate): void
    {
        if (! $startDate && ! $endDate) {
            return;
        }
        $saleDayExpr = 'DATE(COALESCE(penjualan.saledate, penjualan.created_at, invoice_items.created_at))';
        if ($startDate) {
            $query->whereRaw($saleDayExpr.' >= ?', [$startDate]);
        }
        if ($endDate) {
            $query->whereRaw($saleDayExpr.' <= ?', [$endDate]);
        }
    }

    /**
     * POS receipt for this ledger line. Prefer penjualan_id so mixed-supplier receipts
     * (same basket, multiple consignment suppliers) keep the correct receipt per line.
     */
    protected function resolveReceiptNoForInvoiceItem(InvoiceItem $item): string
    {
        if ($item->penjualan_id) {
            if ($item->relationLoaded('penjualan') && $item->penjualan) {
                $r = $item->penjualan->receiptno ?? null;
                if ($r !== null && $r !== '') {
                    return (string) $r;
                }
            }
            $sale = Penjualan::query()->find($item->penjualan_id);
            if ($sale && ($sale->receiptno ?? '') !== '') {
                return (string) $sale->receiptno;
            }
        }

        $penjualanDetail = null;
        if ($item->created_at) {
            $penjualanDetail = PenjualanDetail::query()
                ->where('id_produk', $item->produk_id)
                ->where('jumlah', $item->quantity)
                ->whereDate('created_at', $item->created_at->format('Y-m-d'))
                ->orderByDesc('created_at')
                ->first();
        }

        if ($penjualanDetail) {
            $sale = Penjualan::query()->find($penjualanDetail->id_penjualan);
            if ($sale && ($sale->receiptno ?? '') !== '') {
                return (string) $sale->receiptno;
            }
        }

        return 'N/A';
    }

    /**
     * Business sale date for display / export (matches consignment date filters).
     */
    protected function resolveSaleDateYmdForInvoiceItem(InvoiceItem $item): string
    {
        if ($item->relationLoaded('penjualan') && $item->penjualan) {
            $p = $item->penjualan;
            $d = $p->saledate ?? $p->created_at ?? null;
            if ($d) {
                return Carbon::parse($d)->format('Y-m-d');
            }
        }
        if ($item->penjualan_id) {
            $p = Penjualan::query()->find($item->penjualan_id);
            if ($p) {
                $d = $p->saledate ?? $p->created_at ?? null;
                if ($d) {
                    return Carbon::parse($d)->format('Y-m-d');
                }
            }
        }

        return $item->created_at ? $item->created_at->format('Y-m-d') : 'N/A';
    }

    public function index()
    {
        return view('supplier.index');
    }

    /**
     * Show the form for importing suppliers (optional redirect; modal is on index).
     */
    public function importForm()
    {
        return redirect()->route('supplier.index')->with('open_import_modal', true);
    }

    /**
     * Import suppliers from Excel. Expected columns (first row = header):
     * Supplier Name (or Name), Means of Payment (or Mode of Payment, MOP),
     * Address (optional), Telephone (or Phone, optional).
     */
    public function import(Request $request, SupplierExcelSyncService $sync)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ], [
            'file.required' => 'Please select an Excel file.',
            'file.max' => 'The file may not be greater than 10 MB.',
        ]);

        $file = $request->file('file');
        $path = $file->getPathname();
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return redirect()->route('supplier.index')->with('error', 'File must be Excel (.xlsx or .xls).');
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return redirect()->route('supplier.index')->with('error', 'Could not read the file. Please ensure it is a valid Excel file.');
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (empty($rows)) {
            return redirect()->route('supplier.index')->with('error', 'The file is empty.');
        }

        $header = array_map(function ($v) {
            return is_string($v) ? trim($v) : (string) $v;
        }, $rows[0]);
        $headerLower = array_map(function ($v) {
            return strtolower((string) $v);
        }, $header);

        $col = function ($names) use ($headerLower) {
            foreach ((array) $names as $name) {
                $k = array_search(strtolower($name), $headerLower);
                if ($k !== false) {
                    return $k;
                }
            }
            return null;
        };

        $nameCol = $col(['supplier name', 'name', 'supplier']);
        $mopCol = $col(['means of payment', 'mode of payment', 'mop', 'payment mode']);
        $addressCol = $col(['address', 'alamat']);
        $phoneCol = $col(['telephone', 'phone', 'telepon', 'contact']);

        if ($nameCol === null) {
            return redirect()->route('supplier.index')->with('error', 'Excel must have a "Supplier Name" or "Name" column.');
        }
        if ($mopCol === null) {
            return redirect()->route('supplier.index')->with('error', 'Excel must have a "Means of Payment" or "Mode of Payment" column.');
        }

        $imported = 0;
        $skipped = 0;
        try {
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $nama = isset($row[$nameCol]) ? trim((string) $row[$nameCol]) : '';
                $mop = isset($row[$mopCol]) ? trim((string) $row[$mopCol]) : '';
                if ($nama === '' && $mop === '') {
                    continue;
                }
                if ($nama === '') {
                    $skipped++;
                    continue;
                }

                $alamat = $addressCol !== null && isset($row[$addressCol]) ? trim((string) $row[$addressCol]) : '';
                $telepon = $phoneCol !== null && isset($row[$phoneCol]) ? trim((string) $row[$phoneCol]) : '';
                // DB column telepon is NOT NULL; use empty string when missing
                if ($telepon === null || $telepon === '') {
                    $telepon = '';
                }

                Supplier::create([
                    'nama' => $nama,
                    'alamat' => $alamat,
                    'telepon' => $telepon,
                    'mop' => $sync->canonicalMop($mop),
                    'opening_balance' => 0,
                    'opening_balance_paid' => 0,
                ]);
                $imported++;
            }
        } catch (\Throwable $e) {
            return redirect()->route('supplier.index')->with('error', 'Import failed: ' . $e->getMessage());
        }

        $message = $imported . ' supplier(s) imported successfully.';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' row(s) skipped (missing name).';
        }
        return redirect()->route('supplier.index')->with('success', $message);
    }

    public function data()
    {
        $supplier = Supplier::orderBy('id_supplier', 'desc')->get();

        return datatables()
            ->of($supplier)
            ->addIndexColumn()
            ->addColumn('aksi', function ($supplier) {
                $u = auth()->user();
                $buttons = '<div class="btn-group">';
                if (!$u || $u->can_read) {
                    $buttons .= '<button type="button" onclick="viewConsignment(`'. route('supplier.consignment', $supplier->id_supplier) .'`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-eye"></i> View</button>';
                }
                if (!$u || $u->can_read) {
                    $buttons .= '<a href="'. route('supplier.withdrawal', $supplier->id_supplier) .'" class="btn btn-xs btn-warning btn-flat" title="Withdraw Items"><i class="fa fa-arrow-down"></i> Withdraw</a>';
                }
                if ($u && $u->can_update) {
                    $buttons .= '<button type="button" onclick="editForm(`'. route('supplier.update', $supplier->id_supplier) .'`)" class="btn btn-xs btn-primary btn-flat"><i class="fa fa-pencil"></i></button>';
                }
                if ($u && $u->can_delete) {
                    $buttons .= '<button type="button" onclick="deleteData(`'. route('supplier.destroy', $supplier->id_supplier) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>';
                }
                $buttons .= '</div>';
                return $buttons;
            })
            ->rawColumns(['aksi'])
            ->make(true);
    }

    /**
     * Export supplier list to Excel.
     */
    public function exportToExcel()
    {
        $suppliers = Supplier::orderBy('id_supplier', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', '#');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Telephone');
        $sheet->setCellValue('D1', 'Address');
        $sheet->setCellValue('E1', 'Mode of Payment');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($suppliers as $index => $supplier) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $supplier->nama ?? '');
            $sheet->setCellValue('C' . $row, $supplier->telepon ?? '');
            $sheet->setCellValue('D' . $row, $supplier->alamat ?? '');
            $sheet->setCellValue('E' . $row, $supplier->mop ?? '');
            $row++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'supplier_list_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
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
    public function store(Request $request, SupplierExcelSyncService $sync)
    {
        $data = $request->all();
        // Ensure opening_balance defaults to 0 if not provided
        if (!isset($data['opening_balance']) || $data['opening_balance'] === null || $data['opening_balance'] === '') {
            $data['opening_balance'] = 0;
        }
        if (isset($data['mop'])) {
            $data['mop'] = $sync->canonicalMop($data['mop']);
        }
        $supplier = Supplier::create($data);

        return response()->json('Data saved successfully', 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $supplier = Supplier::find($id);

        return response()->json($supplier);
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
    // visit "codeastro" for more projects!
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, SupplierExcelSyncService $sync)
    {
        $data = $request->all();
        // Ensure opening_balance defaults to 0 if not provided
        if (!isset($data['opening_balance']) || $data['opening_balance'] === null || $data['opening_balance'] === '') {
            $data['opening_balance'] = 0;
        }
        if (isset($data['mop'])) {
            $data['mop'] = $sync->canonicalMop($data['mop']);
        }
        $supplier = Supplier::find($id)->update($data);

        return response()->json('Data saved successfully', 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $supplier = Supplier::find($id)->delete();

        return response(null, 204);
    }

    /**
     * Get supplier consignment details (paid and pending)
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function consignment($id, Request $request)
    {
        $supplier = Supplier::findOrFail($id);
        
        // Get filters from request
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $invoiceItemId = $request->input('invoice_item_id');
        $pendingOnly = $request->boolean('pending_only');
        
        // Build query for invoice items with filters (sale business date; see invoiceItemsForConsignmentQuery)
        $invoiceItemsQuery = $this->invoiceItemsForConsignmentQuery($supplier->id_supplier);

        if ($invoiceItemId) {
            $invoiceItemsQuery->where('invoice_items.id', $invoiceItemId);
        }

        if ($pendingOnly) {
            $invoiceItemsQuery->whereRaw('invoice_items.amount - COALESCE(invoice_items.amount_paid, 0) > 0');
        }

        $this->applyConsignmentSaleDateFilters($invoiceItemsQuery, $startDate, $endDate);
        
        $selectedItemData = null;
        if ($invoiceItemId) {
            $selectedItem = InvoiceItem::with(['produk.shop', 'invoice', 'supplier', 'penjualan'])
                ->whereHas('produk', function ($q) {
                    $q->whereRaw('COALESCE(produk.is_incomplete, 0) = 0');
                })
                ->where('supplier_id', $supplier->id_supplier)
                ->where('id', $invoiceItemId)
                ->first();
            
            if (!$selectedItem) {
                return response()->json([
                    'message' => 'Consignment item not found for this supplier.'
                ], 404);
            }
            
            $selectedAmount = floatval($selectedItem->amount ?? 0);
            $selectedPaid = floatval($selectedItem->amount_paid ?? 0);
            $selectedBalance = max($selectedAmount - $selectedPaid, 0);
            $selectedStatus = 'Not paid';
            if ($selectedPaid > 0 && $selectedBalance > 0) {
                $selectedStatus = 'Partially paid';
            } elseif ($selectedBalance <= 0 && $selectedAmount > 0) {
                $selectedStatus = 'Paid';
            }
            
            // Receipt number from linked penjualan
            $receiptNo = optional($selectedItem->penjualan)->receiptno ?? 'N/A';
            if ($receiptNo === 'N/A' && $selectedItem->penjualan_id) {
                $sale = Penjualan::find($selectedItem->penjualan_id);
                $receiptNo = $sale ? ($sale->receiptno ?? 'N/A') : 'N/A';
            }
            
            $selectedItemData = [
                'id' => $selectedItem->id,
                'supplier_name' => $selectedItem->supplier->nama ?? $supplier->nama,
                'shop_name' => optional($selectedItem->produk->shop)->shop_name ?? 'N/A',
                'invoice_number' => $receiptNo,
                'product_name' => $selectedItem->produk->nama_produk ?? 'N/A',
                'product_code' => $selectedItem->produk->kode_produk ?? 'N/A',
                'quantity' => $selectedItem->quantity ?? 0,
                'buying_price' => $selectedItem->produk->harga_beli ?? 0,
                'total_amount' => $selectedAmount,
                'amount_paid' => $selectedPaid,
                'balance' => $selectedBalance,
                'status' => $selectedStatus,
                'date' => optional($selectedItem->created_at)->format('Y-m-d'),
                'created_at' => optional($selectedItem->created_at)->format('Y-m-d'),
                'updated_at' => optional($selectedItem->updated_at)->format('Y-m-d'),
            ];
        }
        
        // Calculate totals based on ALL items (not filtered by pending_only) for accurate status
        $totalsQuery = $this->invoiceItemsForConsignmentQuery($supplier->id_supplier);
        $this->applyConsignmentSaleDateFilters($totalsQuery, $startDate, $endDate);
        
        // Calculate total pending consignment payment (from ALL items)
        $pendingResult = (clone $totalsQuery)
            ->select(DB::raw('COALESCE(SUM(invoice_items.amount - COALESCE(invoice_items.amount_paid, 0)), 0) as pending'))
            ->first();
        
        $pendingAmount = $pendingResult && isset($pendingResult->pending) ? floatval($pendingResult->pending) : 0;
        
        // Calculate total paid consignment payment (from ALL items)
        $paidResult = (clone $totalsQuery)
            ->select(DB::raw('COALESCE(SUM(COALESCE(invoice_items.amount_paid, 0)), 0) as paid'))
            ->first();
        
        $paidAmount = $paidResult && isset($paidResult->paid) ? floatval($paidResult->paid) : 0;
        
        // Supplier total = SUM(stock_out × buying_price). invoice_items.amount holds supplier payable (not selling price or discounted total).
        $totalResult = (clone $totalsQuery)
            ->select(DB::raw('COALESCE(SUM(invoice_items.amount), 0) as total'))
            ->first();
        
        $totalAmount = $totalResult && isset($totalResult->total) ? floatval($totalResult->total) : 0;
        
        // Base items query with relationships (include penjualan for receipt number)
        $baseItemsQuery = (clone $invoiceItemsQuery)
            ->with(['produk.shop', 'invoice', 'penjualan']);

        // Get ALL items with details (filtered by date if provided, but no payment status filter)
        $allItems = (clone $baseItemsQuery)
            ->orderBy('invoice_items.created_at', 'desc')
            ->get()
            ->map(function($item) {
                $amount = floatval($item->amount ?? 0);
                $amountPaid = floatval($item->amount_paid ?? 0);
                $balance = max($amount - $amountPaid, 0);
                $status = 'Partially paid';
                if ($amountPaid <= 0) {
                    $status = 'Not paid';
                } elseif ($balance <= 0 && $amount > 0) {
                    $status = 'Paid';
                }

                // Receipt number from linked penjualan (sale) when invoice item has penjualan_id
                $receiptNo = optional($item->penjualan)->receiptno ?? 'N/A';
                if ($receiptNo === 'N/A' && $item->penjualan_id) {
                    $sale = Penjualan::find($item->penjualan_id);
                    $receiptNo = $sale ? ($sale->receiptno ?? 'N/A') : 'N/A';
                }

                return [
                    'id' => $item->id,
                    'invoice_number' => $receiptNo,
                    'product_name' => $item->produk->nama_produk ?? 'N/A',
                    'product_code' => $item->produk->kode_produk ?? 'N/A',
                    'quantity' => $item->quantity ?? 0,
                    'unit_price' => $item->produk ? ($amount / max(($item->quantity ?? 1), 1)) : 0,
                    'total_amount' => $amount,
                    'amount_paid' => $amountPaid,
                    'balance' => $balance,
                    'invoice_id' => $item->invoice_id,
                    'status' => $status,
                    'payment_date' => $item->updated_at ? $item->updated_at->format('Y-m-d') : null,
                    'created_at' => $item->created_at ? $item->created_at->format('Y-m-d') : null,
                ];
            });
        
        // Separate paid and pending for backward compatibility and statistics
        $paidItems = $allItems->filter(function($item) {
            return floatval($item['amount_paid'] ?? 0) > 0;
        })->values();
        
        $pendingItems = $allItems->filter(function($item) {
            return floatval($item['balance'] ?? 0) > 0;
        })->values();
        
        return response()->json([
            'supplier' => [
                'id' => $supplier->id_supplier,
                'nama' => $supplier->nama,
                'telepon' => $supplier->telepon,
                'alamat' => $supplier->alamat,
            ],
            'selected_item' => $selectedItemData,
            'consignment' => [
                'total' => $totalAmount,
                'paid' => $paidAmount,
                'pending' => $pendingAmount,
                'status' => ($totalAmount > 0 && $pendingAmount == 0) ? 'Paid' : (($paidAmount <= 0) ? 'Not paid' : 'Partially paid'),
            ],
            'all_items' => $allItems->values(),
            'paid_items' => $paidItems,
            'pending_items' => $pendingItems,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }
    
    /**
     * Export paid consignment items to PDF
     */
    public function exportPaidItemsPdf($id, Request $request)
    {
        $supplier = Supplier::findOrFail($id);
        
        // Get date filters from request
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $pendingOnly = $request->boolean('pending_only');
        $allItems = $request->boolean('all_items');

        $itemsQuery = $this->invoiceItemsForConsignmentQuery($supplier->id_supplier);

        // all_items=1: every line in the date range (for supplier statements; avoids empty export when nothing paid yet)
        if ($allItems) {
            // no payment-status filter
        } elseif ($pendingOnly) {
            $itemsQuery->whereRaw('invoice_items.amount - COALESCE(invoice_items.amount_paid, 0) > 0');
        } else {
            $itemsQuery->where(DB::raw('COALESCE(invoice_items.amount_paid, 0)'), '>', 0);
        }

        $this->applyConsignmentSaleDateFilters($itemsQuery, $startDate, $endDate);

        // Get items and calculate balance and status correctly
        $items = $itemsQuery
            ->with(['produk.shop', 'invoice', 'penjualan'])
            ->orderBy('invoice_items.created_at', 'desc')
            ->get()
            ->map(function($item) {
                // Calculate balance from amount and amount_paid
                $amount = floatval($item->amount ?? 0);
                $amountPaid = floatval($item->amount_paid ?? 0);
                $balance = $amount - $amountPaid;
                
                // Calculate status based on balance
                $status = 'Unpaid';
                if ($balance <= 0.01) {
                    $status = 'Paid';
                } elseif ($amountPaid > 0) {
                    $status = 'Partially paid';
                }
                
                $receiptNo = $this->resolveReceiptNoForInvoiceItem($item);
                
                // Get shop name
                $shopName = optional($item->produk->shop)->shop_name ?? 'N/A';
                
                // Get buying price (unit price)
                $buyingPrice = $item->quantity > 0 ? ($amount / $item->quantity) : 0;
                
                // Add calculated fields
                $item->calculated_balance = $balance;
                $item->calculated_status = $status;
                $item->receipt_no = $receiptNo;
                $item->shop_name = $shopName;
                $item->buying_price = $buyingPrice;
                $item->sale_date_ymd = $this->resolveSaleDateYmdForInvoiceItem($item);
                
                return $item;
            });
        
        // Calculate totals
        $totalAmount = $items->sum('amount');
        $totalPaid = $items->sum('amount_paid');
        
        // Build filename with date range if filtered
        if ($allItems) {
            $filename = 'consignment_all_items_';
        } elseif ($pendingOnly) {
            $filename = 'consignment_pending_items_';
        } else {
            $filename = 'consignment_paid_items_';
        }
        $filename .= $supplier->id_supplier . '_' . date('Y-m-d');
        if ($startDate || $endDate) {
            $dateRange = ($startDate ? $startDate : 'all') . '_to_' . ($endDate ? $endDate : 'all');
            $filename .= '_' . $dateRange;
        }
        $filename .= '.pdf';
        
        $setting = Setting::first();
        
        // Use different view for pending items
        $viewName = ($pendingOnly || $allItems) ? 'supplier.pending_items_pdf' : 'supplier.paid_items_pdf';
        
        $pdf = PDF::loadView($viewName, [
            'supplier' => $supplier,
            'items' => $items,
            'paidItems' => $items, // Keep for backward compatibility
            'totalAmount' => $totalAmount,
            'totalPaid' => $totalPaid,
            'exportDate' => now()->format('Y-m-d H:i:s'),
            'setting' => $setting,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'pendingOnly' => $pendingOnly
        ]);
        
        $pdf->setPaper('a4', 'landscape');
        $pdf->getDomPDF()->set_option('isPhpEnabled', true);

        return $pdf->download($filename);
    }
    
    /**
     * Export paid consignment items to Excel
     */
    public function exportPaidItemsExcel($id, Request $request)
    {
        $supplier = Supplier::findOrFail($id);
        
        // Get date filters from request
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $pendingOnly = $request->boolean('pending_only');
        $allItems = $request->boolean('all_items');

        $paidItemsQuery = $this->invoiceItemsForConsignmentQuery($supplier->id_supplier);

        if ($allItems) {
            // no payment-status filter
        } elseif ($pendingOnly) {
            $paidItemsQuery->whereRaw('invoice_items.amount - COALESCE(invoice_items.amount_paid, 0) > 0');
        } else {
            $paidItemsQuery->where(DB::raw('COALESCE(invoice_items.amount_paid, 0)'), '>', 0);
        }

        $this->applyConsignmentSaleDateFilters($paidItemsQuery, $startDate, $endDate);

        // Get items and calculate balance and status correctly
        $paidItems = $paidItemsQuery
            ->with(['produk.shop', 'invoice', 'penjualan'])
            ->orderBy('invoice_items.created_at', 'desc')
            ->get()
            ->map(function($item) {
                // Calculate balance from amount and amount_paid
                $amount = floatval($item->amount ?? 0);
                $amountPaid = floatval($item->amount_paid ?? 0);
                $balance = $amount - $amountPaid;
                
                // Calculate status based on balance
                $status = 'Unpaid';
                if ($balance <= 0.01) {
                    $status = 'Paid';
                } elseif ($amountPaid > 0) {
                    $status = 'Partially paid';
                }
                
                // Add calculated balance and status to the item
                $item->calculated_balance = $balance;
                $item->calculated_status = $status;
                
                return $item;
            });
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers (Receipt / Shop / Sale date help reconcile mixed-supplier receipts)
        $sheet->setCellValue('A1', 'Product Code');
        $sheet->setCellValue('B1', 'Product Name');
        $sheet->setCellValue('C1', 'Receipt No');
        $sheet->setCellValue('D1', 'Shop');
        $sheet->setCellValue('E1', 'Sale Date');
        $sheet->setCellValue('F1', 'Quantity');
        $sheet->setCellValue('G1', 'Unit Price');
        $sheet->setCellValue('H1', 'Total Amount');
        $sheet->setCellValue('I1', 'Amount Paid');
        $sheet->setCellValue('J1', 'Balance');
        $sheet->setCellValue('K1', 'Status');
        $sheet->setCellValue('L1', 'Payment Date');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
        
        // Add data
        $row = 2;
        foreach ($paidItems as $item) {
            $balance = isset($item->calculated_balance) ? $item->calculated_balance : (floatval($item->amount ?? 0) - floatval($item->amount_paid ?? 0));
            $status = isset($item->calculated_status) ? $item->calculated_status : 'Partially paid';
            
            $sheet->setCellValue('A' . $row, $item->produk->kode_produk ?? 'N/A');
            $sheet->setCellValue('B' . $row, $item->produk->nama_produk ?? 'N/A');
            $sheet->setCellValue('C' . $row, $this->resolveReceiptNoForInvoiceItem($item));
            $sheet->setCellValue('D' . $row, optional($item->produk->shop)->shop_name ?? 'N/A');
            $sheet->setCellValue('E' . $row, $this->resolveSaleDateYmdForInvoiceItem($item));
            $sheet->setCellValue('F' . $row, $item->quantity ?? 0);
            $sheet->setCellValue('G' . $row, number_format(($item->amount ?? 0) / max(($item->quantity ?? 1), 1), 2));
            $sheet->setCellValue('H' . $row, number_format($item->amount ?? 0, 2));
            $sheet->setCellValue('I' . $row, number_format($item->amount_paid ?? 0, 2));
            $sheet->setCellValue('J' . $row, number_format($balance, 2));
            $sheet->setCellValue('K' . $row, $status);
            $sheet->setCellValue('L' . $row, $item->updated_at ? $item->updated_at->format('Y-m-d') : 'N/A');
            $row++;
        }
        
        // Totals (buying = sum of line amounts; matches consignment overview)
        $sheet->setCellValue('H' . $row, 'Total Amount (buying):');
        $sheet->setCellValue('I' . $row, number_format($paidItems->sum('amount'), 2));
        $sheet->getStyle('H' . $row . ':I' . $row)->applyFromArray(['font' => ['bold' => true]]);
        $row++;
        $sheet->setCellValue('H' . $row, 'Total Paid:');
        $sheet->setCellValue('I' . $row, number_format($paidItems->sum('amount_paid'), 2));
        $sheet->getStyle('H' . $row . ':I' . $row)->applyFromArray(['font' => ['bold' => true]]);
        
        // Auto-size columns
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Set title
        if ($allItems) {
            $sheet->setTitle('All Consignment Items');
        } else {
            $sheet->setTitle($pendingOnly ? 'Pending Consignment Items' : 'Paid Consignment Items');
        }
        
        // Build filename with date range if filtered
        if ($allItems) {
            $filename = 'consignment_all_items_';
        } elseif ($pendingOnly) {
            $filename = 'consignment_pending_items_';
        } else {
            $filename = 'consignment_paid_items_';
        }
        $filename .= $supplier->id_supplier . '_' . date('Y-m-d');
        if ($startDate || $endDate) {
            $dateRange = ($startDate ? $startDate : 'all') . '_to_' . ($endDate ? $endDate : 'all');
            $filename .= '_' . $dateRange;
        }
        $filename .= '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Show withdrawal index page listing all suppliers
     *
     * @return \Illuminate\Http\Response
     */
    public function withdrawalIndex()
    {
        return view('supplier.withdrawal-form', [
            'supplierContext' => null,
        ]);
    }

    /**
     * Show withdrawal form (legacy URL: optional supplier context for breadcrumb).
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function withdrawal($id)
    {
        $supplierContext = Supplier::findOrFail($id);

        return view('supplier.withdrawal-form', [
            'supplierContext' => $supplierContext,
        ]);
    }

    /**
     * Parse combined input like POS: "SHOPCODE-ITEMCODE" or "SHOPCODE ITEMCODE".
     *
     * @return array{shop: string, product: string}
     */
    protected function parseShopCodeAndProductForWithdrawal(string $input): array
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return ['shop' => '', 'product' => ''];
        }

        if (strpos($trimmed, '-') !== false) {
            $dashIndex = strpos($trimmed, '-');

            return [
                'shop' => trim(substr($trimmed, 0, $dashIndex)),
                'product' => trim(substr($trimmed, $dashIndex + 1)),
            ];
        }

        $parts = preg_split('/\s+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 2) {
            return [
                'shop' => $parts[0] ?? '',
                'product' => '',
            ];
        }

        return [
            'shop' => $parts[0],
            'product' => implode(' ', array_slice($parts, 1)),
        ];
    }

    /**
     * True when stored code equals the search or is the same base code with an auto suffix
     * (e.g. search MB31 matches MB31_1774004393_158 in the same shop).
     */
    protected function produkStoredCodeMatchesWithdrawalSearch(string $stored, string $search): bool
    {
        $stored = Str::lower(trim($stored));
        $search = Str::lower(trim($search));
        if ($stored === '' || $search === '') {
            return false;
        }
        if ($stored === $search) {
            return true;
        }
        if (! Str::startsWith($stored, $search.'_')) {
            return false;
        }
        $suffix = substr($stored, strlen($search) + 1);

        return (bool) preg_match('/^\d+(_\d+)*$/', $suffix);
    }

    protected function produkRowMatchesWithdrawalItemCode(Produk $produk, string $search): bool
    {
        foreach ([$produk->kode_produk, $produk->item_code] as $field) {
            if ($field !== null && $field !== '' && $this->produkStoredCodeMatchesWithdrawalSearch((string) $field, $search)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Produk>  $candidates
     */
    protected function resolveWithdrawalProductCandidates($candidates): ?Produk
    {
        if ($candidates->isEmpty()) {
            return null;
        }
        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $inStock = $candidates->filter(static fn ($p) => (int) ($p->stok ?? 0) > 0)->values();
        if ($inStock->count() === 1) {
            return $inStock->first();
        }

        $pool = $inStock->isNotEmpty() ? $inStock : $candidates;

        return $pool->sortByDesc(static fn ($p) => (int) $p->id_produk)->first();
    }

    /**
     * Resolve product for withdrawal: exact / base item code in shop (incl. auto suffix), then unique name match.
     */
    protected function findProdukForWithdrawal(Shop $shop, string $codeRaw): ?Produk
    {
        $codeTrim = trim($codeRaw);
        if ($codeTrim === '') {
            return null;
        }

        $codeLower = Str::lower($codeTrim);
        $prefixLike = $codeLower.'\_%';

        $base = Produk::query()->where('shop_id', $shop->id);

        $byCode = (clone $base)
            ->where(function ($q) use ($codeLower, $prefixLike) {
                $q->whereRaw('LOWER(TRIM(COALESCE(item_code, ""))) = ?', [$codeLower])
                    ->orWhereRaw('LOWER(TRIM(COALESCE(kode_produk, ""))) = ?', [$codeLower])
                    ->orWhereRaw('LOWER(TRIM(COALESCE(item_code, ""))) LIKE ?', [$prefixLike])
                    ->orWhereRaw('LOWER(TRIM(COALESCE(kode_produk, ""))) LIKE ?', [$prefixLike]);
            })
            ->with(['supplier', 'shop'])
            ->orderByDesc('id_produk')
            ->limit(25)
            ->get()
            ->filter(fn ($p) => $this->produkRowMatchesWithdrawalItemCode($p, $codeTrim))
            ->values();

        $resolved = $this->resolveWithdrawalProductCandidates($byCode);
        if ($resolved) {
            return $resolved;
        }

        // Same idea as POS product list: LIKE on name only; only accept if exactly one row
        $like = '%'.addcslashes($codeTrim, '%_\\').'%';
        $candidates = (clone $base)
            ->where('nama_produk', 'like', $like)
            ->with(['supplier', 'shop'])
            ->limit(5)
            ->get();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        return null;
    }

    /**
     * Lookup product by combined shop + item (same format as POS: SHOP-ITEM) for withdrawal line (AJAX).
     */
    public function lookupWithdrawalProduct(Request $request)
    {
        $combined = trim((string) $request->query('code', ''));

        if ($combined === '') {
            return response()->json([
                'found' => false,
                'message' => 'Enter shop code and item code (e.g. SHOP-ITEM)',
            ], 422);
        }

        $parsed = $this->parseShopCodeAndProductForWithdrawal($combined);
        $shopCode = $parsed['shop'];
        $code = $parsed['product'];

        if ($shopCode === '' || $code === '') {
            return response()->json([
                'found' => false,
                'message' => 'Use format: shop code then item code (e.g. SHOP-ITEM or SHOP ITEM)',
            ], 422);
        }

        $shop = Shop::query()
            ->whereRaw('LOWER(TRIM(shop_code)) = ?', [Str::lower(trim($shopCode))])
            ->first();

        // Allow numeric shop id if shop_code lookup failed (some users type internal id)
        if (! $shop && ctype_digit(preg_replace('/\s+/', '', $shopCode))) {
            $shop = Shop::query()->find((int) $shopCode);
        }

        if (! $shop) {
            return response()->json(['found' => false, 'message' => 'No shop found with this code']);
        }

        $produk = $this->findProdukForWithdrawal($shop, $code);

        if (! $produk) {
            return response()->json([
                'found' => false,
                'message' => 'No product found in this shop. Use SHOP-ITEM (shop code, dash, then the original item code — not the long suffix). Example: KILEO-MB31. The system matches codes like MB31_1774004393_158 when you type the base code MB31.',
            ]);
        }

        $supplierName = optional($produk->supplier)->nama;

        return response()->json([
            'found' => true,
            'produk' => [
                'id_produk' => (int) $produk->id_produk,
                'item_code' => $produk->kode_produk ?: ($produk->item_code ?? ''),
                'nama_produk' => $produk->nama_produk,
                'supplier_name' => $supplierName ?: '—',
                'merk' => $produk->merk ? (string) $produk->merk : '',
                'harga_beli' => (float) ($produk->harga_beli ?? 0),
                'harga_jual' => (float) ($produk->harga_jual ?? 0),
                'stok' => (int) ($produk->stok ?? 0),
                'id_supplier' => (int) $produk->id_supplier,
                'shop_code' => (string) ($produk->shop->shop_code ?? $shop->shop_code),
                'shop_name' => (string) ($produk->shop->shop_name ?? ''),
            ],
        ]);
    }

    /**
     * Get products for withdrawal (AJAX)
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getWithdrawalProducts($id)
    {
        $supplier = Supplier::findOrFail($id);
        
        $products = Produk::where('id_supplier', $supplier->id_supplier)
            ->where('stok', '>', 0)
            ->with('shop')
            ->orderBy('nama_produk')
            ->get()
            ->map(function($product) {
                return [
                    'id_produk' => $product->id_produk,
                    'kode_produk' => $product->kode_produk,
                    'nama_produk' => $product->nama_produk,
                    'stok' => $product->stok,
                    'harga_beli' => $product->harga_beli,
                    'shop_name' => $product->shop->shop_name ?? 'N/A',
                ];
            });
        
        return response()->json($products);
    }

    /**
     * Process withdrawal
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function processWithdrawal(Request $request)
    {
        $request->merge([
            'receipt_no' => trim((string) $request->input('receipt_no', '')),
        ]);

        $request->validate([
            'withdrawals' => 'required|array|min:1',
            'withdrawals.*.produk_id' => 'required|exists:produk,id_produk',
            'withdrawals.*.quantity' => 'required|integer|min:1',
            'withdrawal_date' => 'required|date',
            'receipt_no' => ['required', 'string', 'max:120'],
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ], [
            'receipt_no.required' => 'Receipt number is required before recording a withdrawal.',
        ]);

        try {
            DB::beginTransaction();

            $withdrawalDate = $request->withdrawal_date;
            $withdrawalNumber = SupplierWithdrawal::generateWithdrawalNumber();
            $userId = auth()->id();
            $receiptNo = $request->input('receipt_no');

            // Merge duplicate product lines (same produk_id) by summing quantity
            $merged = [];
            foreach ($request->withdrawals as $withdrawalData) {
                $pid = (int) $withdrawalData['produk_id'];
                $qty = (int) $withdrawalData['quantity'];
                if ($pid < 1 || $qty < 1) {
                    continue;
                }
                if (! isset($merged[$pid])) {
                    $merged[$pid] = 0;
                }
                $merged[$pid] += $qty;
            }

            if (empty($merged)) {
                throw new \Exception('No valid lines to withdraw.');
            }

            foreach ($merged as $produkId => $quantity) {
                $produk = Produk::lockForUpdate()->findOrFail($produkId);
                $supplier = Supplier::findOrFail($produk->id_supplier);

                if ($produk->stok < $quantity) {
                    throw new \Exception("Insufficient stock for {$produk->nama_produk}. Available: {$produk->stok}, Requested: {$quantity}");
                }

                $unitPrice = $produk->harga_beli ?? 0;
                $totalAmount = $unitPrice * $quantity;

                $rowData = [
                    'withdrawal_number' => $withdrawalNumber,
                    'supplier_id' => $supplier->id_supplier,
                    'produk_id' => $produk->id_produk,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_amount' => $totalAmount,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'user_id' => $userId,
                    'withdrawal_date' => $withdrawalDate,
                ];

                if (Schema::hasColumn('supplier_withdrawals', 'receipt_no')) {
                    $rowData['receipt_no'] = $receiptNo;
                }

                SupplierWithdrawal::create($rowData);

                $previousStock = $produk->stok;
                $newStock = $previousStock - $quantity;

                $produk->update([
                    'stok' => $newStock,
                ]);

                try {
                    $noteReceipt = $receiptNo ? " Receipt: {$receiptNo}." : '';
                    ProdukHistory::create([
                        'id_produk' => $produk->id_produk,
                        'previous_stock' => $previousStock,
                        'restock_amount' => -$quantity,
                        'current_stock' => $newStock,
                        'type' => 'withdrawal',
                        'notes' => "Supplier withdrawal: {$withdrawalNumber}.{$noteReceipt} Supplier: {$supplier->nama}",
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Could not log to produk_history: ' . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Withdrawal processed successfully',
                'withdrawal_number' => $withdrawalNumber,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error processing withdrawal: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error processing withdrawal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get withdrawal history for a supplier
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function withdrawalHistory($id)
    {
        $supplier = Supplier::findOrFail($id);
        
        $withdrawals = SupplierWithdrawal::where('supplier_id', $supplier->id_supplier)
            ->with(['produk', 'user'])
            ->orderBy('withdrawal_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json($withdrawals);
    }

    /**
     * Show all withdrawals page with filters
     *
     * @return \Illuminate\Http\Response
     */
    public function withdrawals(Request $request)
    {
        // Load suppliers for dropdown - Select2 will handle searching efficiently
        $suppliers = Supplier::select('id_supplier', 'nama')
            ->orderBy('nama')
            ->get();
        return view('supplier.withdrawals', compact('suppliers'));
    }

    /**
     * Get withdrawals data for DataTables
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function withdrawalsData(Request $request)
    {
        $query = SupplierWithdrawal::with(['supplier', 'produk', 'user'])
            ->orderByDesc('supplier_withdrawals.id');

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('withdrawal_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('withdrawal_date', '<=', $request->end_date);
        }

        return datatables()
            ->of($query)
            ->addIndexColumn()
            ->addColumn('withdrawal_number_display', function ($withdrawal) {
                return $withdrawal->withdrawal_number ?? 'N/A';
            })
            ->addColumn('receipt_no_display', function ($withdrawal) {
                $r = Schema::hasColumn('supplier_withdrawals', 'receipt_no')
                    ? ($withdrawal->receipt_no ?? '')
                    : '';

                return $r !== '' ? $r : '—';
            })
            ->addColumn('supplier_name', function ($withdrawal) {
                return $withdrawal->supplier->nama ?? 'N/A';
            })
            ->addColumn('product_name', function ($withdrawal) {
                return $withdrawal->produk->nama_produk ?? 'N/A';
            })
            ->addColumn('product_code', function ($withdrawal) {
                return $withdrawal->produk->kode_produk ?? 'N/A';
            })
            ->addColumn('withdrawal_date_formatted', function ($withdrawal) {
                if (! $withdrawal->withdrawal_date) {
                    return 'N/A';
                }

                return $withdrawal->withdrawal_date instanceof \Carbon\Carbon
                    ? $withdrawal->withdrawal_date->format('d/m/Y')
                    : date('d/m/Y', strtotime($withdrawal->withdrawal_date));
            })
            ->addColumn('user_name', function ($withdrawal) {
                return $withdrawal->user->name ?? 'N/A';
            })
            ->addColumn('unit_price_formatted', function ($withdrawal) {
                return number_format($withdrawal->unit_price ?? 0, 2);
            })
            ->addColumn('total_amount_formatted', function ($withdrawal) {
                return number_format($withdrawal->total_amount ?? 0, 2);
            })
            ->addColumn('remaining_stock', function ($withdrawal) {
                // Show current stock as remaining stock (stock in shop now)
                return $withdrawal->produk->stok ?? 0;
            })
            ->addColumn('action', function ($withdrawal) {
                $editUrl = route('supplier.withdrawals.edit', $withdrawal->id);
                $undoUrl = route('supplier.withdrawals.undo-batch', $withdrawal->id);
                $batchLabel = $withdrawal->withdrawal_number ?? 'N/A';
                $confirmMsg = 'Undo the entire withdrawal batch '.$batchLabel.'? All product lines in this batch will be removed and stock will be restored.';
                $edit = '<a href="'.$editUrl.'" class="btn btn-xs btn-primary btn-flat" title="Correct this withdrawal line"><i class="fa fa-edit"></i> Edit</a>';
                $undoForm = '<form method="POST" action="'.$undoUrl.'" style="display:inline;margin-left:4px" onsubmit="return confirm('.json_encode($confirmMsg).');">'
                    .'<input type="hidden" name="_token" value="'.e(csrf_token()).'">'
                    .'<button type="submit" class="btn btn-xs btn-danger btn-flat" title="Undo whole withdrawal (every line in this batch)"><i class="fa fa-undo"></i> Undo batch</button></form>';

                return $edit.$undoForm;
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    /**
     * Edit a single supplier withdrawal line (correct quantity, date, receipt, etc.).
     */
    public function withdrawalLineEdit(SupplierWithdrawal $withdrawal)
    {
        $withdrawal->load(['supplier', 'produk']);
        $hasReceiptNo = Schema::hasColumn('supplier_withdrawals', 'receipt_no');

        return view('supplier.withdrawal_edit', compact('withdrawal', 'hasReceiptNo'));
    }

    /**
     * Update a single withdrawal line and adjust product stock by quantity delta.
     */
    public function withdrawalLineUpdate(Request $request, SupplierWithdrawal $withdrawal)
    {
        $rules = [
            'quantity' => 'required|integer|min:1',
            'withdrawal_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
        if (Schema::hasColumn('supplier_withdrawals', 'receipt_no')) {
            $rules['receipt_no'] = ['required', 'string', 'max:120'];
        }
        $request->validate($rules, [
            'receipt_no.required' => 'Receipt number is required.',
        ]);

        try {
            DB::beginTransaction();

            $withdrawal = SupplierWithdrawal::lockForUpdate()->findOrFail($withdrawal->id);
            $produk = Produk::lockForUpdate()->findOrFail($withdrawal->produk_id);

            $oldQty = (int) $withdrawal->quantity;
            $newQty = (int) $request->input('quantity');
            $currentStock = (int) $produk->stok;

            // After "undoing" this line's withdrawal, stock would be current + oldQty; new qty must not exceed that.
            $maxWithdrawable = $currentStock + $oldQty;
            if ($newQty > $maxWithdrawable) {
                throw new \Exception("Insufficient stock for {$produk->nama_produk}. After correcting this line, at most {$maxWithdrawable} can be withdrawn (current stock {$currentStock}, originally withdrew {$oldQty}).");
            }

            $newStock = $currentStock + $oldQty - $newQty;
            $unitPrice = (float) ($produk->harga_beli ?? 0);
            $totalAmount = round($unitPrice * $newQty, 2);

            $produk->update(['stok' => $newStock]);

            $withdrawal->quantity = $newQty;
            $withdrawal->unit_price = $unitPrice;
            $withdrawal->total_amount = $totalAmount;
            $withdrawal->withdrawal_date = $request->input('withdrawal_date');
            $withdrawal->reason = $request->input('reason');
            $withdrawal->notes = $request->input('notes');
            if (Schema::hasColumn('supplier_withdrawals', 'receipt_no')) {
                $withdrawal->receipt_no = trim((string) $request->input('receipt_no', ''));
            }
            $withdrawal->save();

            try {
                $delta = $newQty - $oldQty;
                $noteReceipt = $withdrawal->receipt_no ? " Receipt: {$withdrawal->receipt_no}." : '';
                ProdukHistory::create([
                    'id_produk' => $produk->id_produk,
                    'previous_stock' => $currentStock,
                    'restock_amount' => -$delta,
                    'current_stock' => $newStock,
                    'type' => 'withdrawal_edit',
                    'notes' => "Supplier withdrawal line edited ({$withdrawal->withdrawal_number}). Qty {$oldQty} → {$newQty}.{$noteReceipt}",
                ]);
            } catch (\Exception $e) {
                \Log::warning('Could not log withdrawal edit to produk_history: '.$e->getMessage());
            }

            DB::commit();

            return redirect()
                ->route('supplier.withdrawals')
                ->with('success', 'Withdrawal line updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('withdrawalLineUpdate: '.$e->getMessage());

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reverse an entire withdrawal batch (all rows sharing withdrawal_number):
     * restore stock for each line, delete rows, log produk_history.
     */
    public function withdrawalBatchUndo(Request $request, SupplierWithdrawal $withdrawal)
    {
        $batchNumber = (string) ($withdrawal->withdrawal_number ?? '');
        if ($batchNumber === '') {
            return redirect()
                ->route('supplier.withdrawals')
                ->withErrors(['error' => 'Invalid withdrawal batch.']);
        }

        try {
            DB::beginTransaction();

            $lines = SupplierWithdrawal::query()
                ->with('supplier')
                ->where('withdrawal_number', $batchNumber)
                ->orderBy('produk_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw new \Exception('Withdrawal batch not found or already removed.');
            }

            foreach ($lines as $line) {
                $produk = Produk::lockForUpdate()->findOrFail($line->produk_id);
                $qty = (int) $line->quantity;
                $previousStock = (int) $produk->stok;
                $newStock = $previousStock + $qty;

                $produk->update(['stok' => $newStock]);

                $supplierName = $line->supplier->nama ?? '';
                $noteReceipt = '';
                if (Schema::hasColumn('supplier_withdrawals', 'receipt_no') && ! empty($line->receipt_no)) {
                    $noteReceipt = " Receipt: {$line->receipt_no}.";
                }

                try {
                    ProdukHistory::create([
                        'id_produk' => $produk->id_produk,
                        'previous_stock' => $previousStock,
                        'restock_amount' => $qty,
                        'current_stock' => $newStock,
                        'type' => 'withdrawal_undo',
                        'notes' => "Supplier withdrawal batch reversed: {$batchNumber}.{$noteReceipt}"
                            .($supplierName !== '' ? " Supplier: {$supplierName}." : ''),
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Could not log withdrawal_undo to produk_history: '.$e->getMessage());
                }

                $line->delete();
            }

            DB::commit();

            $n = $lines->count();

            $lineWord = $n === 1 ? 'line' : 'lines';

            return redirect()
                ->route('supplier.withdrawals')
                ->with('success', "Withdrawal batch {$batchNumber} was undone ({$n} {$lineWord}). Stock has been restored.");
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('withdrawalBatchUndo: '.$e->getMessage());

            return redirect()
                ->route('supplier.withdrawals')
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Export withdrawals to PDF
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function exportWithdrawalsPdf(Request $request)
    {
        $query = SupplierWithdrawal::with(['supplier', 'produk', 'user'])
            ->orderByDesc('supplier_withdrawals.id');

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($startDate) {
            $query->whereDate('withdrawal_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('withdrawal_date', '<=', $endDate);
        }

        $withdrawals = $query->get();
        
        // Calculate remaining stock for each withdrawal
        // Remaining stock = current stock (since withdrawal already reduced it)
        // Stock before withdrawal = current stock + withdrawn quantity
        foreach ($withdrawals as $withdrawal) {
            if ($withdrawal->produk) {
                $currentStock = $withdrawal->produk->stok ?? 0;
                $withdrawnQty = $withdrawal->quantity ?? 0;
                $withdrawal->stock_before = $currentStock + $withdrawnQty;
                $withdrawal->stock_after = $currentStock;
            } else {
                $withdrawal->stock_before = 0;
                $withdrawal->stock_after = 0;
            }
        }
        
        $supplier = $request->filled('supplier_id') ? Supplier::find($request->supplier_id) : null;
        $setting = Setting::first();

        // Calculate totals
        $totalQuantity = $withdrawals->sum('quantity');
        $totalQuantityBefore = $withdrawals->sum('stock_before');
        $totalQuantityAfter = $withdrawals->sum('stock_after');
        $totalAmount = $withdrawals->sum('total_amount');

        // Build filename
        $filename = 'supplier_withdrawals_' . date('Y-m-d');
        if ($startDate || $endDate) {
            $dateRange = ($startDate ? $startDate : 'all') . '_to_' . ($endDate ? $endDate : 'all');
            $filename .= '_' . $dateRange;
        }
        if ($supplier) {
            $filename .= '_' . $supplier->id_supplier;
        }
        $filename .= '.pdf';

        $pdf = PDF::loadView('supplier.withdrawals_pdf', [
            'withdrawals' => $withdrawals,
            'supplier' => $supplier,
            'setting' => $setting,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'totalQuantity' => $totalQuantity,
            'totalQuantityBefore' => $totalQuantityBefore,
            'totalQuantityAfter' => $totalQuantityAfter,
            'totalAmount' => $totalAmount,
            'exportDate' => now()->format('Y-m-d H:i:s'),
        ]);

        $pdf->setPaper('a4', 'landscape');
        return $pdf->download($filename);
    }

    /**
     * Compare uploaded supplier Excel to database (same columns as import + optional Supplier ID).
     */
    public function excelSyncPreview(Request $request, SupplierExcelSyncService $sync)
    {
        if (! $this->userCanSupplierStructuralChange()) {
            return response()->json(['message' => 'You do not have permission to run supplier updates.'], 403);
        }
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls'], true)) {
            return response()->json(['message' => 'File must be Excel (.xlsx or .xls).'], 422);
        }

        @set_time_limit(300);

        try {
            $preview = $sync->buildPreview($file->getPathname());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('excelSyncPreview: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if (str_contains($e->getMessage(), 'Maximum execution time')) {
                return response()->json(['message' => 'Comparison took too long. Try a smaller file or contact support.'], 422);
            }

            return response()->json(['message' => 'Could not read the file.'], 422);
        }

        return response()->json($preview);
    }

    /**
     * Apply selected supplier row updates from Excel compare (no merge).
     */
    public function excelSyncApply(Request $request, SupplierExcelSyncService $sync)
    {
        if (! $this->userCanSupplierStructuralChange()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.id_supplier' => 'required|integer|exists:supplier,id_supplier',
            'rows.*.nama' => 'required|string|max:255',
            'rows.*.mop' => 'nullable|string|max:100',
            'rows.*.alamat' => 'nullable|string|max:500',
            'rows.*.telepon' => 'nullable|string|max:100',
            'source_file' => 'nullable|string|max:255',
        ]);

        $updated = 0;
        $auditEntries = [];
        $sourceFile = $request->input('source_file');

        DB::beginTransaction();
        try {
            foreach ($request->input('rows') as $row) {
                $s = Supplier::query()->find((int) $row['id_supplier']);
                if (! $s) {
                    continue;
                }

                $newNama = trim((string) $row['nama']);
                $newMop = $sync->canonicalMop($row['mop'] ?? '');
                $newAlamat = trim((string) ($row['alamat'] ?? ''));
                $newTelepon = trim((string) ($row['telepon'] ?? ''));

                $changes = $sync->diffFields($s, $newNama, $newMop, $newAlamat, $newTelepon);
                if ($changes === []) {
                    continue;
                }

                $auditEntries[] = [
                    'id_supplier' => (int) $s->id_supplier,
                    'changes' => $changes,
                ];

                $s->nama = $newNama;
                $s->mop = $newMop;
                $s->alamat = $newAlamat;
                $s->telepon = $newTelepon;
                $s->save();
                $updated++;
            }

            if ($auditEntries !== []) {
                $sync->logExcelSyncChanges($auditEntries, auth()->id(), $sourceFile);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('excelSyncApply: '.$e->getMessage());

            return response()->json(['message' => 'Save failed: '.$e->getMessage()], 500);
        }

        return response()->json([
            'message' => $updated.' supplier(s) updated. '.$updated.' audit log entries recorded.',
            'updated' => $updated,
            'audit_logged' => count($auditEntries),
        ]);
    }

    /**
     * Possible duplicate suppliers (same name / same phone) for merge UI.
     */
    public function duplicateSupplierGroups(SupplierExcelSyncService $sync)
    {
        if (! $this->userCanSupplierStructuralChange()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $groups = $sync->findPossibleDuplicateGroups();
        $nameGroups = array_map(fn ($g) => $sync->enrichDuplicateGroup($g), $groups['name_groups']);
        $phoneGroups = array_map(fn ($g) => $sync->enrichDuplicateGroup($g), $groups['phone_groups']);

        return response()->json([
            'name_groups' => $nameGroups,
            'phone_groups' => $phoneGroups,
            'total_groups' => count($nameGroups) + count($phoneGroups),
        ]);
    }

    /**
     * Select2 JSON for supplier picker (merge modal).
     */
    public function selectOptions(Request $request)
    {
        if (! $this->userCanSupplierStructuralChange()) {
            return response()->json(['results' => []], 403);
        }

        $q = trim((string) $request->query('q', ''));
        $query = Supplier::query()->orderBy('nama');
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            if (ctype_digit($q)) {
                $query->where(function ($sub) use ($like, $q) {
                    $sub->where('nama', 'like', $like)
                        ->orWhere('id_supplier', (int) $q);
                });
            } else {
                $query->where('nama', 'like', $like);
            }
        }

        $rows = $query->limit(50)->get(['id_supplier', 'nama', 'mop', 'telepon']);
        $results = $rows->map(function ($s) {
            $mop = trim((string) ($s->mop ?? ''));
            $text = '#'.$s->id_supplier.' — '.($s->nama ?? '');
            if ($mop !== '') {
                $text .= ' ('.$mop.')';
            }

            return ['id' => (int) $s->id_supplier, 'text' => $text];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Preview product counts before merging suppliers.
     */
    public function supplierMergePreview(Request $request, SupplierExcelSyncService $sync)
    {
        if (! $this->userCanSupplierStructuralChange()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $request->validate([
            'keep_id' => 'required|integer|exists:supplier,id_supplier',
            'merge_ids' => 'required|array|min:1',
            'merge_ids.*' => 'integer|distinct|exists:supplier,id_supplier',
        ]);

        $keepId = (int) $request->input('keep_id');
        $mergeIds = array_map('intval', $request->input('merge_ids', []));

        return response()->json($sync->mergePreview($keepId, $mergeIds));
    }

    /**
     * Merge one or more suppliers into another; repoints stock, purchases, invoices, payments, etc.
     */
    public function supplierMerge(Request $request, SupplierExcelSyncService $sync)
    {
        if (! $this->userCanSupplierStructuralChange()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $request->validate([
            'keep_id' => 'required|integer|exists:supplier,id_supplier',
            'merge_ids' => 'required|array|min:1',
            'merge_ids.*' => 'integer|distinct|exists:supplier,id_supplier',
            'final_nama' => 'nullable|string|max:255',
            'final_mop' => 'nullable|string|max:100',
            'final_alamat' => 'nullable|string|max:500',
            'final_telepon' => 'nullable|string|max:100',
        ]);

        $keepId = (int) $request->input('keep_id');
        $mergeIds = array_map('intval', $request->input('merge_ids', []));
        if (in_array($keepId, $mergeIds, true)) {
            return response()->json(['message' => 'Cannot merge the keeper supplier into itself.'], 422);
        }

        try {
            $sync->mergeSuppliers(
                $keepId,
                $mergeIds,
                $request->input('final_nama'),
                $request->input('final_mop'),
                $request->input('final_alamat'),
                $request->input('final_telepon')
            );
        } catch (\Throwable $e) {
            \Log::error('supplierMerge: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Merge failed: '.$e->getMessage()], 500);
        }

        return response()->json(['message' => 'Suppliers merged. All linked stock, purchases, consignment, and payments now use the kept supplier.']);
    }
}
