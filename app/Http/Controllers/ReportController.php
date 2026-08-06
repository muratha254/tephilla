<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Shop;
use App\Models\Produk;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade as PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\Penjualan;
use App\Models\Pembelian;
use App\Models\Account;
use App\Models\DailyCash;
use App\Models\Setting;
use App\Models\PenjualanDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    public function supplierPayments(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        $supplierId = $request->get('supplier_id');

        $query = Payment::with('supplier')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $payments = $query->get();
        $suppliers = Supplier::orderBy('nama')->get();

        return view('reports.supplier_payments', compact('payments', 'suppliers', 'startDate', 'endDate', 'supplierId'));
    }

    public function exportSupplierPayments(Request $request)
    {
        try {
            $startDate = $request->get('start_date', date('Y-m-01'));
            $endDate = $request->get('end_date', date('Y-m-d'));
            $supplierId = $request->get('supplier_id');

            $query = Payment::with('supplier')
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'desc');

            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            $payments = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $sheet->setCellValue('A1', 'Supplier Payment Report');
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Date range
            $sheet->setCellValue('A2', 'Period: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)));
            $sheet->mergeCells('A2:F2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Headers
            $headers = ['Date', 'Reference #', 'Supplier', 'Amount', 'Payment Method', 'Notes'];
            foreach (range('A', 'F') as $key => $column) {
                $sheet->setCellValue($column . '4', $headers[$key]);
                $sheet->getStyle($column . '4')->getFont()->setBold(true);
                $sheet->getStyle($column . '4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }

            // Data
            $row = 5;
            $totalAmount = 0;
            foreach ($payments as $payment) {
                $sheet->setCellValue('A' . $row, date('Y-m-d', strtotime($payment->date)));
                $sheet->setCellValue('B' . $row, $payment->reference_number);
                $sheet->setCellValue('C' . $row, $payment->supplier->nama ?? '');
                $sheet->setCellValue('D' . $row, number_format($payment->amount, 2));
                $sheet->setCellValue('E' . $row, $payment->payment_method);
                $sheet->setCellValue('F' . $row, $payment->notes);

                // Apply borders
                foreach (range('A', 'F') as $column) {
                    $sheet->getStyle($column . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                $totalAmount += $payment->amount;
                $row++;
            }

            // Total row
            $sheet->setCellValue('A' . $row, 'Total');
            $sheet->mergeCells('A' . $row . ':C' . $row);
            $sheet->setCellValue('D' . $row, number_format($totalAmount, 2));
            $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':F' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Auto-size columns
            foreach (range('A', 'F') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create the Excel file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'supplier_payments_' . date('Y-m-d_His') . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            return back()->with('error', 'Error generating report: ' . $e->getMessage());
        }
    }

    public function exportSupplierPaymentsPdf(Request $request)
    {
        try {
            $startDate = $request->get('start_date', date('Y-m-01'));
            $endDate = $request->get('end_date', date('Y-m-d'));
            $supplierId = $request->get('supplier_id');

            $query = Payment::with('supplier')
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'desc');

            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            $payments = $query->get();
            $supplier = $supplierId ? Supplier::find($supplierId) : null;
            $setting = Setting::first();

            $pdf = PDF::loadView('reports.supplier_payments_pdf', [
                'payments' => $payments,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'supplier' => $supplier,
                'setting' => $setting
            ]);

            return $pdf->download('supplier_payments_' . date('Y-m-d_His') . '.pdf');

        } catch (\Exception $e) {
            return back()->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    public function productReport()
    {
        $shops = Shop::pluck('shop_name', 'id');
        $suppliers = Supplier::pluck('nama', 'id_supplier');
        return view('reports.product', compact('shops', 'suppliers'));
    }

    /**
     * Sales by Shop (view)
     */
    public function salesByShop(Request $request)
    {
        $start = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $end = $request->get('end_date', now()->format('Y-m-d'));

        $data = $this->salesByShopData($start, $end);

        return view('reports.sales_by_shop', [
            'start' => $start,
            'end' => $end,
            'rows' => $data['rows'],
            'totalAmount' => $data['totalAmount'],
            'totalQty' => $data['totalQty'],
        ]);
    }

    /**
     * Sales by Shop PDF export
     */
    public function exportSalesByShopPdf(Request $request)
    {
        $start = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $end = $request->get('end_date', now()->format('Y-m-d'));

        $data = $this->salesByShopData($start, $end);
        $setting = Setting::first();

        $pdf = PDF::loadView('reports.sales_by_shop_pdf', [
            'start' => $start,
            'end' => $end,
            'rows' => $data['rows'],
            'totalAmount' => $data['totalAmount'],
            'totalQty' => $data['totalQty'],
            'setting' => $setting,
        ]);

        $pdf->setPaper('a4', 'portrait');
        return $pdf->download('sales_by_shop_' . $start . '_to_' . $end . '.pdf');
    }

    /**
     * Helper: fetch sales grouped by shop
     */
    private function salesByShopData(string $start, string $end): array
    {
        $query = PenjualanDetail::select(
                'shops.id as shop_id',
                'shops.shop_name',
                DB::raw('SUM(penjualan_detail.subtotal) as total_amount'),
                DB::raw('SUM(penjualan_detail.jumlah) as total_qty')
            )
            ->join('penjualan', 'penjualan.id_penjualan', '=', 'penjualan_detail.id_penjualan')
            ->join('produk', 'produk.id_produk', '=', 'penjualan_detail.id_produk')
            ->leftJoin('shops', 'shops.id', '=', 'produk.shop_id')
            ->whereBetween(DB::raw('DATE(penjualan.created_at)'), [$start, $end]);

        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('penjualan.status', 'completed');
        }

        $rows = $query
            ->groupBy('shops.id', 'shops.shop_name')
            ->orderBy('shops.shop_name')
            ->get()
            ->map(function ($row) {
                return [
                    'shop_name' => $row->shop_name ?? 'Unassigned',
                    'total_amount' => $row->total_amount ?? 0,
                    'total_qty' => $row->total_qty ?? 0,
                ];
            })
            ->toArray();

        $totalAmount = array_sum(array_column($rows, 'total_amount'));
        $totalQty = array_sum(array_column($rows, 'total_qty'));

        return compact('rows', 'totalAmount', 'totalQty');
    }

    public function productReportData(Request $request)
    {
        $query = Produk::with(['shop', 'supplier'])
            ->select('produk.*');

        if ($request->shop_id) {
            $query->where('shop_id', $request->shop_id);
        }
        if ($request->supplier_id) {
            $query->where('id_supplier', $request->supplier_id);
        }
        if ($request->item_code) {
            $query->where('item_code', 'like', '%' . $request->item_code . '%');
        }

        return datatables()
            ->of($query)
            ->addIndexColumn()
            ->addColumn('shop_name', function ($produk) {
                return $produk->shop->shop_name ?? '-';
            })
            ->addColumn('supplier_name', function ($produk) {
                return $produk->supplier->nama ?? '-';
            })
            ->make(true);
    }

    public function productReportPdf(Request $request)
    {
        $query = Produk::with(['shop', 'supplier']);

        if ($request->shop_id) {
            $query->where('shop_id', $request->shop_id);
        }
        if ($request->supplier_id) {
            $query->where('id_supplier', $request->supplier_id);
        }
        if ($request->item_code) {
            $query->where('item_code', 'like', '%' . $request->item_code . '%');
        }

        $products = $query->get();
        $setting = Setting::first();

        $pdf = PDF::loadView('reports.product_pdf', compact('products', 'setting'));
        return $pdf->download('product_report.pdf');
    }

    public function index()
    {
        return view('report.index');
    }

    public function getData(Request $request)
    {
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        
        $query = Penjualan::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(total_item) as total_items'),
            DB::raw('SUM(total_harga) as total_amount'),
            DB::raw('SUM(diskon) as total_discount')
        )
        ->whereBetween('created_at', [$startDate, $endDate])
        ->where('total_harga', '>', 0);
        
        // Only include completed sales if status column exists
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        $report = $query->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return response()->json($report);
    }

    public function getDailySales()
    {
        $today = Carbon::today();
        
        $query = Penjualan::select(
            DB::raw('DATE_FORMAT(created_at, "%H:00") as hour'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(total_item) as total_items'),
            DB::raw('SUM(total_harga) as total_amount'),
            DB::raw('SUM(diskon) as total_discount')
        )
        ->whereDate('created_at', $today)
        ->where('total_harga', '>', 0);
        
        // Only include completed sales if status column exists
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        $sales = $query->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json($sales);
    }

    public function getWeeklySales()
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $query = Penjualan::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(total_item) as total_items'),
            DB::raw('SUM(total_harga) as total_amount'),
            DB::raw('SUM(diskon) as total_discount')
        )
        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
        ->where('total_harga', '>', 0);
        
        // Only include completed sales if status column exists
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        $sales = $query->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($sales);
    }

    public function getMonthlySales()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $query = Penjualan::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(total_item) as total_items'),
            DB::raw('SUM(total_harga) as total_amount'),
            DB::raw('SUM(diskon) as total_discount')
        )
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->where('total_harga', '>', 0);
        
        // Only include completed sales if status column exists
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        $sales = $query->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($sales);
    }

    public function getAnnualSales()
    {
        $startOfYear = Carbon::now()->startOfYear();
        $endOfYear = Carbon::now()->endOfYear();

        $query = Penjualan::select(
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(total_item) as total_items'),
            DB::raw('SUM(total_harga) as total_amount'),
            DB::raw('SUM(diskon) as total_discount')
        )
        ->whereBetween('created_at', [$startOfYear, $endOfYear])
        ->where('total_harga', '>', 0);
        
        // Only include completed sales if status column exists
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        $sales = $query->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json($sales);
    }

    public function printReport(Request $request)
    {
        $type = $request->type;
        $data = [];
        $period = '';
        
        switch($type) {
            case 'daily':
                $data = $this->getDailySalesData();
                $period = 'Daily Report - ' . Carbon::now()->format('Y-m-d');
                break;
            case 'weekly':
                $data = $this->getWeeklySalesData();
                $period = 'Weekly Report - ' . Carbon::now()->startOfWeek()->format('Y-m-d') . ' to ' . Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'monthly':
                $data = $this->getMonthlySalesData();
                $period = 'Monthly Report - ' . Carbon::now()->format('F Y');
                break;
            case 'annual':
                $data = $this->getAnnualSalesData();
                $period = 'Annual Report - ' . Carbon::now()->format('Y');
                break;
            case 'custom':
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();
                
                $query = Penjualan::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total_transactions'),
                    DB::raw('SUM(total_item) as total_items'),
                    DB::raw('SUM(total_harga) as total_amount'),
                    DB::raw('SUM(diskon) as total_discount')
                )
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('total_harga', '>', 0);
                
                if (Schema::hasColumn('penjualan', 'status')) {
                    $query->where('status', 'completed');
                }
                
                $data = $query->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->get();
                
                $period = 'Custom Date Range Report - ' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d');
                break;
        }

        // Calculate summary
        $summary = [
            'total_sales' => collect($data)->sum('total_amount'),
            'total_transactions' => collect($data)->sum('total_transactions'),
            'total_items' => collect($data)->sum('total_items'),
            'total_discount' => collect($data)->sum('total_discount'),
        ];
        $summary['average_sale'] = $summary['total_transactions'] > 0 ? 
            $summary['total_sales'] / $summary['total_transactions'] : 0;

        // Format data for printing
        $formattedData = collect($data)->map(function($item) use ($type) {
            $period = match($type) {
                'daily' => $item->hour,
                'annual' => Carbon::create()->month($item->month)->format('F'),
                'custom' => Carbon::parse($item->date)->format('Y-m-d'),
                default => Carbon::parse($item->date)->format('Y-m-d'),
            };

            return [
                'period' => $period,
                'total_transactions' => $item->total_transactions,
                'total_items' => $item->total_items ?? 0,
                'total_amount' => $item->total_amount,
                'total_discount' => $item->total_discount ?? 0,
                'average_sale' => $item->total_transactions > 0 ? 
                    $item->total_amount / $item->total_transactions : 0,
            ];
        })->toArray();

        $setting = Setting::first();
        $pdf = PDF::loadView('report.print', [
            'data' => $formattedData,
            'summary' => $summary,
            'period' => $period,
            'setting' => $setting,
        ]);

        return $pdf->stream('sales_report.pdf');
    }

    private function getDailySalesData()
    {
        $today = Carbon::today();
        $query = Penjualan::select(
            DB::raw('DATE_FORMAT(created_at, "%H:00") as hour'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(total_item) as total_items'),
            DB::raw('SUM(total_harga) as total_amount'),
            DB::raw('SUM(diskon) as total_discount')
        )
        ->whereDate('created_at', $today)
        ->where('total_harga', '>', 0);
        
        // Only include completed sales if status column exists
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        return $query->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    private function getWeeklySalesData()
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $query = Penjualan::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(total_item) as total_items'),
            DB::raw('SUM(total_harga) as total_amount'),
            DB::raw('SUM(diskon) as total_discount')
        )
        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
        ->where('total_harga', '>', 0);
        
        // Only include completed sales if status column exists
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        return $query->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getMonthlySalesData()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $query = Penjualan::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(total_item) as total_items'),
            DB::raw('SUM(total_harga) as total_amount'),
            DB::raw('SUM(diskon) as total_discount')
        )
        ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
        ->where('total_harga', '>', 0);
        
        // Only include completed sales if status column exists
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        return $query->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getAnnualSalesData()
    {
        $startOfYear = Carbon::now()->startOfYear();
        $endOfYear = Carbon::now()->endOfYear();
        $query = Penjualan::select(
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(total_item) as total_items'),
            DB::raw('SUM(total_harga) as total_amount'),
            DB::raw('SUM(diskon) as total_discount')
        )
        ->whereBetween('created_at', [$startOfYear, $endOfYear])
        ->where('total_harga', '>', 0);
        
        // Only include completed sales if status column exists
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        return $query->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    public function restockReport(Request $request)
    {
        return view('reports.restock_report');
    }

    public function restockReportData(Request $request)
    {
        $dateRange = $request->date_range;
        $dates = $dateRange ? explode(' - ', $dateRange) : [date('Y-m-01'), date('Y-m-d')];
        $startDate = $dates[0];
        $endDate = $dates[1] ?? $dates[0];

        $query = DB::table('pembelian_detail')
            ->join('pembelian', 'pembelian_detail.id_pembelian', '=', 'pembelian.id_pembelian')
            ->join('produk', 'pembelian_detail.id_produk', '=', 'produk.id_produk')
            ->join('supplier', 'pembelian.id_supplier', '=', 'supplier.id_supplier')
            ->select(
                'pembelian.purchasedate2 as date',
                'produk.kode_produk as product_code',
                'produk.nama_produk as product_name',
                'supplier.nama as supplier_name',
                'pembelian_detail.jumlah as quantity_added',
                'pembelian_detail.harga_beli as unit_price',
                DB::raw('pembelian_detail.jumlah * pembelian_detail.harga_beli as total_value')
            )
            ->whereBetween('pembelian.purchasedate2', [$startDate, $endDate])
            ->orderBy('pembelian.purchasedate2', 'desc');

        return datatables()
            ->of($query)
            ->addIndexColumn()
            ->make(true);
    }

    public function exportRestockReportPdf(Request $request)
    {
        $dateRange = $request->date_range;
        $dates = $dateRange ? explode(' - ', $dateRange) : [date('Y-m-01'), date('Y-m-d')];
        $startDate = $dates[0];
        $endDate = $dates[1] ?? $dates[0];

        $restocks = DB::table('pembelian_detail')
            ->join('pembelian', 'pembelian_detail.id_pembelian', '=', 'pembelian.id_pembelian')
            ->join('produk', 'pembelian_detail.id_produk', '=', 'produk.id_produk')
            ->join('supplier', 'pembelian.id_supplier', '=', 'supplier.id_supplier')
            ->select(
                'pembelian.purchasedate2 as date',
                'produk.kode_produk as product_code',
                'produk.nama_produk as product_name',
                'supplier.nama as supplier_name',
                'pembelian_detail.jumlah as quantity_added',
                'pembelian_detail.harga_beli as unit_price',
                DB::raw('pembelian_detail.jumlah * pembelian_detail.harga_beli as total_value')
            )
            ->whereBetween('pembelian.purchasedate2', [$startDate, $endDate])
            ->orderBy('pembelian.purchasedate2', 'desc')
            ->get();

        $totalValue = $restocks->sum('total_value');
        $setting = Setting::first();

        $pdf = PDF::loadView('reports.restock_report_pdf', compact('restocks', 'startDate', 'endDate', 'totalValue', 'setting'));
        return $pdf->stream('restock-report-'. date('Y-m-d') .'.pdf');
    }

    public function kraTaxReport(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        
        return view('reports.kra_tax', compact('startDate', 'endDate'));
    }

    public function kraTaxReportData(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        
        $query = Penjualan::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('total_harga', '>', 0);
        
        // Only include completed sales
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }
        
        $query->orderBy('created_at', 'desc');
        
        return datatables()
            ->of($query)
            ->addIndexColumn()
            ->addColumn('date', function ($penjualan) {
                return date('Y-m-d', strtotime($penjualan->created_at));
            })
            ->addColumn('receipt_number', function ($penjualan) {
                return $penjualan->receiptno ?? 'N/A';
            })
            ->addColumn('total_sale', function ($penjualan) {
                return 'Ksh ' . format_uang($penjualan->total_harga ?? 0);
            })
            ->addColumn('tax_amount', function ($penjualan) {
                // Get tax from database if column exists, otherwise calculate
                if (Schema::hasColumn('penjualan', 'tax') && $penjualan->tax) {
                    $tax = floatval($penjualan->tax);
                } else {
                    // Calculate 16% VAT inclusive
                    $total = floatval($penjualan->bayar ?? $penjualan->total_harga ?? 0);
                    $tax = round($total * (16 / 116), 2);
                }
                return 'Ksh ' . format_uang($tax);
            })
            ->addColumn('subtotal_before_tax', function ($penjualan) {
                // Get tax from database if column exists, otherwise calculate
                if (Schema::hasColumn('penjualan', 'tax') && $penjualan->tax) {
                    $tax = floatval($penjualan->tax);
                } else {
                    $total = floatval($penjualan->bayar ?? $penjualan->total_harga ?? 0);
                    $tax = round($total * (16 / 116), 2);
                }
                $subtotal = floatval($penjualan->bayar ?? $penjualan->total_harga ?? 0) - $tax;
                return 'Ksh ' . format_uang($subtotal);
            })
            ->addColumn('tax_rate', function ($penjualan) {
                return '16%';
            })
            ->rawColumns(['total_sale', 'tax_amount', 'subtotal_before_tax'])
            ->make(true);
    }

    public function exportKraTaxPdf(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        
        $sales = Penjualan::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('total_harga', '>', 0);
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $sales->where('status', 'completed');
        }
        
        $sales = $sales->orderBy('created_at', 'desc')->get();
        
        // Calculate totals
        $totalSales = 0;
        $totalTax = 0;
        $totalSubtotal = 0;
        
        foreach ($sales as $sale) {
            $total = floatval($sale->bayar ?? $sale->total_harga ?? 0);
            $totalSales += $total;
            
            if (Schema::hasColumn('penjualan', 'tax') && $sale->tax) {
                $tax = floatval($sale->tax);
            } else {
                $tax = round($total * (16 / 116), 2);
            }
            
            $totalTax += $tax;
            $totalSubtotal += ($total - $tax);
        }
        
        $setting = Setting::first();
        $pdf = PDF::loadView('reports.kra_tax_pdf', compact('sales', 'startDate', 'endDate', 'totalSales', 'totalTax', 'totalSubtotal', 'setting'));
        return $pdf->download('kra_tax_report_' . date('Y-m-d', strtotime($startDate)) . '_to_' . date('Y-m-d', strtotime($endDate)) . '.pdf');
    }

    public function exportKraTaxExcel(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        
        $sales = Penjualan::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('total_harga', '>', 0);
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $sales->where('status', 'completed');
        }
        
        $sales = $sales->orderBy('created_at', 'desc')->get();
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header
        $sheet->setCellValue('A1', 'KRA Tax Report - VAT Returns');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Period
        $sheet->setCellValue('A2', 'Period: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)));
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Headers
        $headers = ['Date', 'Receipt Number', 'Subtotal (Before Tax)', 'Tax Amount (16% VAT)', 'Total Sale Amount', 'Tax Rate'];
        foreach (range('A', 'F') as $key => $column) {
            if (isset($headers[$key])) {
                $sheet->setCellValue($column . '4', $headers[$key]);
                $sheet->getStyle($column . '4')->getFont()->setBold(true);
                $sheet->getStyle($column . '4')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');
                $sheet->getStyle($column . '4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
        }
        
        // Data
        $row = 5;
        $totalSales = 0;
        $totalTax = 0;
        $totalSubtotal = 0;
        
        foreach ($sales as $sale) {
            $total = floatval($sale->bayar ?? $sale->total_harga ?? 0);
            
            if (Schema::hasColumn('penjualan', 'tax') && $sale->tax) {
                $tax = floatval($sale->tax);
            } else {
                $tax = round($total * (16 / 116), 2);
            }
            
            $subtotal = $total - $tax;
            
            $sheet->setCellValue('A' . $row, date('Y-m-d', strtotime($sale->created_at)));
            $sheet->setCellValue('B' . $row, $sale->receiptno ?? 'N/A');
            $sheet->setCellValue('C' . $row, number_format($subtotal, 2));
            $sheet->setCellValue('D' . $row, number_format($tax, 2));
            $sheet->setCellValue('E' . $row, number_format($total, 2));
            $sheet->setCellValue('F' . $row, '16%');
            
            // Apply borders
            foreach (range('A', 'F') as $column) {
                $sheet->getStyle($column . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
            
            $totalSales += $total;
            $totalTax += $tax;
            $totalSubtotal += $subtotal;
            $row++;
        }
        
        // Total row
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->setCellValue('C' . $row, number_format($totalSubtotal, 2));
        $sheet->setCellValue('D' . $row, number_format($totalTax, 2));
        $sheet->setCellValue('E' . $row, number_format($totalSales, 2));
        $sheet->setCellValue('F' . $row, '16%');
        
        $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':F' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD3D3D3');
        $sheet->getStyle('A' . $row . ':F' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Auto-size columns
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $fileName = 'kra_tax_report_' . date('Y-m-d', strtotime($startDate)) . '_to_' . date('Y-m-d', strtotime($endDate)) . '.xlsx';
        
        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Display daily closing report
     */
    public function dailyClosingReport(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));
        $selectedDate = Carbon::parse($date);
        
        // Get opening cash balance
        $dailyCash = DailyCash::whereDate('date', $selectedDate)->first();
        $openingCash = $dailyCash ? $dailyCash->opening_cash : 0;
        
        // Get sales by payment method for the selected date
        $salesQuery = Penjualan::whereDate('created_at', $selectedDate);
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesQuery->where('status', 'completed');
        }
        
        $sales = $salesQuery->get();
        
        // Group sales by payment method
        $salesByMethod = [
            'Cash' => 0,
            'Mpesa' => 0,
            'Card' => 0,
            'Other' => 0
        ];
        
        $totalSales = 0;
        foreach ($sales as $sale) {
            $paymentMethod = $sale->payment_method ?? 'Cash';
            $amount = $sale->bayar ?? 0;
            
            if (isset($salesByMethod[$paymentMethod])) {
                $salesByMethod[$paymentMethod] += $amount;
            } else {
                $salesByMethod['Other'] += $amount;
            }
            $totalSales += $amount;
        }
        
        // Get purchases for the selected date
        $purchases = Pembelian::whereDate('purchasedate2', $selectedDate)
            ->orWhereDate('created_at', $selectedDate)
            ->get();
        $totalPurchases = $purchases->sum('total_harga');
        
        // Get supplier payments for the selected date
        $supplierPaymentsQuery = Payment::whereDate('date', $selectedDate);
        
        // Check if payment_date column exists and add it to the query
        if (Schema::hasColumn('payments', 'payment_date')) {
            $supplierPaymentsQuery->orWhereDate('payment_date', $selectedDate);
        }
        
        $supplierPayments = $supplierPaymentsQuery->get();
        
        // Group payments by payment method
        $paymentsByMethod = [
            'Cash' => 0,
            'Mpesa' => 0,
            'Card' => 0,
            'Cheque' => 0,
            'Other' => 0
        ];
        
        $totalSupplierPayments = 0;
        foreach ($supplierPayments as $payment) {
            $paymentMethod = $payment->payment_method ?? 'Cash';
            $amount = $payment->amount ?? 0;
            
            if (isset($paymentsByMethod[$paymentMethod])) {
                $paymentsByMethod[$paymentMethod] += $amount;
            } else {
                $paymentsByMethod['Other'] += $amount;
            }
            $totalSupplierPayments += $amount;
        }
        
        // Get account balances
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $accountBalances = [];
        foreach ($accounts as $account) {
            $accountBalances[$account->name] = $account->balance;
        }
        
        // Calculate net cash position
        $netCash = $openingCash + $salesByMethod['Cash'] - $paymentsByMethod['Cash'];
        
        // Calculate closing balance (opening + total sales - total purchases - supplier payments)
        $closingBalance = $openingCash + $totalSales - $totalPurchases - $totalSupplierPayments;
        
        return view('reports.daily_closing', compact(
            'date',
            'selectedDate',
            'openingCash',
            'salesByMethod',
            'totalSales',
            'purchases',
            'totalPurchases',
            'supplierPayments',
            'paymentsByMethod',
            'totalSupplierPayments',
            'accounts',
            'accountBalances',
            'netCash',
            'closingBalance',
            'dailyCash'
        ));
    }

    /**
     * Export daily closing report as PDF
     */
    public function exportDailyClosingReportPdf(Request $request)
    {
        $date = $request->get('date', date('Y-m-d'));
        $selectedDate = Carbon::parse($date);
        
        // Get opening cash balance
        $dailyCash = DailyCash::whereDate('date', $selectedDate)->first();
        $openingCash = $dailyCash ? $dailyCash->opening_cash : 0;
        
        // Get sales by payment method for the selected date
        $salesQuery = Penjualan::whereDate('created_at', $selectedDate);
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesQuery->where('status', 'completed');
        }
        
        $sales = $salesQuery->get();
        
        // Group sales by payment method
        $salesByMethod = [
            'Cash' => 0,
            'Mpesa' => 0,
            'Card' => 0,
            'Other' => 0
        ];
        
        $totalSales = 0;
        foreach ($sales as $sale) {
            $paymentMethod = $sale->payment_method ?? 'Cash';
            $amount = $sale->bayar ?? 0;
            
            if (isset($salesByMethod[$paymentMethod])) {
                $salesByMethod[$paymentMethod] += $amount;
            } else {
                $salesByMethod['Other'] += $amount;
            }
            $totalSales += $amount;
        }
        
        // Get purchases for the selected date
        $purchases = Pembelian::whereDate('purchasedate2', $selectedDate)
            ->orWhereDate('created_at', $selectedDate)
            ->get();
        $totalPurchases = $purchases->sum('total_harga');
        
        // Get supplier payments for the selected date
        $supplierPaymentsQuery = Payment::whereDate('date', $selectedDate);
        
        // Check if payment_date column exists and add it to the query
        if (Schema::hasColumn('payments', 'payment_date')) {
            $supplierPaymentsQuery->orWhereDate('payment_date', $selectedDate);
        }
        
        $supplierPayments = $supplierPaymentsQuery->get();
        
        // Group payments by payment method
        $paymentsByMethod = [
            'Cash' => 0,
            'Mpesa' => 0,
            'Card' => 0,
            'Cheque' => 0,
            'Other' => 0
        ];
        
        $totalSupplierPayments = 0;
        foreach ($supplierPayments as $payment) {
            $paymentMethod = $payment->payment_method ?? 'Cash';
            $amount = $payment->amount ?? 0;
            
            if (isset($paymentsByMethod[$paymentMethod])) {
                $paymentsByMethod[$paymentMethod] += $amount;
            } else {
                $paymentsByMethod['Other'] += $amount;
            }
            $totalSupplierPayments += $amount;
        }
        
        // Get account balances
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $accountBalances = [];
        foreach ($accounts as $account) {
            $accountBalances[$account->name] = $account->balance;
        }
        
        // Calculate net cash position
        $netCash = $openingCash + $salesByMethod['Cash'] - $paymentsByMethod['Cash'];
        
        // Calculate closing balance
        $closingBalance = $openingCash + $totalSales - $totalPurchases - $totalSupplierPayments;
        
        $setting = Setting::first();
        $pdf = PDF::loadView('reports.daily_closing_pdf', compact(
            'date',
            'selectedDate',
            'openingCash',
            'salesByMethod',
            'totalSales',
            'purchases',
            'totalPurchases',
            'supplierPayments',
            'paymentsByMethod',
            'totalSupplierPayments',
            'accounts',
            'accountBalances',
            'netCash',
            'closingBalance',
            'dailyCash',
            'setting'
        ));
        
        $pdf->setPaper('a4', 'portrait');
        return $pdf->stream('daily_closing_report_' . $date . '.pdf');
    }
}
