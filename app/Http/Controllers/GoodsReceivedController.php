<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\ProdukHistory;
use App\Models\PurchaseOrderBatch;
use App\Models\PurchaseOrderBatchItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceivedController extends Controller
{
    public function index(Request $request)
    {
        $selectedBatchId = $request->query('batch');

        // Show most recently created purchase orders first in the Receive Orders dropdown
        $openBatches = PurchaseOrderBatch::whereIn('status', ['pending', 'partially_received'])
            ->orderByDesc('created_at')
            ->get(['id', 'po_number', 'reference_number']);

        return view('purchase_orders.receive', compact('openBatches', 'selectedBatchId'));
    }

    public function history(Request $request)
    {
        $query = GoodsReceived::with(['batch.supplier', 'items.product']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                // Search by GRN reference number
                $q->where('reference_number', 'like', '%' . $searchTerm . '%')
                  // Search by PO number
                  ->orWhereHas('batch', function($batchQuery) use ($searchTerm) {
                      $batchQuery->where('po_number', 'like', '%' . $searchTerm . '%');
                  })
                  // Search by supplier name
                  ->orWhereHas('batch.supplier', function($supplierQuery) use ($searchTerm) {
                      $supplierQuery->where('nama', 'like', '%' . $searchTerm . '%');
                  })
                  // Search by product name in items
                  ->orWhereHas('items.product', function($productQuery) use ($searchTerm) {
                      $productQuery->where('nama_produk', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // Filter by date range if provided
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->where('received_date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->where('received_date', '<=', $request->date_to);
        }

        $receipts = $query->latest('received_date')->paginate(20)->appends($request->query());

        return view('purchase_orders.received_index', compact('receipts'));
    }

    public function showReceipt(GoodsReceived $receipt)
    {
        $receipt->load(['batch.supplier', 'batch.items.product', 'items.product', 'items.batchItem']);

        return response()->json([
            'receipt' => [
                'id' => $receipt->id,
                'reference_number' => $receipt->reference_number,
                'received_date' => optional($receipt->received_date)->format('Y-m-d'),
                'notes' => $receipt->notes,
                'supplier' => optional($receipt->batch->supplier)->nama,
                'po_number' => $receipt->batch->po_number ?? null,
            ],
            'items' => $receipt->items->map(function (GoodsReceivedItem $item) {
                return [
                    'product' => $item->product->nama_produk ?? 'N/A',
                    'ordered_quantity' => (int) optional($item->batchItem)->quantity,
                    'received_quantity' => (int) $item->quantity,
                    'unit_price' => $item->unit_price,
                ];
            }),
        ]);
    }

    public function show(PurchaseOrderBatch $batch)
    {
        $batch->load(['supplier', 'items.product', 'items.supplier']);

        $items = $batch->items->map(function (PurchaseOrderBatchItem $item) use ($batch) {
            // Get supplier name - prefer item's supplier, fallback to product's supplier, then batch supplier
            $supplierName = 'N/A';
            if ($item->supplier_id && $item->supplier) {
                $supplierName = $item->supplier->nama;
            } elseif ($item->product && $item->product->supplier) {
                $supplierName = $item->product->supplier->nama;
            } elseif ($batch->supplier) {
                $supplierName = $batch->supplier->nama;
            }
            
            return [
                'id' => $item->id,
                'product_id' => $item->produk_id,
                'product_name' => $item->product->nama_produk ?? 'N/A',
                'supplier_id' => $item->supplier_id,
                'supplier_name' => $supplierName,
                'quantity' => (int) $item->quantity,
                'received_quantity' => (int) $item->received_quantity,
                'remaining_quantity' => $item->remaining,
            ];
        });

        return response()->json([
            'batch' => [
                'id' => $batch->id,
                'po_number' => $batch->po_number,
                'reference_number' => $batch->reference_number,
                'status' => $batch->status,
                'supplier' => $batch->supplier->nama ?? 'N/A',
                'order_date' => optional($batch->order_date)->format('Y-m-d'),
                'notes' => $batch->notes,
            ],
            'items' => $items,
        ]);
    }

    public function store(Request $request, PurchaseOrderBatch $batch)
    {
        $validated = $request->validate([
            'received_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.batch_item_id' => 'required|exists:purchase_order_batch_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.confirmed' => 'nullable|boolean',
        ]);

        $items = collect($validated['items'])
            ->filter(fn ($item) => !empty($item['confirmed']))
            ->map(function ($item) {
                return [
                    'batch_item_id' => $item['batch_item_id'],
                    'quantity' => (int) $item['quantity'],
                ];
            })
            ->filter(fn ($item) => $item['quantity'] > 0)
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Select at least one item to receive.',
            ]);
        }

        DB::transaction(function () use ($batch, $validated, $items) {
            $receipt = GoodsReceived::create([
                'batch_id' => $batch->id,
                'reference_number' => $this->generateReceiptNumber($batch),
                'received_date' => $validated['received_date'],
                'notes' => $validated['notes'] ?? null,
                'received_by' => auth()->id(),
            ]);

            foreach ($items as $itemData) {
                /** @var PurchaseOrderBatchItem $batchItem */
                $batchItem = PurchaseOrderBatchItem::where('batch_id', $batch->id)
                    ->where('id', $itemData['batch_item_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $remaining = $batchItem->remaining;
                if ($itemData['quantity'] > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => "Requested quantity exceeds remaining quantity for {$batchItem->product->nama_produk}.",
                    ]);
                }

                GoodsReceivedItem::create([
                    'goods_received_id' => $receipt->id,
                    'batch_item_id' => $batchItem->id,
                    'produk_id' => $batchItem->produk_id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $batchItem->unit_price ?? 0,
                ]);

                // Get product and previous stock before update
                $product = $batchItem->product;
                $previousStock = $product->stok;
                
                $batchItem->increment('received_quantity', $itemData['quantity']);
                $product->increment('stok', $itemData['quantity']);
                
                // Log stock update to produk_history
                try {
                    ProdukHistory::create([
                        'id_produk' => $product->id_produk,
                        'previous_stock' => $previousStock,
                        'restock_amount' => $itemData['quantity'],
                        'current_stock' => $product->fresh()->stok,
                        'type' => 'goods_received',
                        'notes' => "Goods received - PO: {$batch->po_number}",
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Could not log to produk_history: ' . $e->getMessage());
                }
            }

            $batch->refresh();

            if ($batch->is_fully_received) {
                $batch->update([
                    'status' => 'completed',
                    'closed_at' => now(),
                ]);
            } else {
                $batch->update([
                    'status' => 'partially_received',
                ]);
            }
        });

        return response()->json([
            'message' => 'Goods received successfully.',
        ]);
    }

    protected function generateReceiptNumber(PurchaseOrderBatch $batch): string
    {
        $sequence = GoodsReceived::where('batch_id', $batch->id)->count() + 1;

        return $batch->po_number . '-GRN' . str_pad($sequence, 2, '0', STR_PAD_LEFT);
    }
}

