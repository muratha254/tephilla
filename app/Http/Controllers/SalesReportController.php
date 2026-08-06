<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\DailyCash;
use App\Models\Member;
use App\Models\Account;
use Illuminate\Http\Request;
use Carbon\Carbon;
use PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

class SalesReportController extends Controller
{
    /**
     * Get date range based on filter type
     */
    private function getDateRange($filterType, $startDate = null, $endDate = null)
    {
        $today = Carbon::today();
        
        switch ($filterType) {
            case 'daily':
                return [
                    'start' => $today->copy(),
                    'end' => $today->copy()
                ];
            
            case 'monthly':
                return [
                    'start' => $today->copy()->startOfMonth(),
                    'end' => $today->copy()->endOfMonth()
                ];
            
            case 'yearly':
                return [
                    'start' => $today->copy()->startOfYear(),
                    'end' => $today->copy()->endOfYear()
                ];
            
            case 'custom':
                return [
                    'start' => $startDate ? Carbon::parse($startDate) : $today->copy()->startOfMonth(),
                    'end' => $endDate ? Carbon::parse($endDate) : $today->copy()
                ];
            
            default:
                return [
                    'start' => $today->copy()->startOfMonth(),
                    'end' => $today->copy()
                ];
        }
    }

    /**
     * Sales Summary View
     */
    public function summary(Request $request)
    {
        $filterType = $request->get('filter_type', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        $dateRange = $this->getDateRange($filterType, $startDate, $endDate);
        $start = $dateRange['start'];
        $end = $dateRange['end'];
        
        // Get all dates in range
        $dates = [];
        $current = $start->copy();
        while ($current <= $end) {
            $dates[] = $current->copy();
            $current->addDay();
        }
        
        // Get daily cash records
        $dailyCashRecords = DailyCash::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(function($item) {
                return $item->date->format('Y-m-d');
            });
        
        // Get sales grouped by date
        $salesQuery = Penjualan::whereBetween(DB::raw('DATE(created_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesQuery->where('status', 'completed');
        }
        
        $sales = $salesQuery->get();
        
        $salesByDate = $sales->groupBy(function($sale) {
            return Carbon::parse($sale->created_at)->format('Y-m-d');
        });
        
        // Build summary data
        $summaryData = [];
        $totalCashOnHand = 0;
        $totalSales = 0;
        $totalNetSales = 0;
        $totalDiscount = 0;
        $totalGrossSales = 0;
        
        foreach ($dates as $date) {
            $dateStr = $date->format('Y-m-d');
            $dailyCash = $dailyCashRecords->get($dateStr);
            $daySales = $salesByDate->get($dateStr, collect());
            
            $cashOnHand = $dailyCash ? $dailyCash->opening_cash : 0;
            $dayTotalSales = $daySales->sum('bayar');
            $dayDiscount = $daySales->sum(fn ($s) => $s->getSaleDiscountAmount());
            $dayGrossSales = $dayTotalSales + $dayDiscount;
            $dayNetSales = $dayTotalSales - $cashOnHand;
            
            $summaryData[] = [
                'date' => $dateStr,
                'date_formatted' => $date->format('F d, Y'),
                'cash_on_hand' => $cashOnHand,
                'gross_sales' => $dayGrossSales,
                'total_discount' => $dayDiscount,
                'total_sales' => $dayTotalSales,
                'net_sales' => $dayNetSales,
                'transaction_count' => $daySales->count()
            ];
            
            $totalCashOnHand += $cashOnHand;
            $totalSales += $dayTotalSales;
            $totalNetSales += $dayNetSales;
            $totalDiscount += $dayDiscount;
            $totalGrossSales += $dayGrossSales;
        }
        
        // Get account balances for Cash, Mpesa, and Card
        $cashAccount = Account::getByName('Cash');
        $mpesaAccount = Account::getByName('Mpesa');
        $cardAccount = Account::getByName('Card');
        
        $accountBalances = [
            'Cash' => $cashAccount ? $cashAccount->balance : 0,
            'Mpesa' => $mpesaAccount ? $mpesaAccount->balance : 0,
            'Card' => $cardAccount ? $cardAccount->balance : 0,
        ];
        
        return view('sales_report.summary', compact('summaryData', 'filterType', 'startDate', 'endDate', 'start', 'end', 'totalCashOnHand', 'totalGrossSales', 'totalDiscount', 'totalSales', 'totalNetSales', 'accountBalances'));
    }

    /**
     * Sales Detailed View
     */
    public function detailed(Request $request)
    {
        $filterType = $request->get('filter_type', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        $dateRange = $this->getDateRange($filterType, $startDate, $endDate);
        $start = $dateRange['start'];
        $end = $dateRange['end'];
        
        // Get daily cash records
        $dailyCashRecords = DailyCash::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(function($item) {
                return $item->date->format('Y-m-d');
            });
        
        // Get sales
        $salesQuery = Penjualan::with(['member', 'user'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('created_at', 'asc');
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesQuery->where('status', 'completed');
        }
        
        $sales = $salesQuery->get();
        
        // Build detailed data with daily totals
        $detailedData = [];
        $currentDate = null;
        $dayTotal = 0;
        $dayDiscount = 0;
        $dayGrossSales = 0;
        $dayCashOnHand = 0;
        $dayNetSales = 0;
        $firstTransactionOfDay = true;
        
        foreach ($sales as $sale) {
            $saleDate = Carbon::parse($sale->created_at)->format('Y-m-d');
            $saleDiscount = $sale->getSaleDiscountAmount();
            
            // If new day, add daily totals for previous day
            if ($currentDate !== null && $currentDate !== $saleDate) {
                $detailedData[] = [
                    'type' => 'daily_total',
                    'date' => $currentDate,
                    'cash_on_hand' => $dayCashOnHand,
                    'gross_sales' => $dayGrossSales,
                    'total_discount' => $dayDiscount,
                    'total_sales' => $dayTotal,
                    'net_sales' => $dayNetSales
                ];
                $dayTotal = 0;
                $dayDiscount = 0;
                $dayGrossSales = 0;
                $dayCashOnHand = 0;
                $dayNetSales = 0;
                $firstTransactionOfDay = true;
            }
            
            // If new day, get cash on hand and add header
            if ($currentDate !== $saleDate) {
                $dailyCash = $dailyCashRecords->get($saleDate);
                $dayCashOnHand = $dailyCash ? $dailyCash->opening_cash : 0;
                $currentDate = $saleDate;
                $firstTransactionOfDay = true;
            }
            
            // Add sale transaction
            $detailedData[] = [
                'type' => 'transaction',
                'date' => $saleDate,
                'date_formatted' => Carbon::parse($sale->created_at)->format('F d, Y'),
                'receipt_no' => $sale->receiptno,
                'customer' => $sale->member->nama ?? 'Walk-in',
                'payment_method' => $sale->payment_method ?? 'Cash',
                'gross_amount' => $sale->bayar + $saleDiscount,
                'discount' => $saleDiscount,
                'discount_percentage' => $sale->getDiscountPercentage(),
                'amount' => $sale->bayar,
                'cashier' => $sale->user->name ?? 'N/A',
                'cash_on_hand' => $firstTransactionOfDay ? $dayCashOnHand : null,
                'is_first_of_day' => $firstTransactionOfDay
            ];
            
            $firstTransactionOfDay = false;
            $dayTotal += $sale->bayar;
            $dayDiscount += $saleDiscount;
            $dayGrossSales += $sale->bayar + $saleDiscount;
            $dayNetSales = $dayTotal - $dayCashOnHand;
        }
        
        // Add final day totals
        if ($currentDate !== null) {
            $detailedData[] = [
                'type' => 'daily_total',
                'date' => $currentDate,
                'cash_on_hand' => $dayCashOnHand,
                'gross_sales' => $dayGrossSales,
                'total_discount' => $dayDiscount,
                'total_sales' => $dayTotal,
                'net_sales' => $dayNetSales
            ];
        }
        
        // Calculate overall totals
        $totalCashOnHand = $dailyCashRecords->sum('opening_cash');
        $totalSales = $sales->sum('bayar');
        $totalDiscount = $sales->sum(fn ($s) => $s->getSaleDiscountAmount());
        $totalGrossSales = $totalSales + $totalDiscount;
        $totalNetSales = $totalSales - $totalCashOnHand;
        
        return view('sales_report.detailed', compact('detailedData', 'filterType', 'startDate', 'endDate', 'start', 'end', 'totalCashOnHand', 'totalGrossSales', 'totalDiscount', 'totalSales', 'totalNetSales'));
    }

    /**
     * Export Summary PDF
     */
    public function exportSummaryPdf(Request $request)
    {
        $filterType = $request->get('filter_type', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        $dateRange = $this->getDateRange($filterType, $startDate, $endDate);
        $start = $dateRange['start'];
        $end = $dateRange['end'];
        
        // Get data (same logic as summary method)
        $dates = [];
        $current = $start->copy();
        while ($current <= $end) {
            $dates[] = $current->copy();
            $current->addDay();
        }
        
        $dailyCashRecords = DailyCash::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(function($item) {
                return $item->date->format('Y-m-d');
            });
        
        $salesQuery = Penjualan::whereBetween(DB::raw('DATE(created_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesQuery->where('status', 'completed');
        }
        
        $sales = $salesQuery->get();
        $salesByDate = $sales->groupBy(function($sale) {
            return Carbon::parse($sale->created_at)->format('Y-m-d');
        });
        
        $summaryData = [];
        $totalCashOnHand = 0;
        $totalSales = 0;
        $totalNetSales = 0;
        $totalDiscount = 0;
        $totalGrossSales = 0;
        $totalTax = 0;
        $totalNetAfterTax = 0;
        
        foreach ($dates as $date) {
            $dateStr = $date->format('Y-m-d');
            $dailyCash = $dailyCashRecords->get($dateStr);
            $daySales = $salesByDate->get($dateStr, collect());
            
            $cashOnHand = $dailyCash ? $dailyCash->opening_cash : 0;
            $dayTotalSales = $daySales->sum('bayar');
            $dayDiscount = $daySales->sum(fn ($s) => $s->getSaleDiscountAmount());
            $dayGrossSales = $dayTotalSales + $dayDiscount;
            $dayTax = $daySales->sum('tax');
            $dayNetSales = $dayTotalSales - $cashOnHand;
            $dayNetAfterTax = $dayTotalSales - $dayTax;
            
            $summaryData[] = [
                'date' => $dateStr,
                'date_formatted' => $date->format('F d, Y'),
                'cash_on_hand' => $cashOnHand,
                'gross_sales' => $dayGrossSales,
                'total_discount' => $dayDiscount,
                'total_sales' => $dayTotalSales,
                'net_sales' => $dayNetSales,
                'tax' => $dayTax,
                'net_after_tax' => $dayNetAfterTax,
                'transaction_count' => $daySales->count()
            ];
            
            $totalCashOnHand += $cashOnHand;
            $totalSales += $dayTotalSales;
            $totalNetSales += $dayNetSales;
            $totalDiscount += $dayDiscount;
            $totalGrossSales += $dayGrossSales;
            $totalTax += $dayTax;
            $totalNetAfterTax += $dayNetAfterTax;
        }
        
        $setting = Setting::first();
        $pdf = PDF::loadView('sales_report.summary_pdf', compact(
            'summaryData',
            'filterType',
            'start',
            'end',
            'totalCashOnHand',
            'totalGrossSales',
            'totalDiscount',
            'totalSales',
            'totalNetSales',
            'totalTax',
            'totalNetAfterTax',
            'setting'
        ));
        return $pdf->download('sales_summary_' . $start->format('Y-m-d') . '_to_' . $end->format('Y-m-d') . '.pdf');
    }

    /**
     * Export Detailed PDF
     */
    public function exportDetailedPdf(Request $request)
    {
        $filterType = $request->get('filter_type', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        $dateRange = $this->getDateRange($filterType, $startDate, $endDate);
        $start = $dateRange['start'];
        $end = $dateRange['end'];
        
        // Get data (same logic as detailed method)
        $dailyCashRecords = DailyCash::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(function($item) {
                return $item->date->format('Y-m-d');
            });
        
        $salesQuery = Penjualan::with(['member', 'user'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('created_at', 'asc');
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesQuery->where('status', 'completed');
        }
        
        $sales = $salesQuery->get();
        
        $detailedData = [];
        $currentDate = null;
        $dayTotal = 0;
        $dayDiscount = 0;
        $dayGrossSales = 0;
        $dayCashOnHand = 0;
        $dayNetSales = 0;
        $firstTransactionOfDay = true;
        
        foreach ($sales as $sale) {
            $saleDate = Carbon::parse($sale->created_at)->format('Y-m-d');
            $saleDiscount = $sale->getSaleDiscountAmount();
            
            if ($currentDate !== null && $currentDate !== $saleDate) {
                $detailedData[] = [
                    'type' => 'daily_total',
                    'date' => $currentDate,
                    'cash_on_hand' => $dayCashOnHand,
                    'gross_sales' => $dayGrossSales,
                    'total_discount' => $dayDiscount,
                    'total_sales' => $dayTotal,
                    'net_sales' => $dayNetSales
                ];
                $dayTotal = 0;
                $dayDiscount = 0;
                $dayGrossSales = 0;
                $dayCashOnHand = 0;
                $dayNetSales = 0;
                $firstTransactionOfDay = true;
            }
            
            if ($currentDate !== $saleDate) {
                $dailyCash = $dailyCashRecords->get($saleDate);
                $dayCashOnHand = $dailyCash ? $dailyCash->opening_cash : 0;
                $currentDate = $saleDate;
                $firstTransactionOfDay = true;
            }
            
            $detailedData[] = [
                'type' => 'transaction',
                'date' => $saleDate,
                'date_formatted' => Carbon::parse($sale->created_at)->format('F d, Y'),
                'receipt_no' => $sale->receiptno,
                'customer' => $sale->member->nama ?? 'Walk-in',
                'payment_method' => $sale->payment_method ?? 'Cash',
                'gross_amount' => $sale->bayar + $saleDiscount,
                'discount' => $saleDiscount,
                'discount_percentage' => $sale->getDiscountPercentage(),
                'amount' => $sale->bayar,
                'cashier' => $sale->user->name ?? 'N/A',
                'cash_on_hand' => $firstTransactionOfDay ? $dayCashOnHand : null,
                'is_first_of_day' => $firstTransactionOfDay
            ];
            
            $firstTransactionOfDay = false;
            $dayTotal += $sale->bayar;
            $dayDiscount += $saleDiscount;
            $dayGrossSales += $sale->bayar + $saleDiscount;
            $dayNetSales = $dayTotal - $dayCashOnHand;
        }
        
        if ($currentDate !== null) {
            $detailedData[] = [
                'type' => 'daily_total',
                'date' => $currentDate,
                'cash_on_hand' => $dayCashOnHand,
                'gross_sales' => $dayGrossSales,
                'total_discount' => $dayDiscount,
                'total_sales' => $dayTotal,
                'net_sales' => $dayNetSales
            ];
        }
        
        $totalCashOnHand = $dailyCashRecords->sum('opening_cash');
        $totalSales = $sales->sum('bayar');
        $totalDiscount = $sales->sum(fn ($s) => $s->getSaleDiscountAmount());
        $totalGrossSales = $totalSales + $totalDiscount;
        $totalNetSales = $totalSales - $totalCashOnHand;
        
        $setting = Setting::first();
        $pdf = PDF::loadView('sales_report.detailed_pdf', compact('detailedData', 'filterType', 'start', 'end', 'totalCashOnHand', 'totalGrossSales', 'totalDiscount', 'totalSales', 'totalNetSales', 'setting'));
        return $pdf->download('sales_detailed_' . $start->format('Y-m-d') . '_to_' . $end->format('Y-m-d') . '.pdf');
    }

    /**
     * Export Summary Excel
     */
    public function exportSummaryExcel(Request $request)
    {
        $filterType = $request->get('filter_type', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        $dateRange = $this->getDateRange($filterType, $startDate, $endDate);
        $start = $dateRange['start'];
        $end = $dateRange['end'];
        
        // Get data (same logic as summary method)
        $dates = [];
        $current = $start->copy();
        while ($current <= $end) {
            $dates[] = $current->copy();
            $current->addDay();
        }
        
        $dailyCashRecords = DailyCash::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(function($item) {
                return $item->date->format('Y-m-d');
            });
        
        $salesQuery = Penjualan::whereBetween(DB::raw('DATE(created_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesQuery->where('status', 'completed');
        }
        
        $sales = $salesQuery->get();
        $salesByDate = $sales->groupBy(function($sale) {
            return Carbon::parse($sale->created_at)->format('Y-m-d');
        });
        
        $summaryData = [];
        $totalCashOnHand = 0;
        $totalSales = 0;
        $totalNetSales = 0;
        $totalDiscount = 0;
        $totalGrossSales = 0;
        
        foreach ($dates as $date) {
            $dateStr = $date->format('Y-m-d');
            $dailyCash = $dailyCashRecords->get($dateStr);
            $daySales = $salesByDate->get($dateStr, collect());
            
            $cashOnHand = $dailyCash ? $dailyCash->opening_cash : 0;
            $dayTotalSales = $daySales->sum('bayar');
            $dayDiscount = $daySales->sum(fn ($s) => $s->getSaleDiscountAmount());
            $dayGrossSales = $dayTotalSales + $dayDiscount;
            $dayNetSales = $dayTotalSales - $cashOnHand;
            
            $summaryData[] = [
                'date' => $dateStr,
                'date_formatted' => $date->format('F d, Y'),
                'cash_on_hand' => $cashOnHand,
                'gross_sales' => $dayGrossSales,
                'total_discount' => $dayDiscount,
                'total_sales' => $dayTotalSales,
                'net_sales' => $dayNetSales,
                'transaction_count' => $daySales->count()
            ];
            
            $totalCashOnHand += $cashOnHand;
            $totalSales += $dayTotalSales;
            $totalNetSales += $dayNetSales;
            $totalDiscount += $dayDiscount;
            $totalGrossSales += $dayGrossSales;
        }
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header
        $sheet->setCellValue('A1', 'Sales Summary Report');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A2', 'Period: ' . $start->format('F d, Y') . ' to ' . $end->format('F d, Y'));
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Column headers
        $row = 4;
        $sheet->setCellValue('A' . $row, 'Date');
        $sheet->setCellValue('B' . $row, 'Cash on Hand');
        $sheet->setCellValue('C' . $row, 'Gross Sales');
        $sheet->setCellValue('D' . $row, 'Discount');
        $sheet->setCellValue('E' . $row, 'Total Sales');
        $sheet->setCellValue('F' . $row, 'Net Sales');
        $sheet->setCellValue('G' . $row, 'Transactions');
        
        $headerStyle = $sheet->getStyle('A' . $row . ':G' . $row);
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Data rows
        $row++;
        foreach ($summaryData as $data) {
            $sheet->setCellValue('A' . $row, $data['date_formatted']);
            $sheet->setCellValue('B' . $row, $data['cash_on_hand']);
            $sheet->setCellValue('C' . $row, $data['gross_sales']);
            $sheet->setCellValue('D' . $row, $data['total_discount']);
            $sheet->setCellValue('E' . $row, $data['total_sales']);
            $sheet->setCellValue('F' . $row, $data['net_sales']);
            $sheet->setCellValue('G' . $row, $data['transaction_count']);
            
            // Format currency columns
            $sheet->getStyle('B' . $row . ':F' . $row)->getNumberFormat()
                ->setFormatCode('#,##0.00');
            
            $row++;
        }
        
        // Total row
        $sheet->setCellValue('A' . $row, 'Total');
        $sheet->setCellValue('B' . $row, $totalCashOnHand);
        $sheet->setCellValue('C' . $row, $totalGrossSales);
        $sheet->setCellValue('D' . $row, $totalDiscount);
        $sheet->setCellValue('E' . $row, $totalSales);
        $sheet->setCellValue('F' . $row, $totalNetSales);
        
        $totalStyle = $sheet->getStyle('A' . $row . ':G' . $row);
        $totalStyle->getFont()->setBold(true);
        $totalStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD4EDDA');
        $sheet->getStyle('B' . $row . ':F' . $row)->getNumberFormat()
            ->setFormatCode('#,##0.00');
        
        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $filename = 'sales_summary_' . $start->format('Y-m-d') . '_to_' . $end->format('Y-m-d') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }

    /**
     * Export Detailed Excel
     */
    public function exportDetailedExcel(Request $request)
    {
        $filterType = $request->get('filter_type', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        $dateRange = $this->getDateRange($filterType, $startDate, $endDate);
        $start = $dateRange['start'];
        $end = $dateRange['end'];
        
        // Get data (same logic as detailed method)
        $dailyCashRecords = DailyCash::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(function($item) {
                return $item->date->format('Y-m-d');
            });
        
        $salesQuery = Penjualan::with(['member', 'user'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('created_at', 'asc');
        
        if (Schema::hasColumn('penjualan', 'status')) {
            $salesQuery->where('status', 'completed');
        }
        
        $sales = $salesQuery->get();
        
        $detailedData = [];
        $currentDate = null;
        $dayTotal = 0;
        $dayDiscount = 0;
        $dayGrossSales = 0;
        $dayCashOnHand = 0;
        $dayNetSales = 0;
        $firstTransactionOfDay = true;
        
        foreach ($sales as $sale) {
            $saleDate = Carbon::parse($sale->created_at)->format('Y-m-d');
            $saleDiscount = $sale->getSaleDiscountAmount();
            
            if ($currentDate !== null && $currentDate !== $saleDate) {
                $detailedData[] = [
                    'type' => 'daily_total',
                    'date' => $currentDate,
                    'cash_on_hand' => $dayCashOnHand,
                    'gross_sales' => $dayGrossSales,
                    'total_discount' => $dayDiscount,
                    'total_sales' => $dayTotal,
                    'net_sales' => $dayNetSales
                ];
                $dayTotal = 0;
                $dayDiscount = 0;
                $dayGrossSales = 0;
                $dayCashOnHand = 0;
                $dayNetSales = 0;
                $firstTransactionOfDay = true;
            }
            
            if ($currentDate !== $saleDate) {
                $dailyCash = $dailyCashRecords->get($saleDate);
                $dayCashOnHand = $dailyCash ? $dailyCash->opening_cash : 0;
                $currentDate = $saleDate;
                $firstTransactionOfDay = true;
            }
            
            $detailedData[] = [
                'type' => 'transaction',
                'date' => $saleDate,
                'date_formatted' => Carbon::parse($sale->created_at)->format('F d, Y'),
                'receipt_no' => $sale->receiptno,
                'customer' => $sale->member->nama ?? 'Walk-in',
                'payment_method' => $sale->payment_method ?? 'Cash',
                'gross_amount' => $sale->bayar + $saleDiscount,
                'discount' => $saleDiscount,
                'discount_percentage' => $sale->getDiscountPercentage(),
                'amount' => $sale->bayar,
                'cashier' => $sale->user->name ?? 'N/A',
                'cash_on_hand' => $firstTransactionOfDay ? $dayCashOnHand : null,
                'is_first_of_day' => $firstTransactionOfDay
            ];
            
            $firstTransactionOfDay = false;
            $dayTotal += $sale->bayar;
            $dayDiscount += $saleDiscount;
            $dayGrossSales += $sale->bayar + $saleDiscount;
            $dayNetSales = $dayTotal - $dayCashOnHand;
        }
        
        if ($currentDate !== null) {
            $detailedData[] = [
                'type' => 'daily_total',
                'date' => $currentDate,
                'cash_on_hand' => $dayCashOnHand,
                'gross_sales' => $dayGrossSales,
                'total_discount' => $dayDiscount,
                'total_sales' => $dayTotal,
                'net_sales' => $dayNetSales
            ];
        }
        
        $totalCashOnHand = $dailyCashRecords->sum('opening_cash');
        $totalSales = $sales->sum('bayar');
        $totalDiscount = $sales->sum(fn ($s) => $s->getSaleDiscountAmount());
        $totalGrossSales = $totalSales + $totalDiscount;
        $totalNetSales = $totalSales - $totalCashOnHand;
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header
        $sheet->setCellValue('A1', 'Sales Detailed Report');
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A2', 'Period: ' . $start->format('F d, Y') . ' to ' . $end->format('F d, Y'));
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Column headers
        $row = 4;
        $sheet->setCellValue('A' . $row, 'Date');
        $sheet->setCellValue('B' . $row, 'Receipt No');
        $sheet->setCellValue('C' . $row, 'Customer');
        $sheet->setCellValue('D' . $row, 'Payment Type');
        $sheet->setCellValue('E' . $row, 'Gross Amount');
        $sheet->setCellValue('F' . $row, 'Discount(%)');
        $sheet->setCellValue('G' . $row, 'Discount');
        $sheet->setCellValue('H' . $row, 'Sale Amount');
        $sheet->setCellValue('I' . $row, 'Cash on Hand');
        $sheet->setCellValue('J' . $row, 'Daily Total Sales');
        $sheet->setCellValue('K' . $row, 'Net Sales');
        
        $headerStyle = $sheet->getStyle('A' . $row . ':K' . $row);
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0E0');
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Data rows
        $row++;
        foreach ($detailedData as $data) {
            if ($data['type'] == 'transaction') {
                $sheet->setCellValue('A' . $row, $data['date_formatted']);
                $sheet->setCellValue('B' . $row, $data['receipt_no']);
                $sheet->setCellValue('C' . $row, $data['customer']);
                $sheet->setCellValue('D' . $row, $data['payment_method']);
                $sheet->setCellValue('E' . $row, $data['gross_amount']);
                $sheet->setCellValue('F' . $row, ($data['discount_percentage'] ?? 0) > 0 ? number_format($data['discount_percentage'], 2) . '%' : '-');
                $discountVal = ($data['discount'] ?? 0) > 0 ? (($data['discount_percentage'] ?? 0) > 0 ? round($data['discount'], 0) : $data['discount']) : null;
                $sheet->setCellValue('G' . $row, $discountVal !== null ? $discountVal : '-');
                $sheet->setCellValue('H' . $row, $data['amount']);
                $sheet->setCellValue('I' . $row, $data['cash_on_hand'] ?? '');
                $sheet->setCellValue('J' . $row, '');
                $sheet->setCellValue('K' . $row, '');
                
                // Format currency columns (E=Gross, G=Discount value, H=Amount; percentage discount = whole number)
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode(($data['discount_percentage'] ?? 0) > 0 ? '#,##0' : '#,##0.00');
                $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                if ($data['cash_on_hand'] !== null) {
                    $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                }
            } else {
                // Daily total row
                $sheet->setCellValue('A' . $row, 'Daily Totals for ' . date('F d, Y', strtotime($data['date'])));
                $sheet->mergeCells('A' . $row . ':D' . $row);
                $sheet->setCellValue('E' . $row, $data['gross_sales']);
                $sheet->setCellValue('F' . $row, '-');
                $sheet->setCellValue('G' . $row, $data['total_discount']);
                $sheet->setCellValue('H' . $row, $data['total_sales']);
                $sheet->setCellValue('I' . $row, $data['cash_on_hand']);
                $sheet->setCellValue('J' . $row, $data['total_sales']);
                $sheet->setCellValue('K' . $row, $data['net_sales']);
                
                $dailyTotalStyle = $sheet->getStyle('A' . $row . ':K' . $row);
                $dailyTotalStyle->getFont()->setBold(true);
                $dailyTotalStyle->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF0F0F0');
                
                $sheet->getStyle('E' . $row . ':K' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $row++;
        }
        
        // Grand total row
        $sheet->setCellValue('A' . $row, 'Grand Total');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->setCellValue('E' . $row, $totalGrossSales);
        $sheet->setCellValue('F' . $row, '-');
        $sheet->setCellValue('G' . $row, $totalDiscount);
        $sheet->setCellValue('H' . $row, $totalSales);
        $sheet->setCellValue('I' . $row, $totalCashOnHand);
        $sheet->setCellValue('J' . $row, $totalSales);
        $sheet->setCellValue('K' . $row, $totalNetSales);
        
        $totalStyle = $sheet->getStyle('A' . $row . ':K' . $row);
        $totalStyle->getFont()->setBold(true);
        $totalStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD4EDDA');
        $sheet->getStyle('E' . $row . ':K' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        
        // Auto-size columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $filename = 'sales_detailed_' . $start->format('Y-m-d') . '_to_' . $end->format('Y-m-d') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
}
