<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PDF;

class CustomerReportsController extends Controller
{
    public function customers(Request $request)
    {
        $this->authorizeCustomersReport();

        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        $rows = collect();
        if ($generated) {
            $rows = $this->customerQuery($customerId, $branchId)
                ->get()
                ->map(function (Customer $customer, int $i) {
                    return [
                        'index' => $i + 1,
                        'branch' => optional($customer->branch)->name ?: '-',
                        'code' => str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT),
                        'name' => $customer->name,
                        'phone' => $customer->phone ?: $customer->mobile ?: '-',
                        'reg_date' => optional($customer->created_at)->format('d-m-Y') ?: '-',
                        'registered_by' => '-',
                        'savings' => 0,
                        'loyalty' => round((float) $customer->loyalty_points, 2),
                        'balance' => $customer->creditAmount(),
                    ];
                })->values();
        }

        return view('reports.customers.customers', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.customers.customers',
            'customerId' => $customerId,
            'generated' => $generated,
            'customers' => $this->customerOptions(),
            'rows' => $rows,
        ]));
    }

    public function statement(Request $request)
    {
        $this->authorizeCustomersReport();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        $reportType = $request->input('report_type', 'detailed');
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        $rows = collect();
        if ($generated) {
            $rows = $this->statementRows($from, $to, $customerId, $branchId, $reportType);
        }

        return view('reports.customers.statement', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.customers.statement',
            'from' => $from,
            'to' => $to,
            'customerId' => $customerId,
            'reportType' => $reportType,
            'generated' => $generated,
            'customers' => $this->customerOptions(),
            'rows' => $rows,
        ]));
    }

    public function statementPdf(Request $request)
    {
        $this->authorizeCustomersReport();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        $reportType = $request->input('report_type', 'detailed');
        $branchId = $this->currentBranchId();
        $rows = $this->statementRows($from, $to, $customerId, $branchId, $reportType);

        $pdf = PDF::loadView('reports.customers.statement-pdf', array_merge(fleet_shared_view_data(), [
            'from' => $from,
            'to' => $to,
            'reportType' => $reportType,
            'rows' => $rows,
            'profile' => fleet_company_profile(),
        ]))->setPaper('a4', 'portrait');

        return $pdf->download('customer-statement-' . $from . '-to-' . $to . '.pdf');
    }

    public function purchases(Request $request)
    {
        $this->authorizeCustomersReport();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        $rows = collect();
        if ($generated) {
            $rows = Sale::query()
                ->with(['customer', 'branch', 'cashier'])
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereBetween('sale_date', [
                    Carbon::parse($from)->startOfDay(),
                    Carbon::parse($to)->endOfDay(),
                ])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                ->when(! $customerId, fn ($q) => $q->whereNotNull('customer_id'))
                ->orderBy('sale_date')
                ->orderBy('id')
                ->get()
                ->map(function (Sale $sale, int $i) {
                    return [
                        'index' => $i + 1,
                        'branch' => optional($sale->branch)->name ?: '-',
                        'invoice' => $sale->invoice_number ?: $sale->receipt_number ?: $sale->number,
                        'sales_date' => optional($sale->sale_date)->format('d-m-Y'),
                        'customer' => optional($sale->customer)->name ?: 'Walk-in',
                        'sold_by' => optional($sale->cashier)->name ?: '-',
                        'total' => round((float) $sale->total, 2),
                        'paid' => round((float) $sale->paid_amount, 2),
                        'due' => round((float) $sale->balance, 2),
                    ];
                })->values();
        }

        return view('reports.customers.purchases', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.customers.purchases',
            'from' => $from,
            'to' => $to,
            'customerId' => $customerId,
            'generated' => $generated,
            'customers' => $this->customerOptions(),
            'rows' => $rows,
        ]));
    }

    private function statementRows(string $from, string $to, ?int $customerId, ?int $branchId, string $reportType)
    {
        $customers = $this->customerQuery($customerId, $branchId)->get();
        $rows = collect();
        $index = 0;

        foreach ($customers as $customer) {
            $sales = Sale::query()
                ->where('customer_id', $customer->id)
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereBetween('sale_date', [
                    Carbon::parse($from)->startOfDay(),
                    Carbon::parse($to)->endOfDay(),
                ])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('sale_date')
                ->get();

            $payments = Payment::query()
                ->where('customer_id', $customer->id)
                ->whereBetween('paid_at', [
                    Carbon::parse($from)->startOfDay(),
                    Carbon::parse($to)->endOfDay(),
                ])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('paid_at')
                ->get();

            if ($reportType === 'summary') {
                $index++;
                $rows->push([
                    'index' => $index,
                    'customer' => $customer->name,
                    'date' => $from . ' to ' . $to,
                    'reference' => 'Summary',
                    'description' => 'Sales / Payments',
                    'debit' => round((float) $sales->sum('total'), 2),
                    'credit' => round((float) $payments->sum('amount'), 2),
                    'balance' => $customer->creditAmount(),
                ]);
                continue;
            }

            foreach ($sales as $sale) {
                $index++;
                $rows->push([
                    'index' => $index,
                    'customer' => $customer->name,
                    'date' => optional($sale->sale_date)->format('d-m-Y'),
                    'reference' => $sale->invoice_number ?: $sale->receipt_number ?: $sale->number,
                    'description' => 'Sale',
                    'debit' => round((float) $sale->total, 2),
                    'credit' => 0,
                    'balance' => round((float) $sale->balance, 2),
                ]);
            }

            foreach ($payments as $payment) {
                $index++;
                $rows->push([
                    'index' => $index,
                    'customer' => $customer->name,
                    'date' => optional($payment->paid_at)->format('d-m-Y'),
                    'reference' => $payment->number ?: '-',
                    'description' => 'Payment (' . ucfirst(str_replace('_', ' ', (string) $payment->method)) . ')',
                    'debit' => 0,
                    'credit' => round((float) $payment->amount, 2),
                    'balance' => $customer->creditAmount(),
                ]);
            }
        }

        return $rows->values();
    }

    private function customerQuery(?int $customerId = null, ?int $branchId = null)
    {
        return Customer::query()
            ->with('branch')
            ->where('is_walk_in', false)
            ->withSum(['sales as credit_sales' => function ($builder) {
                $builder->where('status', Sale::STATUS_COMPLETED);
            }], 'balance')
            ->when($customerId, fn ($q) => $q->where('id', $customerId))
            ->when($branchId, fn ($q) => $q->where(function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->orderBy('name');
    }

    private function customerOptions()
    {
        return Customer::query()
            ->where('is_walk_in', false)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function authorizeCustomersReport(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('reports.customers') || $user->hasPermission('reports.view')), 403);
    }
}
