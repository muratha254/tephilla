<?php

namespace App\Http\Controllers;

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use PDF;

class DriverCommissionController extends Controller
{
    public function index(Request $request)
    {
        return view('driver_commission.index');
    }

    public function data(Request $request)
    {
        $query = Penjualan::query();

        // Only show completed sales
        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }

        // Date filtering
        if ($request->filled('start_date')) {
            $query->whereDate('saledate', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('saledate', '<=', $request->input('end_date'));
        }

        // Show all sales - don't filter by commission > 0
        // This allows viewing all sales even if commission wasn't calculated for old sales

        $penjualans = $query->orderBy('id_penjualan', 'desc')->get();

        return datatables()
            ->of($penjualans)
            ->addIndexColumn()
            ->addColumn('saledate', function ($penjualan) {
                return $penjualan->saledate ? date('Y-m-d', strtotime($penjualan->saledate)) : tanggal_indonesia($penjualan->created_at, false);
            })
            ->addColumn('receiptno', function ($penjualan) {
                return $penjualan->receiptno;
            })
            ->addColumn('subtotal', function ($penjualan) {
                // Calculate subtotal before VAT: bayar (total payable) - tax
                $tax = $penjualan->tax ?? 0;
                $subtotal = $penjualan->bayar - $tax;
                return 'Ksh ' . format_uang($subtotal);
            })
            ->addColumn('driver_commission', function ($penjualan) {
                $commission = $penjualan->driver_commission ?? 0;
                return 'Ksh ' . format_uang($commission);
            })
            ->addColumn('commission_rate', function ($penjualan) {
                $setting = Setting::first();
                $rate = $setting->driver_commission_rate ?? 0;
                return $rate . '%';
            })
            ->addColumn('total_payable', function ($penjualan) {
                return 'Ksh ' . format_uang($penjualan->bayar);
            })
            ->addColumn('kasir', function ($penjualan) {
                return $penjualan->user->name ?? '';
            })
            ->addColumn('aksi', function ($penjualan) {
                return '
                    <div class="btn-group">
                        <button onclick="showDetail(`'. route('driver-commission.show', $penjualan->id_penjualan) .'`)" class="btn btn-xs btn-primary btn-flat" title="View Details"><i class="fa fa-eye"></i></button>
                    </div>
                ';
            })
            ->rawColumns(['aksi'])
            ->make(true);
    }

    public function summary(Request $request)
    {
        $filterType = $request->get('filter_type', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Get date range
        if ($filterType === 'custom' && $startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
        } elseif ($filterType === 'daily') {
            $start = Carbon::today();
            $end = Carbon::today();
        } elseif ($filterType === 'weekly') {
            $start = Carbon::now()->startOfWeek();
            $end = Carbon::now()->endOfWeek();
        } elseif ($filterType === 'monthly') {
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
        } else {
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
        }

        $query = Penjualan::query();

        if (Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }

        if (Schema::hasColumn('penjualan', 'driver_commission')) {
            $query->where('driver_commission', '>', 0);
        }

        $query->whereBetween(DB::raw('DATE(created_at)'), [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        $sales = $query->get();

        $totalCommission = $sales->sum('driver_commission');
        $totalSubtotal = 0;
        foreach ($sales as $sale) {
            // Calculate subtotal before VAT: bayar (total payable) - tax
            $tax = $sale->tax ?? 0;
            $subtotal = $sale->bayar - $tax;
            $totalSubtotal += $subtotal;
        }

        $setting = Setting::first();
        $commissionRate = $setting->driver_commission_rate ?? 0;

        return response()->json([
            'total_commission' => $totalCommission,
            'total_subtotal' => $totalSubtotal,
            'commission_rate' => $commissionRate,
            'total_sales' => $sales->count(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ]);
    }

    public function show($id)
    {
        $penjualan = Penjualan::with(['member', 'user'])->findOrFail($id);
        $details = PenjualanDetail::with('produk')
            ->where('id_penjualan', $id)
            ->get();
        $setting = Setting::first();
        
        // Calculate subtotal before VAT
        $tax = $penjualan->tax ?? 0;
        $subtotalBeforeVat = $penjualan->bayar - $tax;
        $driverCommission = $penjualan->driver_commission ?? 0;
        $commissionRate = $setting->driver_commission_rate ?? 0;
        
        return view('driver_commission.show', compact(
            'penjualan', 
            'details', 
            'setting', 
            'subtotalBeforeVat', 
            'driverCommission', 
            'commissionRate'
        ));
    }

    public function exportPdf($id)
    {
        $penjualan = Penjualan::with(['member', 'user'])->findOrFail($id);
        $details = PenjualanDetail::with('produk')
            ->where('id_penjualan', $id)
            ->get();
        $setting = Setting::first();
        
        // Calculate subtotal before VAT
        $tax = $penjualan->tax ?? 0;
        $subtotalBeforeVat = $penjualan->bayar - $tax;
        $driverCommission = $penjualan->driver_commission ?? 0;
        $commissionRate = $setting->driver_commission_rate ?? 0;
        
        $pdf = PDF::loadView('driver_commission.pdf', compact(
            'penjualan', 
            'details', 
            'setting', 
            'subtotalBeforeVat', 
            'driverCommission', 
            'commissionRate'
        ));
        $pdf->setPaper('a4', 'portrait');
        return $pdf->stream('Service-Fee-'. $penjualan->receiptno .'.pdf');
    }
}






