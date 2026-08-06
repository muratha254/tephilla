<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Penjualan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use PDF;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $totalBalance = Account::where('is_active', true)->sum('balance');
        
        return view('account.index', compact('accounts', 'totalBalance'));
    }

    public function data()
    {
        $accounts = Account::where('is_active', true)->orderBy('name')->get();

        return datatables()
            ->of($accounts)
            ->addIndexColumn()
            ->addColumn('balance', function ($account) {
                return 'Ksh ' . number_format($account->balance, 2);
            })
            ->addColumn('status', function ($account) {
                return $account->is_active 
                    ? '<span class="label label-success">Active</span>' 
                    : '<span class="label label-danger">Inactive</span>';
            })
            ->addColumn('aksi', function ($account) {
                return '
                    <div class="btn-group">
                        <button onclick="viewAccount(`' . route('account.show', $account->id) . '`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-eye"></i> View</button>
                    </div>
                ';
            })
            ->rawColumns(['status', 'aksi'])
            ->make(true);
    }

    public function show($id, Request $request)
    {
        $account = Account::findOrFail($id);
        
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));

        // Get transactions (sales) for this account
        $query = Penjualan::where('payment_method', $account->name)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->orderBy('created_at', 'desc');

        // Only show completed sales if status column exists
        if (\Illuminate\Support\Facades\Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }

        $transactions = $query->with('user')->get();
        $totalAmount = $transactions->sum('bayar');

        return view('account.show', compact('account', 'transactions', 'startDate', 'endDate', 'totalAmount'));
    }

    public function exportPdf($id, Request $request)
    {
        $account = Account::findOrFail($id);
        
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));

        // Get transactions (sales) for this account
        $query = Penjualan::where('payment_method', $account->name)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->orderBy('created_at', 'desc');

        // Only show completed sales if status column exists
        if (\Illuminate\Support\Facades\Schema::hasColumn('penjualan', 'status')) {
            $query->where('status', 'completed');
        }

        $transactions = $query->with('user')->get();
        $totalAmount = $transactions->sum('bayar');
        $setting = Setting::first();

        $pdf = PDF::loadView('account.transactions_pdf', [
            'account' => $account,
            'transactions' => $transactions,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'setting' => $setting,
            'totalAmount' => $totalAmount,
        ]);

        return $pdf->download('account_' . $account->name . '_transactions_' . $startDate . '_to_' . $endDate . '.pdf');
    }
}
