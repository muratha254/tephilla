<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use Illuminate\Http\Request;

class LoyaltyReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeLoyaltyReport();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $reportType = $request->input('report_type', '');
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        $rows = collect();
        $transactions = collect();
        if ($generated) {
            $query = Customer::query()
                ->with('branch')
                ->where('is_walk_in', false)
                ->whereBetween('created_at', [
                    $from . ' 00:00:00',
                    $to . ' 23:59:59',
                ])
                ->when($branchId, fn ($q) => $q->where(function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)->orWhereNull('branch_id');
                }));

            if ($reportType === 'with_points') {
                $query->where('loyalty_points', '>', 0);
            } elseif ($reportType === 'zero') {
                $query->where(function ($q) {
                    $q->whereNull('loyalty_points')->orWhere('loyalty_points', '<=', 0);
                });
            }

            $rows = $query->orderBy('name')->get()->map(function (Customer $customer, int $i) {
                return [
                    'index' => $i + 1,
                    'branch' => optional($customer->branch)->name ?: '-',
                    'customer' => $customer->name,
                    'phone' => $customer->phone ?: $customer->mobile ?: '-',
                    'points' => round((float) $customer->loyalty_points, 2),
                    'registered' => optional($customer->created_at)->format('d-m-Y'),
                    'status' => $customer->is_active ? 'Active' : 'Inactive',
                ];
            })->values();

            $transactions = LoyaltyTransaction::query()
                ->with('customer')
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->orderByDesc('id')
                ->limit(500)
                ->get();
        }

        return view('reports.loyalty.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.loyalty',
            'from' => $from,
            'to' => $to,
            'reportType' => $reportType,
            'generated' => $generated,
            'rows' => $rows,
            'transactions' => $transactions,
        ]));
    }

    private function authorizeLoyaltyReport(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasPermission('reports.view'), 403);
    }
}
