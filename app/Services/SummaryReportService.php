<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SummaryReportService
{
    public function dailySummary(string $from, string $to, ?int $branchId = null, ?int $userId = null): array
    {
        $sales = $this->salesQuery($from, $to, $branchId, $userId);

        $expenses = (float) Expense::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        $purchases = (float) PurchaseOrder::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('status', [PurchaseOrder::STATUS_CANCELLED, PurchaseOrder::STATUS_DRAFT])
            ->whereBetween('order_date', [$from, $to])
            ->sum('total');

        $returns = (float) SaleReturn::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('return_date', [$this->start($from), $this->end($to)])
            ->sum('refund_amount');

        $paymentMethods = Payment::query()
            ->where('payable_type', Sale::class)
            ->whereHasMorph('payable', [Sale::class], function ($q) use ($from, $to, $branchId, $userId) {
                $q->where('status', Sale::STATUS_COMPLETED)
                    ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->when($userId, fn ($query) => $query->where('user_id', $userId));
            })
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($amount) => round((float) $amount, 2))
            ->all();

        return [
            'sales_count' => (clone $sales)->count(),
            'subtotal' => round((clone $sales)->sum('subtotal'), 2),
            'discount' => round((clone $sales)->sum('discount_amount'), 2),
            'tax' => round((clone $sales)->sum('tax_amount'), 2),
            'total' => round((clone $sales)->sum('total'), 2),
            'paid' => round((clone $sales)->sum('paid_amount'), 2),
            'balance' => round((clone $sales)->sum('balance'), 2),
            'expenses' => round($expenses, 2),
            'purchases' => round($purchases, 2),
            'returns' => round($returns, 2),
            'net_sales' => round((clone $sales)->sum('total') - $returns, 2),
            'payment_methods' => $paymentMethods,
        ];
    }

    public function employeeBranchRows(string $from, string $to, ?int $branchId = null, ?int $userId = null): Collection
    {
        $rows = Sale::query()
            ->selectRaw('user_id, branch_id, COUNT(*) as invoices, SUM(total) as total, SUM(paid_amount) as paid, SUM(balance) as balance')
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->groupBy('user_id', 'branch_id')
            ->orderBy('total', 'desc')
            ->get();

        $users = User::query()->whereIn('id', $rows->pluck('user_id')->filter())->get()->keyBy('id');
        $branches = Branch::query()->whereIn('id', $rows->pluck('branch_id')->filter())->get()->keyBy('id');

        return $rows->map(function ($row) use ($users, $branches) {
            return [
                'employee' => optional($users->get($row->user_id))->name ?: 'Unknown',
                'branch' => optional($branches->get($row->branch_id))->name ?: '-',
                'invoices' => (int) $row->invoices,
                'total' => round((float) $row->total, 2),
                'paid' => round((float) $row->paid, 2),
                'balance' => round((float) $row->balance, 2),
            ];
        })->values();
    }

    public function branchRows(string $from, string $to, ?int $branchId = null): Collection
    {
        $rows = Sale::query()
            ->selectRaw('branch_id, COUNT(*) as invoices, SUM(total) as total, SUM(paid_amount) as paid, SUM(balance) as balance')
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('branch_id')
            ->orderBy('total', 'desc')
            ->get();

        $branches = Branch::query()->whereIn('id', $rows->pluck('branch_id')->filter())->get()->keyBy('id');

        return $rows->map(function ($row) use ($branches) {
            return [
                'branch' => optional($branches->get($row->branch_id))->name ?: '-',
                'invoices' => (int) $row->invoices,
                'total' => round((float) $row->total, 2),
                'paid' => round((float) $row->paid, 2),
                'balance' => round((float) $row->balance, 2),
            ];
        })->values();
    }

    public function zReport(string $from, string $to, ?int $branchId = null, string $category = 'detailed', string $report = 'all'): array
    {
        $sales = $this->salesQuery($from, $to, $branchId);
        $salesCollection = (clone $sales)->get();

        $data = [
            'summary' => [
                'invoices' => $salesCollection->count(),
                'subtotal' => round($salesCollection->sum('subtotal'), 2),
                'discount' => round($salesCollection->sum('discount_amount'), 2),
                'tax' => round($salesCollection->sum('tax_amount'), 2),
                'total' => round($salesCollection->sum('total'), 2),
                'paid' => round($salesCollection->sum('paid_amount'), 2),
                'balance' => round($salesCollection->sum('balance'), 2),
            ],
            'payment_methods' => [],
            'tax_breakdown' => [],
            'voids' => [],
            'returns' => [],
            'items' => [],
        ];

        if ($report === 'all' || $report === 'payments') {
            $data['payment_methods'] = Payment::query()
                ->where('payable_type', Sale::class)
                ->whereIn('payable_id', $salesCollection->pluck('id'))
                ->selectRaw('method, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('method')
                ->orderBy('total', 'desc')
                ->get()
                ->map(fn ($row) => [
                    'method' => ucfirst(str_replace('_', ' ', (string) $row->method)),
                    'count' => (int) $row->count,
                    'total' => round((float) $row->total, 2),
                ])->all();
        }

        if ($report === 'all' || $report === 'tax') {
            $data['tax_breakdown'] = [
                ['label' => 'Tax Collected', 'amount' => $data['summary']['tax']],
                ['label' => 'Taxable Sales', 'amount' => round($data['summary']['subtotal'] - $data['summary']['discount'], 2)],
            ];
        }

        if ($report === 'all' || $report === 'voids') {
            $voids = Sale::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', Sale::STATUS_VOIDED)
                ->whereBetween('voided_at', [$this->start($from), $this->end($to)])
                ->orderBy('voided_at')
                ->get();

            $data['voids'] = $voids->map(fn (Sale $sale) => [
                'number' => $sale->receipt_number ?: $sale->number,
                'date' => optional($sale->voided_at)->format('d-m-Y H:i'),
                'total' => round((float) $sale->total, 2),
                'reason' => $sale->void_reason ?: '-',
            ])->all();
        }

        if ($report === 'all' || $report === 'returns') {
            $returns = SaleReturn::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereBetween('return_date', [$this->start($from), $this->end($to)])
                ->orderBy('return_date')
                ->get();

            $data['returns'] = $returns->map(fn (SaleReturn $row) => [
                'number' => $row->number,
                'date' => optional($row->return_date)->format('d-m-Y'),
                'total' => round((float) $row->refund_amount, 2),
            ])->all();
        }

        if ($category === 'detailed' && ($report === 'all' || $report === 'sales')) {
            $data['items'] = SaleItem::query()
                ->selectRaw('name, SUM(quantity) as qty, SUM(line_total) as total')
                ->whereIn('sale_id', $salesCollection->pluck('id'))
                ->groupBy('name')
                ->orderBy('total', 'desc')
                ->limit(100)
                ->get()
                ->map(fn ($row) => [
                    'product' => $row->name,
                    'qty' => round((float) $row->qty, 2),
                    'total' => round((float) $row->total, 2),
                ])->all();
        }

        return $data;
    }

    public function debtorsCreditors(?int $branchId = null): array
    {
        $customers = Customer::query()
            ->where('is_walk_in', false)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => [
                'name' => $customer->name,
                'phone' => $customer->phone ?: $customer->mobile,
                'branch' => optional($customer->branch)->name ?: '-',
                'balance' => $customer->creditAmount(),
            ])
            ->filter(fn ($row) => $row['balance'] > 0)
            ->values();

        $suppliers = Supplier::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'name' => $supplier->name,
                'phone' => $supplier->phone ?: $supplier->mobile,
                'branch' => optional($supplier->branch)->name ?: '-',
                'balance' => $supplier->currentBalance(),
            ])
            ->filter(fn ($row) => $row['balance'] > 0)
            ->values();

        return [
            'debtors' => $customers,
            'creditors' => $suppliers,
            'debtors_total' => round($customers->sum('balance'), 2),
            'creditors_total' => round($suppliers->sum('balance'), 2),
        ];
    }

    public function staffOptions(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function salesQuery(string $from, string $to, ?int $branchId = null, ?int $userId = null)
    {
        return Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId));
    }

    private function start(string $date): Carbon
    {
        return Carbon::parse($date)->startOfDay();
    }

    private function end(string $date): Carbon
    {
        return Carbon::parse($date)->endOfDay();
    }
}
