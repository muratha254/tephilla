<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Folding;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function data(User $user, ?int $branchId = null): array
    {
        $company = $user->company;
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();

        if ($branchId && $branches->contains('id', $branchId)) {
            $branch = $branches->firstWhere('id', $branchId);
        } else {
            $currentId = session('current_branch_id', $user->branch_id);
            $branch = $branches->firstWhere('id', $currentId) ?: $branches->first();
        }

        $now = Carbon::now(optional($company)->timezone ?: config('app.timezone'));
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $resolvedBranchId = $branch ? $branch->id : null;

        $salesQuery = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->when($resolvedBranchId, function ($query) use ($resolvedBranchId) {
                $query->where('branch_id', $resolvedBranchId);
            });

        $todaySales = (clone $salesQuery)
            ->whereBetween('sale_date', [$todayStart, $todayEnd])
            ->sum('total');

        $todayPaid = (clone $salesQuery)
            ->whereBetween('sale_date', [$todayStart, $todayEnd])
            ->sum('paid_amount');

        $todayDue = (clone $salesQuery)
            ->whereBetween('sale_date', [$todayStart, $todayEnd])
            ->sum('balance');

        $todayCount = (clone $salesQuery)
            ->whereBetween('sale_date', [$todayStart, $todayEnd])
            ->count();

        $salesDue = (clone $salesQuery)
            ->whereIn('payment_status', [Sale::PAYMENT_UNPAID, Sale::PAYMENT_PARTIAL])
            ->sum('balance');

        $invoiceCount = (clone $salesQuery)
            ->whereNotNull('invoice_number')
            ->whereBetween('sale_date', [$monthStart, $monthEnd])
            ->count();

        // Unpaid purchase balances for the active branch (matches Purchase List Balance total).
        $purchaseDue = round((float) PurchaseOrder::query()
            ->when($resolvedBranchId, function ($query) use ($resolvedBranchId) {
                $query->where('branch_id', $resolvedBranchId);
            })
            ->whereNotIn('status', [
                PurchaseOrder::STATUS_CANCELLED,
                PurchaseOrder::STATUS_DRAFT,
            ])
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as due')
            ->value('due'), 2);

        $monthExpenses = (float) Expense::query()
            ->when($resolvedBranchId, function ($query) use ($resolvedBranchId) {
                $query->where('branch_id', $resolvedBranchId);
            })
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $customersActive = Customer::query()->where('is_active', true)->where('is_walk_in', false)->count();
        $customersInactive = Customer::query()->where('is_active', false)->count();
        $suppliersActive = Supplier::query()->where('is_active', true)->count();
        $suppliersInactive = Supplier::query()->where('is_active', false)->count();
        $productsActive = Product::query()->where('is_active', true)->count();
        $productsInactive = Product::query()->where('is_active', false)->count();

        $lowStock = $this->lowStock($resolvedBranchId);
        $topMovers = $this->topMovers($resolvedBranchId, $monthStart, $monthEnd);
        $symbol = optional($company)->currency_symbol ?? 'Ksh';

        $foldingQuery = Folding::query()->when($resolvedBranchId, fn ($query) => $query->where('branch_id', $resolvedBranchId));
        $todayProduction = (clone $foldingQuery)->whereDate('folded_on', $now->toDateString())->sum('quantity');
        $recentFoldings = (clone $foldingQuery)->with(['product', 'colour'])->orderByDesc('folded_on')->orderByDesc('id')->limit(6)->get();
        $recentMovements = StockMovement::query()
            ->with(['product', 'variant', 'user'])
            ->when($resolvedBranchId, fn ($query) => $query->where('branch_id', $resolvedBranchId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();
        $recentActivities = AuditLog::query()
            ->with('user')
            ->when($resolvedBranchId, fn ($query) => $query->where('branch_id', $resolvedBranchId))
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
        $outOfStockCount = ProductBranchStock::query()
            ->when($resolvedBranchId, fn ($query) => $query->where('branch_id', $resolvedBranchId))
            ->where('quantity', '<=', 0)
            ->count();
        $inventoryValue = (float) ProductBranchStock::query()
            ->when($resolvedBranchId, fn ($query) => $query->where('branch_id', $resolvedBranchId))
            ->selectRaw('COALESCE(SUM(quantity * average_cost), 0) as value')
            ->value('value');

        return [
            'company' => $company,
            'branches' => $branches,
            'branch' => $branch,
            'currencySymbol' => $symbol,
            'purchaseDue' => $purchaseDue,
            'salesDue' => $salesDue,
            'todaySales' => $todaySales,
            'todayPaid' => $todayPaid,
            'todayDue' => $todayDue,
            'todayCount' => $todayCount,
            'todayDate' => $now->format('d-m-Y'),
            'monthExpenses' => $monthExpenses,
            'monthLabel' => $now->format('M/Y'),
            'customersActive' => $customersActive,
            'customersInactive' => $customersInactive,
            'suppliersActive' => $suppliersActive,
            'suppliersInactive' => $suppliersInactive,
            'productsActive' => $productsActive,
            'productsInactive' => $productsInactive,
            'invoiceCount' => $invoiceCount,
            'lowStock' => $lowStock,
            'lowStockCount' => $lowStock->count(),
            'todayProduction' => (float) $todayProduction,
            'flatSheetStock' => $this->flatSheetStock($resolvedBranchId),
            'recentFoldings' => $recentFoldings,
            'recentMovements' => $recentMovements,
            'recentActivities' => $recentActivities,
            'outOfStockCount' => $outOfStockCount,
            'inventoryValue' => $inventoryValue,
            'topMovers' => $topMovers,
            'categorySales' => $this->categorySales($resolvedBranchId, $monthStart, $monthEnd),
            'pendingSales' => $this->pendingSales($resolvedBranchId),
            'barChart' => $this->monthlyBars($resolvedBranchId, $now),
            'can' => [
                'purchases' => $user->hasPermission('purchases.view'),
                'sales' => $user->hasPermission('sales.view'),
                'expenses' => $user->hasPermission('expenses.view'),
                'customers' => $user->hasPermission('customers.view'),
                'suppliers' => $user->hasPermission('suppliers.view'),
                'products' => $user->hasPermission('products.view'),
                'inventory' => $user->hasPermission('inventory.view'),
                'pos' => $user->hasPermission('pos.view'),
            ],
        ];
    }

    private function lowStock(?int $branchId): Collection
    {
        if (! $branchId) {
            return collect();
        }

        return Product::query()
            ->with(['category', 'brand'])
            ->where('is_active', true)
            ->where('manage_stock', true)
            ->where('reorder_level', '>', 0)
            ->whereHas('branchStock', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)
                    ->whereColumn('product_branch_stock.quantity', '<=', 'products.reorder_level');
            })
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function (Product $product) use ($branchId) {
                $qty = (float) ProductBranchStock::query()
                    ->where('product_id', $product->id)
                    ->where('branch_id', $branchId)
                    ->sum('quantity');

                $product->stock_on_hand = $qty;

                return $product;
            });
    }

    private function topMovers(?int $branchId, Carbon $from, Carbon $to): Collection
    {
        return SaleItem::query()
            ->selectRaw('sale_items.product_id, sale_items.name, SUM(sale_items.quantity) as qty_sold')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('sales.branch_id', $branchId);
            })
            ->whereNotNull('sale_items.product_id')
            ->groupBy('sale_items.product_id', 'sale_items.name')
            ->orderByDesc('qty_sold')
            ->limit(5)
            ->get();
    }

    private function categorySales(?int $branchId, Carbon $from, Carbon $to): Collection
    {
        $rows = SaleItem::query()
            ->selectRaw("COALESCE(product_categories.name, 'Uncategorized') as category_name, SUM(sale_items.line_total) as amount")
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sale_date', [$from, $to])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('sales.branch_id', $branchId);
            })
            ->groupByRaw("COALESCE(product_categories.name, 'Uncategorized')")
            ->orderByDesc('amount')
            ->get();

        $total = (float) $rows->sum('amount');

        return $rows->map(function ($row) use ($total) {
            $amount = (float) $row->amount;

            return [
                'label' => $row->category_name,
                'amount' => $amount,
                'percent' => $total > 0 ? round(($amount / $total) * 100, 1) : 0,
            ];
        });
    }

    private function flatSheetStock(?int $branchId): Collection
    {
        $product = Product::query()->whereIn('name', ['Flat Sheet', 'Flatsheets'])->orderBy('name')->first();
        if (! $product) {
            return collect();
        }

        return ProductBranchStock::query()
            ->with('variant')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('product_id', $product->id)
            ->where('product_variant_id', '>', 0)
            ->orderBy('product_variant_id')
            ->get();
    }

    private function pendingSales(?int $branchId): Collection
    {
        return Sale::query()
            ->with('customer')
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereIn('payment_status', [Sale::PAYMENT_UNPAID, Sale::PAYMENT_PARTIAL])
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->orderByDesc('sale_date')
            ->limit(8)
            ->get();
    }

    /**
     * @return array{labels: array<int, string>, purchases: array<int, float>, sales: array<int, float>, expenses: array<int, float>}
     */
    private function monthlyBars(?int $branchId, Carbon $now): array
    {
        $labels = [];
        $purchases = [];
        $sales = [];
        $expenses = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = $now->copy()->subMonths($i)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $labels[] = $start->format('M');

            $purchases[] = round((float) PurchaseOrder::query()
                ->when($branchId, function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                })
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total'), 2);

            $sales[] = round((float) Sale::query()
                ->where('status', Sale::STATUS_COMPLETED)
                ->when($branchId, function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                })
                ->whereBetween('sale_date', [$start, $end])
                ->sum('total'), 2);

            $expenses[] = round((float) Expense::query()
                ->when($branchId, function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                })
                ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount'), 2);
        }

        return [
            'labels' => $labels,
            'purchases' => $purchases,
            'sales' => $sales,
            'expenses' => $expenses,
        ];
    }
}
