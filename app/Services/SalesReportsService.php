<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesReportsService
{
    public function staffOptions(): Collection
    {
        return User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function customerOptions(): Collection
    {
        return Customer::query()->where('is_walk_in', false)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'tax_number']);
    }

    public function categoryOptions(): Collection
    {
        return ProductCategory::query()->with('parent')->where('is_active', true)->orderBy('name')->get();
    }

    public function brandOptions(): Collection
    {
        return Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function productOptions(): Collection
    {
        return Product::query()->where('is_active', true)->where('for_sale', true)->orderBy('name')->limit(500)->get(['id', 'name']);
    }

    public function branchOptions(): Collection
    {
        $query = Branch::query()->where('is_active', true)->orderBy('name');
        $user = auth()->user();
        if ($user && ! $user->canSwitchBranches()) {
            $query->whereKey($user->branch_id);
        }

        return $query->get(['id', 'name']);
    }

    public function clearanceRows(string $from, string $to, ?int $branchId = null, ?int $userId = null): Collection
    {
        $rows = Sale::query()
            ->selectRaw('user_id, COUNT(*) as invoices, SUM(total) as total, SUM(paid_amount) as paid, SUM(balance) as balance')
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->get();

        $users = User::query()->whereIn('id', $rows->pluck('user_id')->filter())->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'employee' => optional($users->get($row->user_id))->name ?: 'Unknown',
            'invoices' => (int) $row->invoices,
            'total' => round((float) $row->total, 2),
            'paid' => round((float) $row->paid, 2),
            'balance' => round((float) $row->balance, 2),
        ])->values();
    }

    public function salesRows(string $from, string $to, ?int $branchId = null, array $filters = []): Collection
    {
        return $this->salesBase($from, $to, $branchId, $filters)
            ->with(['customer', 'cashier', 'branch'])
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get()
            ->map(function (Sale $sale, int $i) {
                return [
                    'index' => $i + 1,
                    'invoice' => $sale->invoice_number ?: $sale->receipt_number ?: $sale->number,
                    'sales_date' => optional($sale->sale_date)->format('d-m-Y'),
                    'customer' => optional($sale->customer)->name ?: 'Walk-in',
                    'kra_pin' => optional($sale->customer)->tax_number ?: '-',
                    'note' => $sale->notes ?: '-',
                    'total' => round((float) $sale->total, 2),
                    'paid' => round((float) $sale->paid_amount, 2),
                    'due' => round((float) $sale->balance, 2),
                    'employee' => optional($sale->cashier)->name ?: '-',
                    'branch' => optional($sale->branch)->name ?: '-',
                    'payment_status' => $sale->payment_status,
                ];
            })->values();
    }

    public function salesSummaryRows(string $from, string $to, ?int $branchId = null): Collection
    {
        return Sale::query()
            ->selectRaw('DATE(sale_date) as day, COUNT(*) as invoices, SUM(total) as total, SUM(paid_amount) as paid, SUM(balance) as balance')
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row, $i) => [
                'index' => $i + 1,
                'date' => Carbon::parse($row->day)->format('d-m-Y'),
                'invoices' => (int) $row->invoices,
                'total' => round((float) $row->total, 2),
                'paid' => round((float) $row->paid, 2),
                'balance' => round((float) $row->balance, 2),
            ])->values();
    }

    public function salesByEmployeeRows(string $from, string $to, ?int $branchId = null, ?int $userId = null): Collection
    {
        return $this->clearanceRows($from, $to, $branchId, $userId);
    }

    public function itemSalesRows(string $from, string $to, ?int $branchId = null, array $filters = []): Collection
    {
        $query = SaleItem::query()
            ->with(['sale.customer', 'sale.cashier', 'product.category', 'product.brand'])
            ->whereHas('sale', function ($q) use ($from, $to, $branchId, $filters) {
                $q->where('status', Sale::STATUS_COMPLETED)
                    ->whereBetween('sale_date', [$this->start($from, $filters['from_time'] ?? null), $this->end($to, $filters['to_time'] ?? null)])
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->when(! empty($filters['user_id']), fn ($query) => $query->where('user_id', (int) $filters['user_id']));
            })
            ->when(! empty($filters['product_id']), fn ($q) => $q->where('product_id', (int) $filters['product_id']))
            ->when(! empty($filters['category_id']), function ($q) use ($filters) {
                $ids = \App\Models\ProductCategory::idsIncludingChildren((int) $filters['category_id']);
                $q->whereHas('product', fn ($p) => $p->whereIn('category_id', $ids));
            })
            ->when(! empty($filters['brand_id']), function ($q) use ($filters) {
                $q->whereHas('product', fn ($p) => $p->where('brand_id', (int) $filters['brand_id']));
            })
            ->orderBy('id');

        return $query->get()->map(function (SaleItem $item, int $i) {
            $sale = $item->sale;

            return [
                'index' => $i + 1,
                'invoice' => optional($sale)->invoice_number ?: optional($sale)->receipt_number ?: optional($sale)->number,
                'sales_date' => optional(optional($sale)->sale_date)->format('d-m-Y'),
                'datetime' => optional(optional($sale)->sale_date)->format('d-m-Y H:i'),
                'customer' => optional(optional($sale)->customer)->name ?: 'Walk-in',
                'item' => $item->name,
                'qty' => round((float) $item->quantity, 2),
                'invoice_total' => round((float) optional($sale)->total, 2),
                'sold_by' => optional(optional($sale)->cashier)->name ?: '-',
                'line_total' => round((float) $item->line_total, 2),
            ];
        })->values();
    }

    public function itemSalesSummaryRows(string $from, string $to, ?int $branchId = null): Collection
    {
        return SaleItem::query()
            ->selectRaw('name, SUM(quantity) as qty, SUM(line_total) as total')
            ->whereHas('sale', function ($q) use ($from, $to, $branchId) {
                $q->where('status', Sale::STATUS_COMPLETED)
                    ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
            })
            ->groupBy('name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row, $i) => [
                'index' => $i + 1,
                'item' => $row->name,
                'qty' => round((float) $row->qty, 2),
                'total' => round((float) $row->total, 2),
            ])->values();
    }

    public function categorySummaryRows(string $from, string $to, ?int $branchId = null, array $filters = []): Collection
    {
        $items = SaleItem::query()
            ->with('product.category')
            ->whereHas('sale', function ($q) use ($from, $to, $branchId, $filters) {
                $q->where('status', Sale::STATUS_COMPLETED)
                    ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
                    ->when($branchId || ! empty($filters['branch_id']), function ($query) use ($branchId, $filters) {
                        $query->where('branch_id', (int) ($filters['branch_id'] ?? $branchId));
                    });
            })
            ->when(! empty($filters['category_id']), function ($q) use ($filters) {
                $ids = \App\Models\ProductCategory::idsIncludingChildren((int) $filters['category_id']);
                $q->whereHas('product', fn ($p) => $p->whereIn('category_id', $ids));
            })
            ->when(! empty($filters['brand_id']), function ($q) use ($filters) {
                $q->whereHas('product', fn ($p) => $p->where('brand_id', (int) $filters['brand_id']));
            })
            ->get();

        return $items->groupBy(fn (SaleItem $item) => optional(optional($item->product)->category)->name ?: 'Uncategorized')
            ->map(function ($group, $category) {
                return [
                    'category' => $category,
                    'qty' => round($group->sum(fn ($i) => (float) $i->quantity), 2),
                    'total' => round($group->sum(fn ($i) => (float) $i->line_total), 2),
                ];
            })
            ->values()
            ->map(fn ($row, $i) => array_merge(['index' => $i + 1], $row));
    }

    public function paymentRows(string $from, string $to, ?int $branchId = null, array $filters = []): Collection
    {
        return Payment::query()
            ->with(['user', 'customer', 'branch'])
            ->where('payable_type', Sale::class)
            ->whereBetween('paid_at', [$this->start($from, $filters['from_time'] ?? null), $this->end($to, $filters['to_time'] ?? null)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', (int) $filters['user_id']))
            ->when(! empty($filters['method']), fn ($q) => $q->where('method', $filters['method']))
            ->orderBy('paid_at')
            ->get()
            ->map(function (Payment $payment, int $i) {
                $sale = $payment->payable_type === Sale::class ? Sale::query()->find($payment->payable_id) : null;

                return [
                    'index' => $i + 1,
                    'branch' => optional($payment->branch)->name ?: '-',
                    'employee' => optional($payment->user)->name ?: '-',
                    'invoice' => optional($sale)->invoice_number ?: optional($sale)->receipt_number ?: optional($sale)->number ?: '-',
                    'pay_date' => optional($payment->paid_at)->format('d-m-Y'),
                    'created' => optional($payment->created_at)->format('d-m-Y H:i'),
                    'customer' => optional($payment->customer)->name ?: '-',
                    'pay_type' => ucfirst(str_replace('_', ' ', (string) $payment->method)),
                    'note' => $payment->notes ?: '-',
                    'amount' => round((float) $payment->amount, 2),
                ];
            })->values();
    }

    public function commissionRows(string $from, string $to, ?int $branchId = null, array $filters = []): Collection
    {
        $items = SaleItem::query()
            ->with(['sale.branch', 'product'])
            ->whereHas('sale', function ($q) use ($from, $to, $branchId, $filters) {
                $q->where('status', Sale::STATUS_COMPLETED)
                    ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->when(! empty($filters['user_id']), fn ($query) => $query->where('user_id', (int) $filters['user_id']));
            })
            ->when(! empty($filters['product_id']), fn ($q) => $q->where('product_id', (int) $filters['product_id']))
            ->orderBy('id')
            ->get();

        return $items->map(function (SaleItem $item, int $i) {
            $sale = $item->sale;
            $rate = (float) optional($item->product)->sales_commission;
            $line = (float) $item->line_total;
            $commission = round($rate > 0 ? ($line * $rate / 100) : 0, 2);

            return [
                'index' => $i + 1,
                'branch' => optional(optional($sale)->branch)->name ?: '-',
                'invoice' => optional($sale)->invoice_number ?: optional($sale)->receipt_number ?: optional($sale)->number,
                'sales_date' => optional(optional($sale)->sale_date)->format('d-m-Y'),
                'item' => $item->name,
                'qty' => round((float) $item->quantity, 2),
                'price' => round((float) $item->unit_price, 2),
                'discount' => round((float) $item->discount_amount, 2),
                'commission' => $commission,
            ];
        })->values();
    }

    public function returnRows(string $from, string $to, ?int $branchId = null, ?int $userId = null): Collection
    {
        $returns = SaleReturn::query()
            ->with(['branch', 'user', 'customer', 'items.product', 'sale'])
            ->whereBetween('return_date', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('return_date')
            ->get();

        $rows = collect();
        $index = 0;
        foreach ($returns as $return) {
            $items = $return->items->isNotEmpty() ? $return->items : collect([null]);
            foreach ($items as $item) {
                $index++;
                $rows->push([
                    'index' => $index,
                    'branch' => optional($return->branch)->name ?: '-',
                    'employee' => optional($return->user)->name ?: '-',
                    'invoice' => optional($return->sale)->invoice_number ?: optional($return->sale)->number ?: $return->number,
                    'return_date' => optional($return->return_date)->format('d-m-Y'),
                    'datetime' => optional($return->return_date)->format('d-m-Y H:i'),
                    'customer' => optional($return->customer)->name ?: '-',
                    'item' => $item ? (optional($item->product)->name ?: 'Item') : '-',
                    'qty' => $item ? round((float) $item->quantity, 2) : 0,
                    'note' => $return->notes ?: '-',
                    'stock_value' => $item ? round((float) $item->line_total, 2) : round((float) $return->refund_amount, 2),
                ]);
            }
        }

        return $rows->values();
    }

    public function cancelledRows(string $from, string $to, ?int $branchId = null, ?int $userId = null): Collection
    {
        $sales = Sale::query()
            ->with(['branch', 'customer', 'items'])
            ->where('status', Sale::STATUS_VOIDED)
            ->whereBetween('voided_at', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('voided_at')
            ->get();

        $rows = collect();
        $index = 0;
        foreach ($sales as $sale) {
            $items = $sale->items->isNotEmpty() ? $sale->items : collect([null]);
            foreach ($items as $item) {
                $index++;
                $rows->push([
                    'index' => $index,
                    'branch' => optional($sale->branch)->name ?: '-',
                    'invoice' => $sale->invoice_number ?: $sale->receipt_number ?: $sale->number,
                    'item' => $item ? $item->name : '-',
                    'sales_date' => optional($sale->sale_date)->format('d-m-Y'),
                    'customer' => optional($sale->customer)->name ?: 'Walk-in',
                    'qty' => $item ? round((float) $item->quantity, 2) : 0,
                    'price' => $item ? round((float) $item->unit_price, 2) : 0,
                    'total' => $item ? round((float) $item->line_total, 2) : round((float) $sale->total, 2),
                ]);
            }
        }

        return $rows->values();
    }

    public function complementaryRows(string $from, string $to, ?int $branchId = null, ?int $customerId = null): Collection
    {
        $saleIds = Payment::query()
            ->where('payable_type', Sale::class)
            ->where('method', Payment::METHOD_COMPLEMENTARY)
            ->whereBetween('paid_at', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->pluck('payable_id');

        return Sale::query()
            ->with(['customer', 'cashier'])
            ->whereIn('id', $saleIds)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderBy('sale_date')
            ->get()
            ->map(fn (Sale $sale, int $i) => [
                'index' => $i + 1,
                'invoice' => $sale->invoice_number ?: $sale->receipt_number ?: $sale->number,
                'sales_date' => optional($sale->sale_date)->format('d-m-Y'),
                'sales_person' => optional($sale->cashier)->name ?: '-',
                'customer' => optional($sale->customer)->name ?: 'Walk-in',
                'total' => round((float) $sale->total, 2),
            ])->values();
    }

    public function creditAgingRows(string $from, string $to, ?int $branchId = null, array $filters = []): Collection
    {
        $query = Sale::query()
            ->with(['customer', 'cashier', 'branch'])
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('balance', '>', 0)
            ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', (int) $filters['user_id']))
            ->when(! empty($filters['customer_id']), fn ($q) => $q->where('customer_id', (int) $filters['customer_id']))
            ->when(! empty($filters['payment_status']), fn ($q) => $q->where('payment_status', $filters['payment_status']))
            ->orderBy('sale_date');

        return $query->get()->map(function (Sale $sale, int $i) {
            $due = $sale->due_date ?: optional($sale->sale_date)->copy();
            $age = $due ? Carbon::parse($due)->startOfDay()->diffInDays(now()->startOfDay(), false) : 0;

            return [
                'index' => $i + 1,
                'invoice' => $sale->invoice_number ?: $sale->receipt_number ?: $sale->number,
                'sales_date' => optional($sale->sale_date)->format('d-m-Y'),
                'due_date' => optional($sale->due_date)->format('d-m-Y') ?: '-',
                'age' => max(0, (int) $age),
                'sales_person' => optional($sale->cashier)->name ?: '-',
                'customer' => optional($sale->customer)->name ?: 'Walk-in',
                'total' => round((float) $sale->total, 2),
                'paid' => round((float) $sale->paid_amount, 2),
                'balance' => round((float) $sale->balance, 2),
                'branch' => optional($sale->branch)->name ?: '-',
            ];
        })->values();
    }

    private function salesBase(string $from, string $to, ?int $branchId = null, array $filters = [])
    {
        return Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('sale_date', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! empty($filters['customer_id']), fn ($q) => $q->where('customer_id', (int) $filters['customer_id']))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', (int) $filters['user_id']))
            ->when(! empty($filters['payment_status']), fn ($q) => $q->where('payment_status', $filters['payment_status']))
            ->when(($filters['kra'] ?? '') === 'with', fn ($q) => $q->whereHas('customer', fn ($c) => $c->whereNotNull('tax_number')->where('tax_number', '!=', '')))
            ->when(($filters['kra'] ?? '') === 'without', fn ($q) => $q->where(function ($q2) {
                $q2->whereDoesntHave('customer')->orWhereHas('customer', fn ($c) => $c->whereNull('tax_number')->orWhere('tax_number', ''));
            }));
    }

    private function start(string $date, ?string $time = null): Carbon
    {
        $dt = Carbon::parse($date)->startOfDay();
        if ($time && preg_match('/^\d{1,2}:\d{2}/', $time)) {
            [$h, $m] = array_map('intval', explode(':', $time));
            $dt->setTime($h, $m, 0);
        }

        return $dt;
    }

    private function end(string $date, ?string $time = null): Carbon
    {
        $dt = Carbon::parse($date)->endOfDay();
        if ($time && preg_match('/^\d{1,2}:\d{2}/', $time)) {
            [$h, $m] = array_map('intval', explode(':', $time));
            $dt->setTime($h, $m, 59);
        }

        return $dt;
    }
}
