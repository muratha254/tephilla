<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBranchStock;
use Illuminate\Support\Collection;

class StockAlertService
{
    /**
     * Active stocked products at the branch with quantity <= 0.
     */
    public function outOfStock(?int $branchId, ?int $limit = null): Collection
    {
        if (! $branchId) {
            return collect();
        }

        $query = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->with(['product:id,name,sku,reorder_level,manage_stock,is_active'])
            ->where('branch_id', $branchId)
            ->where('quantity', '<=', 0)
            ->whereHas('product', function ($builder) {
                $builder->where('is_active', true)->where('manage_stock', true);
            })
            ->orderBy('quantity')
            ->orderBy('product_id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(function (ProductBranchStock $row) {
            $product = $row->product;
            if (! $product) {
                return null;
            }

            return (object) [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'quantity' => (float) $row->quantity,
                'reorder_level' => (float) $product->reorder_level,
            ];
        })->filter()->values();
    }

    public function outOfStockCount(?int $branchId): int
    {
        if (! $branchId) {
            return 0;
        }

        return (int) ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->where('quantity', '<=', 0)
            ->whereHas('product', function ($builder) {
                $builder->where('is_active', true)->where('manage_stock', true);
            })
            ->count();
    }

    /**
     * Products at or below reorder level (includes zero / negative).
     */
    public function lowStock(?int $branchId): Collection
    {
        if (! $branchId) {
            return collect();
        }

        $stock = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id');

        return Product::query()
            ->with(['category', 'brand'])
            ->availableAtBranch($branchId)
            ->where('is_active', true)
            ->where('manage_stock', true)
            ->orderBy('name')
            ->get()
            ->filter(function (Product $product) use ($stock) {
                $onHand = (float) ($stock[$product->id] ?? 0);
                $product->stock_on_hand = $onHand;
                $reorder = (float) $product->reorder_level;

                if ($onHand <= 0) {
                    return true;
                }

                return $reorder > 0 && $onHand <= $reorder;
            })
            ->sortBy(function (Product $product) {
                return [(float) $product->stock_on_hand, $product->name];
            })
            ->values();
    }
}
