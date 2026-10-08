<?php

namespace App\Services;

use App\Exceptions\NegativeStockException;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class InventoryService
{
    /**
     * Apply a stock change and write a movement. This is the only allowed
     * way to change product_branch_stock.quantity.
     *
     * @param  array{
     *     company_id:int,
     *     branch_id:int,
     *     product_id:int,
     *     product_variant_id?:int,
     *     type:string,
     *     quantity_in?:float|int|string,
     *     quantity_out?:float|int|string,
     *     unit_cost?:float|int|string|null,
     *     reference_type?:string|null,
     *     reference_id?:int|null,
     *     reference_number?:string|null,
     *     user_id?:int|null,
     *     notes?:string|null,
     *     occurred_at?:\DateTimeInterface|string|null
     * }  $payload
     */
    public function apply(array $payload): StockMovement
    {
        return DB::transaction(function () use ($payload) {
            $variantId = (int) ($payload['product_variant_id'] ?? 0);

            $stock = ProductBranchStock::query()
                ->withoutGlobalScope('company')
                ->withoutGlobalScope('branch')
                ->where('company_id', $payload['company_id'])
                ->where('branch_id', $payload['branch_id'])
                ->where('product_id', $payload['product_id'])
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = ProductBranchStock::query()
                    ->withoutGlobalScope('company')
                    ->withoutGlobalScope('branch')
                    ->create([
                        'company_id' => $payload['company_id'],
                        'branch_id' => $payload['branch_id'],
                        'product_id' => $payload['product_id'],
                        'product_variant_id' => $variantId,
                        'quantity' => 0,
                        'average_cost' => 0,
                    ]);

                $stock = ProductBranchStock::query()
                    ->withoutGlobalScope('company')
                    ->withoutGlobalScope('branch')
                    ->whereKey($stock->id)
                    ->lockForUpdate()
                    ->first();
            }

            $qtyIn = round((float) ($payload['quantity_in'] ?? 0), 4);
            $qtyOut = round((float) ($payload['quantity_out'] ?? 0), 4);
            $before = (float) $stock->quantity;
            $after = round($before + $qtyIn - $qtyOut, 4);

            $product = Product::query()->withoutGlobalScope('company')->find($payload['product_id']);

            $allowNegative = array_key_exists('allow_negative', $payload)
                ? (bool) $payload['allow_negative']
                : (bool) ($product && $product->allow_negative_stock);
            if ($after < 0 && $product && $product->manage_stock && ! $allowNegative) {
                throw NegativeStockException::forShortage($product->name, max(0, $before), $qtyOut);
            }

            if ($qtyIn > 0 && isset($payload['unit_cost']) && $payload['unit_cost'] !== null) {
                $incomingCost = (float) $payload['unit_cost'];
                $currentValue = $before * (float) $stock->average_cost;
                $incomingValue = $qtyIn * $incomingCost;
                $newQty = $before + $qtyIn;
                $stock->average_cost = $newQty > 0
                    ? round(($currentValue + $incomingValue) / $newQty, 4)
                    : $incomingCost;
            }

            $stock->quantity = $after;
            $stock->save();

            return StockMovement::query()
                ->withoutGlobalScope('company')
                ->withoutGlobalScope('branch')
                ->create([
                    'company_id' => $payload['company_id'],
                    'branch_id' => $payload['branch_id'],
                    'product_id' => $payload['product_id'],
                    'product_variant_id' => $variantId,
                    'type' => $payload['type'],
                    'quantity_in' => $qtyIn,
                    'quantity_out' => $qtyOut,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'unit_cost' => $payload['unit_cost'] ?? $stock->average_cost,
                    'reference_type' => $payload['reference_type'] ?? null,
                    'reference_id' => $payload['reference_id'] ?? null,
                    'reference_number' => $payload['reference_number'] ?? null,
                    'user_id' => $payload['user_id'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'occurred_at' => $payload['occurred_at'] ?? Carbon::now(),
                ]);
        });
    }

    /**
     * Net quantity sold or consumed (sales, folding, issues, damage, conversion, reductions),
     * after returns and folding reversals. Keys are "productId:variantId".
     *
     * @param  array<int, int>|Illuminate\Support\Collection  $productIds
     * @return array<string, float>
     */
    public function consumedByVariant(?int $branchId, $productIds): array
    {
        $productIds = collect($productIds)->filter()->values();
        if ($productIds->isEmpty()) {
            return [];
        }

        $base = StockMovement::query()
            ->withoutGlobalScope('branch')
            ->whereIn('product_id', $productIds)
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            });

        $out = (clone $base)
            ->whereIn('type', [
                StockMovement::SALE,
                StockMovement::POS_SALE,
                StockMovement::FOLDING,
                StockMovement::ISSUE,
                StockMovement::DAMAGE,
                StockMovement::CONVERSION,
                StockMovement::ADJUSTMENT,
            ])
            ->selectRaw('product_id, product_variant_id, SUM(quantity_out) as quantity')
            ->groupBy('product_id', 'product_variant_id')
            ->get();

        $back = (clone $base)
            ->whereIn('type', [
                StockMovement::SALE_RETURN,
                StockMovement::SALE_VOID,
                StockMovement::FOLDING_REVERSAL,
            ])
            ->selectRaw('product_id, product_variant_id, SUM(quantity_in) as quantity')
            ->groupBy('product_id', 'product_variant_id')
            ->get();

        $used = [];
        foreach ($out as $row) {
            $key = $row->product_id . ':' . (int) $row->product_variant_id;
            $used[$key] = round((float) $row->quantity, 4);
        }
        foreach ($back as $row) {
            $key = $row->product_id . ':' . (int) $row->product_variant_id;
            $used[$key] = round(($used[$key] ?? 0) - (float) $row->quantity, 4);
        }

        foreach ($used as $key => $quantity) {
            $used[$key] = $quantity > 0 ? $quantity : 0.0;
        }

        return $used;
    }

    public function quantityOnHand(int $companyId, int $branchId, int $productId, int $variantId = 0): float
    {
        $stock = ProductBranchStock::query()
            ->withoutGlobalScope('company')
            ->withoutGlobalScope('branch')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        return $stock ? (float) $stock->quantity : 0.0;
    }

    public function reverse(StockMovement $movement): void
    {
        DB::transaction(function () use ($movement) {
            $variantId = (int) ($movement->product_variant_id ?? 0);

            $stock = ProductBranchStock::query()
                ->withoutGlobalScope('company')
                ->withoutGlobalScope('branch')
                ->where('company_id', $movement->company_id)
                ->where('branch_id', $movement->branch_id)
                ->where('product_id', $movement->product_id)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $stock->quantity = round(
                    (float) $stock->quantity + (float) $movement->quantity_out - (float) $movement->quantity_in,
                    4
                );
                $stock->save();
            }

            $movement->delete();
        });
    }
}
