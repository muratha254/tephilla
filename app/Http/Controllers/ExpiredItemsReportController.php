<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Http\Request;

class ExpiredItemsReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeExpiredReport();

        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;
        $generated = $request->boolean('show');
        $branchId = $this->currentBranchId();

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name']);

        $rows = collect();
        if ($generated) {
            $batches = ProductBatch::query()
                ->with(['product', 'branch'])
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [$from, $to])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($productId, fn ($q) => $q->where('product_id', $productId))
                ->orderBy('expiry_date')
                ->get();

            $rows = $batches->map(function (ProductBatch $batch, int $i) {
                return [
                    'index' => $i + 1,
                    'branch' => optional($batch->branch)->name ?: '-',
                    'item_code' => optional($batch->product)->item_code ?: '-',
                    'item' => optional($batch->product)->name ?: '-',
                    'lot' => 'BAT-' . str_pad((string) $batch->id, 4, '0', STR_PAD_LEFT),
                    'expire_date' => optional($batch->expiry_date)->format('d-m-Y'),
                    'stock' => round((float) $batch->balance_qty, 2),
                ];
            })->values();

            // Also include products with product-level expiry (no batch record).
            $productExpiries = Product::query()
                ->with(['branchStock' => function ($q) use ($branchId) {
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                }])
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [$from, $to])
                ->when($productId, fn ($q) => $q->where('id', $productId))
                ->whereDoesntHave('batches', function ($q) use ($from, $to, $branchId) {
                    $q->whereBetween('expiry_date', [$from, $to])
                        ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
                })
                ->orderBy('expiry_date')
                ->get();

            $offset = $rows->count();
            foreach ($productExpiries as $index => $product) {
                $stock = (float) $product->branchStock->sum('quantity');
                $rows->push([
                    'index' => $offset + $index + 1,
                    'branch' => '-',
                    'item_code' => $product->item_code,
                    'item' => $product->name,
                    'lot' => '-',
                    'expire_date' => optional($product->expiry_date)->format('d-m-Y'),
                    'stock' => round($stock, 2),
                ]);
            }
        }

        return view('reports.expired.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'reports.expired',
            'from' => $from,
            'to' => $to,
            'productId' => $productId,
            'generated' => $generated,
            'products' => $products,
            'rows' => $rows,
        ]));
    }

    private function authorizeExpiredReport(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('reports.stock') || $user->hasPermission('reports.view')), 403);
    }
}
