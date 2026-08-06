<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\PurchaseOrderBatch;
use App\Models\PurchaseOrderBatchItem;
use App\Models\Supplier;
use App\Models\GoodsReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return view('purchase_orders.index');
    }

    public function createMulti(Request $request)
    {
        $suppliers = Supplier::orderBy('nama')->get(['id_supplier', 'nama']);
        $products = Produk::with('supplier')->orderBy('nama_produk')->get(['id_produk', 'nama_produk', 'stok', 'harga_beli', 'id_supplier']);
        $productOptions = $products->map(function ($product) {
            return [
                'id' => $product->id_produk,
                'name' => $product->nama_produk,
                'stock' => $product->stok,
                'price' => $product->harga_beli,
                'supplier_id' => $product->id_supplier,
            ];
        });

        $selectedProductId = $request->query('product_id');

        return view('purchase_orders.create', [
            'suppliers' => $suppliers,
            'products' => $products,
            'productOptions' => $productOptions,
            'defaultReference' => PurchaseOrderBatch::generateNumber(),
            'selectedProductId' => $selectedProductId,
        ]);
    }

    public function storeMulti(Request $request)
    {
        $validated = $request->validate([
            'order_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:produk,id_produk',
            'items.*.supplier_id' => 'required|exists:supplier,id_supplier',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $items = collect($validated['items'])
            ->map(function ($item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                return [
                    'product_id' => $item['product_id'],
                    'supplier_id' => $item['supplier_id'],
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total_price' => round($qty * $price, 2),
                ];
            })
            ->filter(fn ($item) => $item['product_id'] && $item['supplier_id'] && $item['quantity'] > 0)
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one valid item with supplier before saving.',
            ]);
        }

        DB::transaction(function () use ($validated, $items) {
            // Use the first item's supplier as the batch supplier (for backward compatibility)
            // Or you could make it nullable if you want
            $firstSupplierId = $items->first()['supplier_id'];
            
            $batch = PurchaseOrderBatch::create([
                'po_number' => PurchaseOrderBatch::generateNumber(),
                'supplier_id' => $firstSupplierId,
                'order_date' => $validated['order_date'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $items->each(function ($item) use ($batch) {
                PurchaseOrderBatchItem::create([
                    'batch_id' => $batch->id,
                    'produk_id' => $item['product_id'],
                    'supplier_id' => $item['supplier_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);
            });
        });

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', 'Purchase order created with multiple items from different suppliers.');
    }

    public function batchData(Request $request)
    {
        $query = PurchaseOrderBatch::query()
            ->with('supplier:id_supplier,nama')
            ->with(['items' => function ($q) {
                $q->with(['supplier:id_supplier,nama', 'product' => function ($pq) {
                    $pq->with('supplier:id_supplier,nama');
                }]);
            }])
            ->withCount('items as items_count')
            ->withSum('items as total_quantity', 'quantity')
            ->withSum('items as total_received_quantity', 'received_quantity')
            ->latest('order_date');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('po_number_display', function (PurchaseOrderBatch $batch) {
                $reference = $batch->reference_number ? '<br><small class="text-muted">Ref: ' . e($batch->reference_number) . '</small>' : '';
                return '<strong>' . e($batch->po_number) . '</strong>' . $reference;
            })
            ->editColumn('order_date', function (PurchaseOrderBatch $batch) {
                return optional($batch->order_date)->format('Y-m-d');
            })
            ->addColumn('supplier_name', function (PurchaseOrderBatch $batch) {
                // Check if items have multiple suppliers
                $uniqueSuppliers = collect();
                
                foreach ($batch->items as $item) {
                    // Get supplier from item, product, or batch
                    $supplierId = null;
                    $supplierName = null;
                    
                    if ($item->supplier_id && $item->supplier) {
                        $supplierId = $item->supplier_id;
                        $supplierName = $item->supplier->nama;
                    } elseif ($item->product && $item->product->id_supplier) {
                        $supplierId = $item->product->id_supplier;
                        $supplierName = $item->product->supplier->nama ?? null;
                    } elseif ($batch->supplier_id) {
                        $supplierId = $batch->supplier_id;
                        $supplierName = $batch->supplier->nama ?? null;
                    }
                    
                    if ($supplierId && $supplierName) {
                        $uniqueSuppliers->put($supplierId, $supplierName);
                    }
                }
                
                // If multiple unique suppliers, show "Multiple Suppliers"
                if ($uniqueSuppliers->count() > 1) {
                    return '<span class="text-info"><i class="fa fa-info-circle"></i> Multiple Suppliers</span>';
                }
                
                // Otherwise, show the single supplier name
                return e($uniqueSuppliers->first() ?? $batch->supplier->nama ?? 'N/A');
            })
            ->addColumn('total_quantity', function (PurchaseOrderBatch $batch) {
                return number_format((int) ($batch->total_quantity ?? 0));
            })
            ->addColumn('total_received', function (PurchaseOrderBatch $batch) {
                return number_format((int) ($batch->total_received_quantity ?? 0));
            })
            ->addColumn('remaining_quantity', function (PurchaseOrderBatch $batch) {
                $remaining = (int) ($batch->total_quantity ?? 0) - (int) ($batch->total_received_quantity ?? 0);
                return number_format(max(0, $remaining));
            })
            ->addColumn('status_badge', function (PurchaseOrderBatch $batch) {
                $classes = [
                    'pending' => 'label-warning',
                    'partially_received' => 'label-info',
                    'completed' => 'label-success',
                    'cancelled' => 'label-default',
                    'rejected' => 'label-danger',
                ];

                $class = $classes[$batch->status] ?? 'label-default';
                return '<span class="label ' . $class . '">' . ucfirst(str_replace('_', ' ', $batch->status)) . '</span>';
            })
            ->addColumn('actions', function (PurchaseOrderBatch $batch) {
                return '<button type="button" class="btn btn-xs btn-info btn-flat btn-view-batch" data-id="' . $batch->id . '"><i class="fa fa-eye"></i> View</button>';
            })
            ->rawColumns(['po_number_display', 'status_badge', 'actions', 'supplier_name'])
            ->toJson();
    }

    public function updateBatchStatus(Request $request, PurchaseOrderBatch $batch)
    {
        $validated = $request->validate([
            'status' => 'required|in:rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($batch->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending purchase orders can be rejected.',
            ], 422);
        }

        $hasReceipts = $batch->items()->where('received_quantity', '>', 0)->exists();
        if ($hasReceipts) {
            return response()->json([
                'message' => 'This purchase order already has received items and cannot be rejected.',
            ], 422);
        }

        $batch->status = 'rejected';
        if (!empty($validated['reason'])) {
            $existingNotes = trim((string) $batch->notes);
            $note = 'Rejected: ' . $validated['reason'];
            $batch->notes = $existingNotes ? $existingNotes . PHP_EOL . $note : $note;
        }
        $batch->closed_at = now();
        $batch->save();

        return response()->json([
            'message' => 'Purchase order marked as rejected.',
            'status' => $batch->status,
        ]);
    }

    public function report(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        $supplierId = $request->get('supplier_id');
        $reportType = $request->get('report_type', 'purchase_orders'); // 'purchase_orders' or 'received_orders'

        $suppliers = Supplier::orderBy('nama')->get();

        if ($reportType === 'received_orders') {
            // Get received orders
            $query = GoodsReceived::with(['batch.supplier', 'items.product', 'items.batchItem'])
                ->whereBetween('received_date', [$startDate, $endDate])
                ->orderBy('received_date', 'desc');

            if ($supplierId) {
                $query->whereHas('batch', function ($q) use ($supplierId) {
                    $q->where('supplier_id', $supplierId);
                });
            }

            $receivedOrders = $query->get();
            $purchaseOrders = collect();
        } else {
            // Get purchase orders
            $query = PurchaseOrderBatch::with(['supplier', 'items.product', 'items.supplier'])
                ->whereBetween('order_date', [$startDate, $endDate])
                ->orderBy('order_date', 'desc');

            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            $purchaseOrders = $query->get();
            $receivedOrders = collect();
        }

        return view('reports.purchase_orders', compact(
            'purchaseOrders',
            'receivedOrders',
            'suppliers',
            'startDate',
            'endDate',
            'supplierId',
            'reportType'
        ));
    }

    public function exportPdf(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));
        $supplierId = $request->get('supplier_id');
        $reportType = $request->get('report_type', 'purchase_orders');

        $supplier = null;
        if ($supplierId) {
            $supplier = Supplier::find($supplierId);
        }

        if ($reportType === 'received_orders') {
            // Get received orders
            $query = GoodsReceived::with(['batch.supplier', 'items.product', 'items.batchItem'])
                ->whereBetween('received_date', [$startDate, $endDate])
                ->orderBy('received_date', 'desc');

            if ($supplierId) {
                $query->whereHas('batch', function ($q) use ($supplierId) {
                    $q->where('supplier_id', $supplierId);
                });
            }

            $receivedOrders = $query->get();
            $purchaseOrders = collect();
        } else {
            // Get purchase orders
            $query = PurchaseOrderBatch::with(['supplier', 'items.product', 'items.supplier'])
                ->whereBetween('order_date', [$startDate, $endDate])
                ->orderBy('order_date', 'desc');

            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            $purchaseOrders = $query->get();
            $receivedOrders = collect();
        }

        $pdf = PDF::loadView('reports.purchase_orders_pdf', compact(
            'purchaseOrders',
            'receivedOrders',
            'startDate',
            'endDate',
            'supplier',
            'reportType'
        ));

        $filename = $reportType === 'received_orders' 
            ? 'received_orders_report_' . $startDate . '_to_' . $endDate . '.pdf'
            : 'purchase_orders_report_' . $startDate . '_to_' . $endDate . '.pdf';

        return $pdf->download($filename);
    }
}

