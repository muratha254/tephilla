<?php

namespace App\Services;

use App\Exceptions\NegativeStockException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private InventoryService $inventory,
        private AccountingPoster $accounting,
        private AuditLogger $audit
    ) {
    }

    public function returnablePayload(PurchaseOrder $purchase): array
    {
        $purchase->load(['items.product', 'supplier']);

        $returned = PurchaseReturnItem::query()
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->whereHas('purchaseReturn', function ($query) use ($purchase) {
                $query->where('purchase_order_id', $purchase->id);
            })
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $items = $purchase->items->map(function (PurchaseOrderItem $item) use ($returned) {
            $received = (float) $item->quantity_received;
            $already = (float) ($returned[$item->product_id] ?? 0);
            $available = round(max(0, $received - $already), 4);

            return [
                'product_id' => $item->product_id,
                'name' => optional($item->product)->name ?: $item->description,
                'received' => $received,
                'returned' => $already,
                'available' => $available,
                'unit_cost' => (float) $item->unit_cost,
            ];
        })->filter(function ($item) {
            return $item['available'] > 0;
        })->values();

        return [
            'id' => $purchase->id,
            'number' => $purchase->number,
            'supplier' => optional($purchase->supplier)->name,
            'store_url' => route('purchases.returns.store', $purchase),
            'items' => $items,
        ];
    }

    /**
     * @param  array{return_date:string, notes?:string|null, items:array<int, array{product_id:int|string, quantity?:float|int|string|null}>}  $data
     */
    public function createFromPurchase(PurchaseOrder $purchase, array $data): PurchaseReturn
    {
        $payload = $this->returnablePayload($purchase);
        $available = collect($payload['items'])->keyBy('product_id');
        $lines = [];

        foreach ($data['items'] as $row) {
            $qty = round((float) ($row['quantity'] ?? 0), 4);
            if ($qty <= 0) {
                continue;
            }
            $item = $available->get((int) $row['product_id']);
            if (! $item || $qty - $item['available'] > 0.0001) {
                throw new \InvalidArgumentException('Return quantity exceeds what can be returned.');
            }
            $lines[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => $qty,
                'unit_cost' => (float) $item['unit_cost'],
                'line_total' => round($qty * (float) $item['unit_cost'], 2),
            ];
        }

        if (count($lines) === 0) {
            throw new \InvalidArgumentException('Enter a quantity to return.');
        }

        return DB::transaction(function () use ($purchase, $data, $lines) {
            $return = PurchaseReturn::query()->create([
                'company_id' => $purchase->company_id,
                'branch_id' => $purchase->branch_id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_order_id' => $purchase->id,
                'goods_receipt_id' => optional($purchase->receipts()->latest('id')->first())->id,
                'user_id' => auth()->id(),
                'number' => $this->numbers->next($purchase->company_id, 'purchase_return'),
                'return_date' => $data['return_date'],
                'status' => 'completed',
                'total' => collect($lines)->sum('line_total'),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                PurchaseReturnItem::query()->create(array_merge($line, [
                    'company_id' => $purchase->company_id,
                    'purchase_return_id' => $return->id,
                ]));

                $this->inventory->apply([
                    'company_id' => $purchase->company_id,
                    'branch_id' => $purchase->branch_id,
                    'product_id' => $line['product_id'],
                    'type' => StockMovement::PURCHASE_RETURN,
                    'quantity_out' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'user_id' => auth()->id(),
                    'notes' => 'Debit note / purchase return ' . $return->number,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $return->id,
                    'reference_number' => $return->number,
                    'occurred_at' => $return->return_date,
                ]);
            }

            $returnTotal = round((float) $return->total, 2);
            $this->accounting->postPurchaseReturn($return->fresh(['items', 'purchaseOrder.receipts.items']));

            $purchase->refresh();
            $newTotal = round(max(0, (float) $purchase->total - $returnTotal), 2);
            $newPaid = round(min((float) $purchase->paid_amount, $newTotal), 2);
            $purchase->update([
                'total' => $newTotal,
                'paid_amount' => $newPaid,
            ]);

            $this->audit->record('create', 'purchases', $return, null, [
                'number' => $return->number,
                'total' => $return->total,
                'type' => 'debit_note',
            ]);

            return $return->fresh(['items', 'supplier', 'purchaseOrder']);
        });
    }

    public function returnablePurchases()
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->whereIn('status', [
                PurchaseOrder::STATUS_RECEIVED,
                PurchaseOrder::STATUS_PARTIAL,
                'ordered',
            ])
            ->whereHas('items', function ($query) {
                $query->where('quantity_received', '>', 0);
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get()
            ->filter(function (PurchaseOrder $purchase) {
                return count($this->returnablePayload($purchase)['items']) > 0;
            })
            ->values();
    }
}
