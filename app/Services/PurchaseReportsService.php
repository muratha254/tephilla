<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PurchaseReportsService
{
    public function supplierOptions(): Collection
    {
        return Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function productOptions(): Collection
    {
        return Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name']);
    }

    public function statuses(): array
    {
        return [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_PENDING,
            PurchaseOrder::STATUS_ORDERED,
            PurchaseOrder::STATUS_SENT,
            PurchaseOrder::STATUS_PARTIAL,
            PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_CANCELLED,
        ];
    }

    public function purchaseRows(string $from, string $to, ?int $branchId = null, array $filters = []): Collection
    {
        $orders = $this->ordersQuery($from, $to, $branchId, $filters)
            ->with(['supplier', 'branch', 'items.product'])
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        $rows = collect();
        $index = 0;

        foreach ($orders as $order) {
            $wht = 0.0;
            if (optional($order->supplier)->apply_withholding) {
                $wht = round((float) $order->total * 0.02, 2);
            }

            $items = $order->items->isNotEmpty() ? $order->items : collect([null]);

            foreach ($items as $item) {
                $index++;
                $rows->push([
                    'index' => $index,
                    'branch' => optional($order->branch)->name ?: '-',
                    'invoice' => $order->number,
                    'purchase_date' => optional($order->order_date)->format('d-m-Y'),
                    'supplier' => optional($order->supplier)->name ?: '-',
                    'item' => $item
                        ? (optional($item->product)->name ?: ($item->description ?: '-'))
                        : '-',
                    'vat' => round((float) ($item ? $item->tax_amount : $order->tax_amount), 2),
                    'total' => round((float) ($item ? $item->line_total : $order->total), 2),
                    'wht' => $wht,
                    'paid' => round((float) $order->paid_amount, 2),
                    'due' => $order->balance(),
                    'status' => $order->status,
                ]);
            }
        }

        return $rows->values();
    }

    public function itemPurchaseRows(string $from, string $to, ?int $branchId = null, array $filters = []): Collection
    {
        $query = PurchaseOrderItem::query()
            ->with(['product', 'purchaseOrder.supplier', 'purchaseOrder.branch'])
            ->whereHas('purchaseOrder', function ($q) use ($from, $to, $branchId, $filters) {
                $q->whereBetween('order_date', [$from, $to])
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->when(! empty($filters['supplier_id']), fn ($query) => $query->where('supplier_id', (int) $filters['supplier_id']))
                    ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']));
            })
            ->when(! empty($filters['product_id']), fn ($q) => $q->where('product_id', (int) $filters['product_id']))
            ->orderBy('id');

        return $query->get()->map(function (PurchaseOrderItem $item, int $i) {
            $order = $item->purchaseOrder;
            $expenseShare = 0.0;
            if ($order && (float) $order->expense_amount > 0 && (float) $order->subtotal > 0) {
                $expenseShare = round(((float) $item->line_total / (float) $order->subtotal) * (float) $order->expense_amount, 2);
            }

            return [
                'index' => $i + 1,
                'branch' => optional(optional($order)->branch)->name ?: '-',
                'invoice' => optional($order)->number ?: '-',
                'purchase_date' => optional(optional($order)->order_date)->format('d-m-Y'),
                'item' => optional($item->product)->name ?: ($item->description ?: '-'),
                'supplier' => optional(optional($order)->supplier)->name ?: '-',
                'status' => optional($order)->status ?: '-',
                'ordered_qty' => round((float) $item->quantity, 2),
                'received_qty' => round((float) $item->quantity_received, 2),
                'unit_price' => round((float) $item->unit_cost, 2),
                'expense' => $expenseShare,
                'grand_total' => round((float) $item->line_total + $expenseShare, 2),
            ];
        })->values();
    }

    public function paymentRows(string $from, string $to, ?int $branchId = null, ?int $supplierId = null): Collection
    {
        return Payment::query()
            ->with(['supplier', 'branch'])
            ->where('payable_type', PurchaseOrder::class)
            ->whereBetween('paid_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderBy('paid_at')
            ->get()
            ->map(function (Payment $payment, int $i) {
                $order = PurchaseOrder::query()->find($payment->payable_id);

                return [
                    'index' => $i + 1,
                    'branch' => optional($payment->branch)->name ?: '-',
                    'invoice' => optional($order)->number ?: ($payment->number ?: '-'),
                    'payment_date' => optional($payment->paid_at)->format('d-m-Y'),
                    'supplier_id' => $payment->supplier_id ?: '-',
                    'supplier' => optional($payment->supplier)->name ?: '-',
                    'payment_type' => ucfirst(str_replace('_', ' ', (string) $payment->method)),
                    'note' => $payment->notes ?: '-',
                    'amount' => round((float) $payment->amount, 2),
                ];
            })->values();
    }

    private function ordersQuery(string $from, string $to, ?int $branchId = null, array $filters = [])
    {
        return PurchaseOrder::query()
            ->whereBetween('order_date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', (int) $filters['supplier_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']));
    }
}
