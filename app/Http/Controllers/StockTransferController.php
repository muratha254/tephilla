<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\IdempotencyService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    public function index()
    {
        $this->authorizePermission('inventory.view');

        $transfers = StockTransfer::query()
            ->with(['fromBranch', 'toBranch', 'user', 'items'])
            ->orderByDesc('id')
            ->get();

        return view('stock.transfers.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'stock.transfers',
            'transfers' => $transfers,
            'canTransfer' => auth()->user()->hasPermission('inventory.transfer'),
        ]));
    }

    public function create()
    {
        $this->authorizePermission('inventory.transfer');

        $products = Product::query()
            ->availableAtBranch($this->currentBranchId())
            ->where('is_active', true)
            ->where('manage_stock', true)
            ->orderBy('name')
            ->get();

        return view('stock.transfers.create', array_merge(fleet_shared_view_data(), $this->catalogLookups(), [
            'activeMenu' => 'stock.transfers',
            'products' => $products,
            'selectedBranchId' => $this->currentBranchId(),
            'productCosts' => $products->mapWithKeys(function (Product $product) {
                return [$product->id => (float) $product->purchase_price];
            }),
        ]));
    }

    public function store(Request $request, DocumentNumberService $numbers, InventoryService $inventory, AuditLogger $audit, IdempotencyService $idempotency)
    {
        $this->authorizePermission('inventory.transfer');

        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateTransfer($request, $companyId);
        $completeNow = ($data['action'] ?? 'draft') === 'complete';
        $token = $idempotency->key($request, 'stock_transfer', [
            'from_branch_id' => (int) $data['from_branch_id'],
            'to_branch_id' => (int) $data['to_branch_id'],
            'transfer_date' => $data['transfer_date'],
            'action' => $data['action'] ?? 'draft',
            'notes' => $data['notes'] ?? null,
            'items' => $data['items'],
        ]);

        try {
            $result = $idempotency->remember(
                $companyId,
                (int) auth()->id(),
                'stock_transfer',
                $token,
                function () use ($data, $numbers, $inventory, $audit, $companyId, $completeNow, $request) {
                    return DB::transaction(function () use ($data, $numbers, $inventory, $audit, $companyId, $completeNow, $request) {
                $transfer = StockTransfer::query()->create([
                    'company_id' => $companyId,
                    'from_branch_id' => (int) $data['from_branch_id'],
                    'to_branch_id' => (int) $data['to_branch_id'],
                    'user_id' => auth()->id(),
                    'number' => $numbers->next($companyId, 'stock_transfer'),
                    'transfer_date' => $data['transfer_date'],
                    'status' => StockTransfer::STATUS_DRAFT,
                    'notes' => $data['notes'] ?? null,
                ]);

                foreach ($data['items'] as $line) {
                    $product = Product::query()->findOrFail($line['product_id']);
                    StockTransferItem::query()->create([
                        'company_id' => $companyId,
                        'stock_transfer_id' => $transfer->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $line['product_variant_id'] ?? null,
                        'quantity' => round((float) $line['quantity'], 4),
                        'unit_cost' => round((float) ($line['unit_cost'] ?? $product->purchase_price), 4),
                    ]);
                }

                $audit->record('create', 'stock_transfer', $transfer, null, [
                    'number' => $transfer->number,
                    'from_branch_id' => $transfer->from_branch_id,
                    'to_branch_id' => $transfer->to_branch_id,
                    'status' => $transfer->status,
                ], $request);

                if ($completeNow) {
                    $this->applyCompletion($transfer->fresh(['items.product']), $inventory, $audit, $request);
                }

                return $transfer->fresh(['items', 'fromBranch', 'toBranch']);
                    });
                }
            );
            $transfer = $result['subject'] ?: StockTransfer::query()->findOrFail($result['subject_id']);
        } catch (NegativeStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = $result['replay']
            ? 'This transfer was already recorded.'
            : ($completeNow ? 'Stock transfer completed.' : 'Stock transfer saved as draft.');

        return redirect()
            ->route('stock.transfers.show', $transfer)
            ->with('success', $message);
    }

    public function show(StockTransfer $transfer)
    {
        abort_unless(
            auth()->user()
            && (auth()->user()->hasPermission('inventory.view') || auth()->user()->hasPermission('inventory.transfer')),
            403
        );

        $transfer->load(['items.product', 'fromBranch', 'toBranch', 'user']);

        return view('stock.transfers.show', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'stock.transfers',
            'transfer' => $transfer,
            'canTransfer' => auth()->user()->hasPermission('inventory.transfer'),
        ]));
    }

    public function complete(StockTransfer $transfer, InventoryService $inventory, AuditLogger $audit)
    {
        $this->authorizePermission('inventory.transfer');

        try {
            DB::transaction(function () use ($transfer, $inventory, $audit) {
                $locked = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
                $this->applyCompletion($locked->load(['items.product']), $inventory, $audit, request());
            });
        } catch (NegativeStockException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('stock.transfers.show', $transfer)
            ->with('success', 'Stock transfer completed.');
    }

    public function cancel(StockTransfer $transfer, AuditLogger $audit)
    {
        $this->authorizePermission('inventory.transfer');

        if ($transfer->status === StockTransfer::STATUS_COMPLETED) {
            return back()->with('error', 'Completed transfers cannot be cancelled.');
        }

        if ($transfer->status === StockTransfer::STATUS_CANCELLED) {
            return back()->with('error', 'Transfer is already cancelled.');
        }

        $before = $transfer->only(['number', 'status']);
        $transfer->update([
            'status' => StockTransfer::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $audit->record('cancel', 'stock_transfer', $transfer, $before, [
            'number' => $transfer->number,
            'status' => $transfer->status,
            'cancelled_at' => optional($transfer->cancelled_at)->toDateTimeString(),
        ]);

        return redirect()
            ->route('stock.transfers.show', $transfer)
            ->with('success', 'Stock transfer cancelled.');
    }

    private function applyCompletion(StockTransfer $transfer, InventoryService $inventory, AuditLogger $audit, ?Request $request = null): void
    {
        if ($transfer->status === StockTransfer::STATUS_COMPLETED) {
            throw new \RuntimeException('Transfer already completed.');
        }

        if ($transfer->status === StockTransfer::STATUS_CANCELLED) {
            throw new \RuntimeException('Cancelled transfers cannot be completed.');
        }

        if ($transfer->items->isEmpty()) {
            throw new \RuntimeException('Add at least one item before completing.');
        }

        $companyId = (int) $transfer->company_id;

        foreach ($transfer->items as $item) {
            $onHand = $inventory->quantityOnHand(
                $companyId,
                (int) $transfer->from_branch_id,
                (int) $item->product_id,
                (int) ($item->product_variant_id ?? 0)
            );
            $qty = (float) $item->quantity;
            if ($qty - $onHand > 0.0001) {
                $name = optional($item->product)->name ?: ('Product #' . $item->product_id);
                throw new \RuntimeException("Insufficient stock for {$name} at source branch (available: {$onHand}).");
            }

            $inventory->apply([
                'company_id' => $companyId,
                'branch_id' => (int) $transfer->from_branch_id,
                'product_id' => (int) $item->product_id,
                'product_variant_id' => (int) ($item->product_variant_id ?? 0),
                'type' => StockMovement::TRANSFER_OUT,
                'quantity_out' => $qty,
                'unit_cost' => $item->unit_cost,
                'user_id' => auth()->id(),
                'notes' => 'Transfer out ' . $transfer->number,
                'reference_type' => StockTransfer::class,
                'reference_id' => $transfer->id,
                'reference_number' => $transfer->number,
                'occurred_at' => $transfer->transfer_date,
                'allow_negative' => false,
            ]);

            $inventory->apply([
                'company_id' => $companyId,
                'branch_id' => (int) $transfer->to_branch_id,
                'product_id' => (int) $item->product_id,
                'product_variant_id' => (int) ($item->product_variant_id ?? 0),
                'type' => StockMovement::TRANSFER_IN,
                'quantity_in' => $qty,
                'unit_cost' => $item->unit_cost,
                'user_id' => auth()->id(),
                'notes' => 'Transfer in ' . $transfer->number,
                'reference_type' => StockTransfer::class,
                'reference_id' => $transfer->id,
                'reference_number' => $transfer->number,
                'occurred_at' => $transfer->transfer_date,
            ]);
        }

        $before = $transfer->only(['number', 'status']);
        $transfer->update([
            'status' => StockTransfer::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $audit->record('complete', 'stock_transfer', $transfer, $before, [
            'number' => $transfer->number,
            'status' => $transfer->status,
            'completed_at' => optional($transfer->completed_at)->toDateTimeString(),
        ], $request);
    }

    private function validateTransfer(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'from_branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'to_branch_id' => ['required', 'different:from_branch_id', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'action' => 'nullable|in:draft,complete',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.product_variant_id' => 'nullable|integer|min:0',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        if (! auth()->user()->canSwitchBranches()) {
            $data['from_branch_id'] = $this->currentBranchId();
        }
        $this->assertBranchAccess((int) $data['from_branch_id']);
        $data['from_branch_id'] = $this->currentBranchId();

        $lines = [];
        foreach ($data['items'] as $line) {
            $qty = round((float) $line['quantity'], 4);
            if ($qty <= 0) {
                continue;
            }
            $product = Product::query()->findOrFail($line['product_id']);
            $line['product_variant_id'] = $product->resolveVariantId($line['product_variant_id'] ?? 0);
            $lines[] = $line;
        }

        if (count($lines) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => 'Enter at least one product quantity.',
            ]);
        }

        $data['items'] = $lines;

        return $data;
    }
}
