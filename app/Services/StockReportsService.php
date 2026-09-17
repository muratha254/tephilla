<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Production;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StockReportsService
{
    public function categoryOptions(): Collection
    {
        return ProductCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function brandOptions(): Collection
    {
        return Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function productOptions(): Collection
    {
        return Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'name', 'sku']);
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

    public function priceListRows(?int $branchId = null, array $filters = []): Collection
    {
        $showCost = ($filters['display_cost'] ?? 'hide') !== 'hide';
        $priceField = match ($filters['price_category'] ?? 'retail') {
            'wholesale' => 'wholesale_price',
            'promo' => 'promo_price',
            default => 'selling_price',
        };

        return $this->productQuery($filters)
            ->with(['category', 'brand', 'unit', 'tax', 'parent', 'branchStock'])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product, int $i) use ($branchId, $showCost, $priceField) {
                $stock = $this->productStock($product, $branchId);

                return [
                    'index' => $i + 1,
                    'category' => optional($product->category)->name ?: '-',
                    'brand' => optional($product->brand)->name ?: '-',
                    'item' => $product->name,
                    'sku' => $product->sku ?: '-',
                    'purchase_price' => $showCost ? round((float) $product->purchase_price, 2) : null,
                    'selling_price' => round((float) $product->{$priceField}, 2),
                    'wholesale_price' => round((float) $product->wholesale_price, 2),
                    'promo_price' => round((float) $product->promo_price, 2),
                    'stock' => round($stock, 2),
                ];
            })->values();
    }

    public function stockRows(?int $branchId = null, array $filters = []): Collection
    {
        return $this->productQuery($filters)
            ->with(['category', 'brand'])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product, int $i) use ($branchId) {
                $stock = $this->productStock($product, $branchId);

                return [
                    'index' => $i + 1,
                    'item_code' => $product->item_code,
                    'item' => $product->name,
                    'category' => optional($product->category)->name ?: '-',
                    'brand' => optional($product->brand)->name ?: '-',
                    'purchase_price' => round((float) $product->purchase_price, 2),
                    'selling_price' => round((float) $product->selling_price, 2),
                    'stock' => round($stock, 2),
                    'reorder' => round((float) $product->reorder_level, 2),
                    'value' => round($stock * (float) $product->purchase_price, 2),
                ];
            })->values();
    }

    public function stockAsAtRows(string $date, ?int $branchId = null, array $filters = [], bool $detailed = false): Collection
    {
        $asAt = Carbon::parse($date)->endOfDay();

        return $this->productQuery($filters)
            ->with(['category', 'brand'])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product, int $i) use ($branchId, $asAt, $detailed) {
                $stock = $this->stockAsAt($product->id, $branchId, $asAt);

                $row = [
                    'index' => $i + 1,
                    'item' => $product->name,
                    'category' => optional($product->category)->name ?: '-',
                    'brand' => optional($product->brand)->name ?: '-',
                    'purchase_price' => round((float) $product->purchase_price, 2),
                    'selling_price' => round((float) $product->selling_price, 2),
                    'stock' => round($stock, 2),
                    'value' => round($stock * (float) $product->purchase_price, 2),
                ];

                if ($detailed) {
                    $row['item_code'] = $product->item_code;
                    $row['sku'] = $product->sku ?: '-';
                    $row['reorder'] = round((float) $product->reorder_level, 2);
                    $row['wholesale_price'] = round((float) $product->wholesale_price, 2);
                }

                return $row;
            })->values();
    }

    public function templateRows(?int $branchId = null, array $filters = []): Collection
    {
        $order = ($filters['ordering'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $this->productQuery($filters)
            ->with(['category', 'unit', 'tax', 'parent'])
            ->orderBy('name', $order)
            ->get()
            ->map(function (Product $product, int $i) use ($branchId) {
                $stock = $this->productStock($product, $branchId);

                return [
                    'index' => $i + 1,
                    'product_id' => $product->id,
                    'product_code' => $product->item_code,
                    'product_name' => $product->name,
                    'sku' => $product->sku ?: '-',
                    'category' => optional($product->category)->name ?: '-',
                    'buying_price' => round((float) $product->purchase_price, 2),
                    'selling_price' => round((float) $product->selling_price, 2),
                    'wholesale_price' => round((float) $product->wholesale_price, 2),
                    'promo_price' => round((float) $product->promo_price, 2),
                    'current_stock' => round($stock, 2),
                    'reorder' => round((float) $product->reorder_level, 2),
                    'parent' => optional($product->parent)->name ?: '-',
                    'conversion_rate' => round((float) $product->conversion_rate, 2),
                    'uom' => optional($product->unit)->name ?: '-',
                    'tax' => $product->taxLabel(),
                ];
            })->values();
    }

    public function ledgerRows(string $from, string $to, ?int $branchId = null, ?int $productId = null): Collection
    {
        return $this->movementQuery($from, $to, $branchId, $productId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(function (StockMovement $movement, int $i) {
                $product = $movement->product;

                return [
                    'index' => $i + 1,
                    'branch' => optional($movement->branch)->name ?: '-',
                    'item' => optional($product)->name ?: '-',
                    'trans_date' => optional($movement->occurred_at)->format('d-m-Y'),
                    'action' => ucfirst(str_replace('_', ' ', (string) $movement->type)),
                    'description' => $movement->notes ?: '-',
                    'purchase_price' => round((float) $movement->unit_cost, 2),
                    'sales_price' => round((float) optional($product)->selling_price, 2),
                    'reference' => $movement->reference_number ?: '-',
                    'stock_in' => round((float) $movement->quantity_in, 2),
                    'stock_out' => round((float) $movement->quantity_out, 2),
                    'balance' => round((float) $movement->quantity_after, 2),
                ];
            })->values();
    }

    public function transferRows(string $from, string $to, ?int $branchId = null, ?int $productId = null): Collection
    {
        return StockMovement::query()
            ->with(['product', 'branch', 'user'])
            ->whereIn('type', [StockMovement::TRANSFER_OUT, StockMovement::TRANSFER_IN])
            ->whereBetween('occurred_at', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->orderBy('occurred_at')
            ->get()
            ->map(function (StockMovement $movement, int $i) {
                $product = $movement->product;
                $qty = (float) $movement->quantity_in + (float) $movement->quantity_out;

                return [
                    'index' => $i + 1,
                    'from_branch' => $movement->type === StockMovement::TRANSFER_OUT ? (optional($movement->branch)->name ?: '-') : '-',
                    'to_branch' => $movement->type === StockMovement::TRANSFER_IN ? (optional($movement->branch)->name ?: '-') : '-',
                    'transfer_by' => optional($movement->user)->name ?: '-',
                    'item' => optional($product)->name ?: '-',
                    'trans_date' => optional($movement->occurred_at)->format('d-m-Y'),
                    'purchase_price' => round((float) $movement->unit_cost, 2),
                    'sales_price' => round((float) optional($product)->selling_price, 2),
                    'quantity' => round($qty, 2),
                ];
            })->values();
    }

    public function adjustmentRows(string $from, string $to, ?int $branchId = null, ?int $productId = null): Collection
    {
        return StockMovement::query()
            ->with(['product', 'branch', 'user'])
            ->where('type', StockMovement::ADJUSTMENT)
            ->whereBetween('occurred_at', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->orderBy('occurred_at')
            ->get()
            ->map(function (StockMovement $movement, int $i) {
                $product = $movement->product;
                $cost = (float) $movement->unit_cost;
                $qty = (float) $movement->quantity_in + (float) $movement->quantity_out;

                return [
                    'index' => $i + 1,
                    'branch' => optional($movement->branch)->name ?: '-',
                    'item' => optional($product)->name ?: '-',
                    'trans_date' => optional($movement->occurred_at)->format('d-m-Y'),
                    'action' => 'Adjustment',
                    'description' => $movement->notes ?: '-',
                    'purchase_price' => round($cost, 2),
                    'cost_tax_inc' => round($cost, 2),
                    'sales_price' => round((float) optional($product)->selling_price, 2),
                    'stock_in' => round((float) $movement->quantity_in, 2),
                    'stock_out' => round((float) $movement->quantity_out, 2),
                    'balance' => round((float) $movement->quantity_after, 2),
                    'value' => round($qty * $cost, 2),
                    'created_by' => optional($movement->user)->name ?: '-',
                ];
            })->values();
    }

    public function alertRows(?int $branchId = null): Collection
    {
        return Product::query()
            ->with(['category', 'brand', 'branchStock'])
            ->where('is_active', true)
            ->where('manage_stock', true)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($branchId) {
                $stock = $this->productStock($product, $branchId);

                return [
                    'product' => $product,
                    'stock' => $stock,
                ];
            })
            ->filter(fn ($row) => $row['stock'] <= (float) $row['product']->reorder_level)
            ->values()
            ->map(function ($row, int $i) use ($branchId) {
                $product = $row['product'];
                $branchName = $branchId
                    ? (optional(Branch::query()->find($branchId))->name ?: '-')
                    : '-';

                return [
                    'index' => $i + 1,
                    'branch' => $branchName,
                    'item_code' => $product->item_code,
                    'item' => $product->name,
                    'unit_price' => round((float) $product->purchase_price, 2),
                    'sales_price' => round((float) $product->selling_price, 2),
                    'reorder' => round((float) $product->reorder_level, 2),
                    'current_stock' => round($row['stock'], 2),
                ];
            });
    }

    public function valuationRows(?int $branchId = null, array $filters = []): Collection
    {
        return $this->productQuery($filters)
            ->with(['category', 'brand'])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product, int $i) use ($branchId) {
                $stock = $this->productStock($product, $branchId);
                $cost = (float) $product->purchase_price;

                return [
                    'index' => $i + 1,
                    'item' => $product->name,
                    'category' => optional($product->category)->name ?: '-',
                    'brand' => optional($product->brand)->name ?: '-',
                    'stock' => round($stock, 2),
                    'unit_cost' => round($cost, 2),
                    'value' => round($stock * $cost, 2),
                    'sales_value' => round($stock * (float) $product->selling_price, 2),
                ];
            })->values();
    }

    public function damagedRows(string $from, string $to, ?int $branchId = null, ?int $productId = null): Collection
    {
        return $this->movementByTypeRows($from, $to, StockMovement::DAMAGE, $branchId, $productId, 'total_sales');
    }

    public function issuedRows(string $from, string $to, ?int $branchId = null, ?int $productId = null): Collection
    {
        return $this->movementByTypeRows($from, $to, StockMovement::ISSUE, $branchId, $productId, 'total_cost');
    }

    public function consumptionRows(string $from, string $to, ?int $branchId = null, ?int $productId = null): Collection
    {
        return StockMovement::query()
            ->with(['product', 'reference'])
            ->where('type', StockMovement::ISSUE)
            ->where('reference_type', Production::class)
            ->whereBetween('occurred_at', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->orderBy('occurred_at')
            ->get()
            ->map(function (StockMovement $movement, int $i) {
                $production = $movement->reference_type === Production::class
                    ? Production::query()->find($movement->reference_id)
                    : null;
                $qty = (float) $movement->quantity_out;
                $price = (float) $movement->unit_cost;

                return [
                    'index' => $i + 1,
                    'sales_date' => optional($movement->occurred_at)->format('d-m-Y'),
                    'production' => optional($production)->title ?: '-',
                    'item' => optional($movement->product)->name ?: '-',
                    'qty' => round($qty, 2),
                    'buying_price' => round($price, 2),
                    'total' => round($qty * $price, 2),
                ];
            })->values();
    }

    public function productionRows(string $from, string $to, ?int $branchId = null, ?int $productId = null): Collection
    {
        return Production::query()
            ->with(['user', 'bom.product'])
            ->whereBetween('production_date', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($productId, function ($q) use ($productId) {
                $q->whereHas('bom', fn ($b) => $b->where('product_id', $productId));
            })
            ->orderBy('production_date')
            ->get()
            ->map(function (Production $production, int $i) {
                return [
                    'index' => $i + 1,
                    'production_date' => optional($production->production_date)->format('d-m-Y'),
                    'posted_by' => optional($production->user)->name ?: '-',
                    'description' => $production->description ?: $production->title,
                    'expected_qty' => round((float) $production->expected_production, 2),
                    'production_cost' => round((float) $production->production_cost, 2),
                    'actual_qty' => round((float) $production->actual_production, 2),
                    'sales_cost' => round((float) optional(optional($production->bom)->product)->selling_price, 2),
                ];
            })->values();
    }

    private function movementByTypeRows(string $from, string $to, string $type, ?int $branchId, ?int $productId, string $totalKey): Collection
    {
        return StockMovement::query()
            ->with(['product', 'branch'])
            ->where('type', $type)
            ->whereBetween('occurred_at', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->orderBy('occurred_at')
            ->get()
            ->map(function (StockMovement $movement, int $i) use ($totalKey) {
                $product = $movement->product;
                $qty = (float) $movement->quantity_out ?: (float) $movement->quantity_in;
                $purchase = round((float) $movement->unit_cost, 2);
                $sales = round((float) optional($product)->selling_price, 2);

                return [
                    'index' => $i + 1,
                    'branch' => optional($movement->branch)->name ?: '-',
                    'item_code' => optional($product)->item_code ?: '-',
                    'entry_date' => optional($movement->occurred_at)->format('d-m-Y'),
                    'item' => optional($product)->name ?: '-',
                    'qty' => round($qty, 2),
                    'purchase_price' => $purchase,
                    'sales_price' => $sales,
                    $totalKey => $totalKey === 'total_cost'
                        ? round($qty * $purchase, 2)
                        : round($qty * $sales, 2),
                ];
            })->values();
    }

    private function movementQuery(string $from, string $to, ?int $branchId = null, ?int $productId = null)
    {
        return StockMovement::query()
            ->with(['product', 'branch', 'user'])
            ->whereBetween('occurred_at', [$this->start($from), $this->end($to)])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId));
    }

    private function productQuery(array $filters)
    {
        return Product::query()
            ->where('is_active', true)
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', (int) $filters['category_id']))
            ->when(! empty($filters['brand_id']), fn ($q) => $q->where('brand_id', (int) $filters['brand_id']))
            ->when(! empty($filters['product_id']), fn ($q) => $q->where('id', (int) $filters['product_id']));
    }

    private function productStock(Product $product, ?int $branchId): float
    {
        $query = $product->branchStock();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return (float) $query->sum('quantity');
    }

    private function stockAsAt(int $productId, ?int $branchId, Carbon $asAt): float
    {
        $query = StockMovement::query()
            ->where('product_id', $productId)
            ->where('occurred_at', '<=', $asAt);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $in = (float) (clone $query)->sum('quantity_in');
        $out = (float) (clone $query)->sum('quantity_out');

        return $in - $out;
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
