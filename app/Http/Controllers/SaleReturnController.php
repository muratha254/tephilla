<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleReturnController extends Controller
{
    public function index()
    {
        $this->authorizePermission('sales.return');

        $returns = SaleReturn::query()
            ->with(['sale', 'customer', 'user', 'items'])
            ->orderByDesc('id')
            ->get();

        return view('sales.returns', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'sales.returns',
            'returns' => $returns,
        ]));
    }

    public function form(Sale $sale)
    {
        $this->authorizePermission('sales.return');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can be returned.');

        return response()->json($this->returnablePayload($sale));
    }

    public function store(Request $request, Sale $sale, DocumentNumberService $numbers, InventoryService $inventory)
    {
        $this->authorizePermission('sales.return');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can be returned.');

        $data = $request->validate([
            'receipt_ref' => 'required|string|max:64',
            'want_refund' => 'required|in:0,1',
            'notes' => 'required|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.condition' => 'nullable|in:yes,no',
        ]);

        $payload = $this->returnablePayload($sale);
        $available = collect($payload['items'])->keyBy('sale_item_id');
        $lines = [];

        foreach ($data['items'] as $row) {
            $qty = round((float) ($row['quantity'] ?? 0), 4);
            if ($qty <= 0) {
                continue;
            }

            $item = $available->get((int) $row['sale_item_id']);
            if (! $item || $qty - $item['available'] > 0.0001) {
                return back()->with('error', 'Return quantity exceeds what can be returned.');
            }
            if (empty($row['condition'])) {
                return back()->with('error', 'Select Good Condition for each item being returned.');
            }

            $lines[] = [
                'sale_item_id' => (int) $item['sale_item_id'],
                'product_id' => $item['product_id'] ? (int) $item['product_id'] : null,
                'product_variant_id' => (int) $item['product_variant_id'],
                'quantity' => $qty,
                'unit_price' => (float) $item['unit_price'],
                'line_total' => round($qty * (float) $item['unit_price'], 2),
                'condition' => $row['condition'] === 'yes' ? 'good' : 'damaged',
                'restore_stock' => $row['condition'] === 'yes',
                'manage_stock' => (bool) $item['manage_stock'],
                'cost_price' => (float) $item['cost_price'],
            ];
        }

        if (count($lines) === 0) {
            return back()->with('error', 'Enter a quantity to return.');
        }

        $wantRefund = (int) $data['want_refund'] === 1;
        $refundAmount = $wantRefund ? round(collect($lines)->sum('line_total'), 2) : 0;
        $restoreAny = collect($lines)->contains(function ($line) {
            return $line['restore_stock'];
        });

        try {
            DB::transaction(function () use ($sale, $data, $lines, $numbers, $inventory, $wantRefund, $refundAmount, $restoreAny) {
                $return = SaleReturn::query()->create([
                    'company_id' => $sale->company_id,
                    'branch_id' => $sale->branch_id,
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'user_id' => auth()->id(),
                    'number' => $numbers->next((int) $sale->company_id, 'sale_return'),
                    'return_date' => now(),
                    'status' => 'completed',
                    'refund_method' => $wantRefund ? 'cash' : null,
                    'refund_amount' => $refundAmount,
                    'restore_stock' => $restoreAny,
                    'notes' => $data['notes'],
                ]);

                foreach ($lines as $line) {
                    SaleReturnItem::query()->create([
                        'company_id' => $sale->company_id,
                        'sale_return_id' => $return->id,
                        'sale_item_id' => $line['sale_item_id'],
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $line['product_variant_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => $line['line_total'],
                        'condition' => $line['condition'],
                    ]);

                    if ($line['restore_stock'] && $line['product_id'] && $line['manage_stock']) {
                        $inventory->apply([
                            'company_id' => (int) $sale->company_id,
                            'branch_id' => (int) $sale->branch_id,
                            'product_id' => $line['product_id'],
                            'product_variant_id' => $line['product_variant_id'],
                            'type' => StockMovement::SALE_RETURN,
                            'quantity_in' => $line['quantity'],
                            'unit_cost' => $line['cost_price'],
                            'user_id' => auth()->id(),
                            'notes' => 'Sale return ' . $return->number,
                            'reference_type' => SaleReturn::class,
                            'reference_id' => $return->id,
                            'reference_number' => $return->number,
                            'occurred_at' => $return->return_date,
                        ]);
                    }
                }

                if ($wantRefund && $refundAmount > 0) {
                    $paid = max(0, round((float) $sale->paid_amount - $refundAmount, 2));
                    $total = max(0, round((float) $sale->total - $refundAmount, 2));
                    $sale->update([
                        'paid_amount' => $paid,
                        'total' => $total,
                    ]);
                    $this->syncPaymentState($sale->fresh());
                }

                $sale->load('items');
                $returned = SaleReturnItem::query()
                    ->selectRaw('sale_item_id, SUM(quantity) as qty')
                    ->whereHas('saleReturn', function ($query) use ($sale) {
                        $query->where('sale_id', $sale->id);
                    })
                    ->groupBy('sale_item_id')
                    ->pluck('qty', 'sale_item_id');

                $fullyReturned = $sale->items->every(function (SaleItem $item) use ($returned) {
                    return ((float) $item->quantity) - (float) ($returned[$item->id] ?? 0) <= 0.0001;
                });

                if ($fullyReturned) {
                    $sale->update(['status' => Sale::STATUS_RETURNED]);
                }
            });
        } catch (NegativeStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('sales.returns')
            ->with('success', 'Sales return saved.');
    }

    private function returnablePayload(Sale $sale): array
    {
        $sale->load(['items.product', 'customer']);

        $returned = SaleReturnItem::query()
            ->selectRaw('sale_item_id, SUM(quantity) as qty')
            ->whereHas('saleReturn', function ($query) use ($sale) {
                $query->where('sale_id', $sale->id);
            })
            ->groupBy('sale_item_id')
            ->pluck('qty', 'sale_item_id');

        $items = $sale->items->map(function (SaleItem $item) use ($returned) {
            $sold = (float) $item->quantity;
            $already = (float) ($returned[$item->id] ?? 0);
            $available = round(max(0, $sold - $already), 4);

            return [
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => (int) $item->product_variant_id,
                'name' => $item->name,
                'sold_qty' => $sold,
                'returned_qty' => $already,
                'available' => $available,
                'unit_price' => (float) $item->unit_price,
                'cost_price' => (float) $item->cost_price,
                'manage_stock' => optional($item->product)->manage_stock ? true : false,
            ];
        })->filter(function ($item) {
            return $item['available'] > 0;
        })->values();

        return [
            'id' => $sale->id,
            'number' => $sale->documentNumber(),
            'customer' => $sale->customerDisplayName(),
            'store_url' => route('sales.returns.store', $sale),
            'items' => $items,
        ];
    }

    private function syncPaymentState(Sale $sale): void
    {
        $paid = (float) $sale->paid_amount;
        $total = (float) $sale->total;
        $balance = round(max(0, $total - $paid), 2);

        $sale->update([
            'balance' => $balance,
            'payment_status' => $paid <= 0
                ? Sale::PAYMENT_UNPAID
                : ($balance <= 0.009 ? Sale::PAYMENT_PAID : Sale::PAYMENT_PARTIAL),
        ]);
    }
}
