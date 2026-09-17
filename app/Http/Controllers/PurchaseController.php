<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\AccountingPoster;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function index()
    {
        $this->authorizePermission('purchases.view');

        $purchases = PurchaseOrder::query()
            ->with(['supplier', 'user', 'payments', 'items.product'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();

        return view('purchases.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'purchases.index',
            'purchases' => $purchases,
            'canCreate' => auth()->user()->hasPermission('purchases.create'),
            'canUpdate' => auth()->user()->hasPermission('purchases.update'),
            'purchaseStatuses' => config('sellix.purchase_statuses', []),
            'paymentMethods' => config('sellix.payment_methods', []),
        ]));
    }

    public function create()
    {
        $this->authorizePermission('purchases.create');

        return view('purchases.create', array_merge(fleet_shared_view_data(), $this->purchaseFormData()));
    }

    public function edit(PurchaseOrder $purchase)
    {
        $this->authorizePermission('purchases.update');
        $purchase->load(['items.product', 'supplier']);
        abort_unless($purchase->isEditable(), 403, 'This purchase order can no longer be edited.');

        return view('purchases.create', array_merge(fleet_shared_view_data(), $this->purchaseFormData($purchase)));
    }

    public function store(Request $request, DocumentNumberService $numbers, InventoryService $inventory, AccountingPoster $accounting, AuditLogger $audit)
    {
        $this->authorizePermission('purchases.create');

        $companyId = auth()->user()->company_id;
        $branchId = $this->currentBranchId();
        abort_unless($branchId, 422, 'Select a branch first.');

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'order_date' => 'required|date',
            'due_date' => 'nullable|date',
            'status' => 'required|in:' . implode(',', array_keys(config('sellix.purchase_statuses', ['pending' => 'Pending']))),
            'reference_no' => 'nullable|string|max:64',
            'cu_number' => 'nullable|string|max:64',
            'attachment' => 'nullable|file|max:5120',
            'notes' => 'nullable|string|max:2000',
            'round_off' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'expenses' => 'nullable|array',
            'expenses.*.description' => 'nullable|string|max:255',
            'expenses.*.amount' => 'nullable|numeric|min:0',
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:32',
            'payment_reference' => 'nullable|string|max:64',
            'payment_notes' => 'nullable|string|max:1000',
        ]);

        $lines = [];
        $subtotal = 0;
        $taxTotal = 0;
        $discountTotal = 0;
        foreach ($data['items'] as $line) {
            $qty = (float) $line['quantity'];
            $cost = (float) $line['unit_cost'];
            $taxRate = (float) ($line['tax_rate'] ?? 0);
            $discPct = (float) ($line['discount_percent'] ?? 0);
            $base = $qty * $cost;
            $discountAmt = round($base * ($discPct / 100), 2);
            $taxable = $base - $discountAmt;
            $taxAmt = round($taxable * ($taxRate / 100), 2);
            $lineTotal = round($taxable + $taxAmt, 2);
            $subtotal += $lineTotal;
            $taxTotal += $taxAmt;
            $discountTotal += $discountAmt;
            $product = Product::query()->findOrFail($line['product_id']);
            $lines[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'selling_price' => (float) ($line['selling_price'] ?? $product->selling_price),
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmt,
                'discount_percent' => $discPct,
                'discount_amount' => $discountAmt,
                'line_total' => $lineTotal,
                'expiry_date' => $line['expiry_date'] ?? null,
            ];
        }

        $expenses = collect($data['expenses'] ?? [])
            ->filter(function ($row) {
                return ! empty($row['description']) || (float) ($row['amount'] ?? 0) > 0;
            })
            ->values()
            ->all();
        $expenseAmount = round(collect($expenses)->sum(function ($row) {
            return (float) ($row['amount'] ?? 0);
        }), 2);
        $roundOff = (float) ($data['round_off'] ?? 0);
        $total = round($subtotal + $expenseAmount + $roundOff, 2);
        $payAmount = (float) ($data['payment_amount'] ?? 0);

        try {
            $purchase = DB::transaction(function () use ($request, $data, $lines, $numbers, $inventory, $companyId, $branchId, $subtotal, $taxTotal, $discountTotal, $expenseAmount, $roundOff, $total, $payAmount, $expenses, $accounting, $audit) {
                $path = null;
                if ($request->hasFile('attachment')) {
                    $path = $request->file('attachment')->store('purchases', 'public');
                }

                $purchase = PurchaseOrder::query()->create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'supplier_id' => $data['supplier_id'],
                    'user_id' => auth()->id(),
                    'number' => $numbers->next($companyId, 'purchase_order'),
                    'reference_no' => $data['reference_no'] ?? null,
                    'cu_number' => $data['cu_number'] ?? null,
                    'order_date' => $data['order_date'],
                    'expected_date' => $data['due_date'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'status' => $data['status'],
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountTotal,
                    'tax_amount' => $taxTotal,
                    'round_off' => $roundOff,
                    'expense_amount' => $expenseAmount,
                    'total' => $total,
                    'paid_amount' => $payAmount,
                    'notes' => $data['notes'] ?? null,
                    'attachment_path' => $path,
                    'expenses_json' => $expenses ?: null,
                ]);

                $receive = $data['status'] === PurchaseOrder::STATUS_RECEIVED;
                $receipt = null;
                $receiptTotal = 0.0;
                $receiptTax = 0.0;
                if ($receive) {
                    $receipt = GoodsReceipt::query()->create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'purchase_order_id' => $purchase->id,
                        'supplier_id' => $purchase->supplier_id,
                        'user_id' => auth()->id(),
                        'number' => $numbers->next($companyId, 'goods_receipt'),
                        'received_at' => now(),
                        'status' => 'received',
                        'notes' => $purchase->notes,
                    ]);
                }

                foreach ($lines as $line) {
                    $item = PurchaseOrderItem::query()->create([
                        'company_id' => $companyId,
                        'purchase_order_id' => $purchase->id,
                        'product_id' => $line['product']->id,
                        'description' => $line['product']->name,
                        'quantity' => $line['quantity'],
                        'quantity_received' => $receive ? $line['quantity'] : 0,
                        'unit_cost' => $line['unit_cost'],
                        'selling_price' => $line['selling_price'],
                        'tax_rate' => $line['tax_rate'],
                        'tax_amount' => $line['tax_amount'],
                        'discount_percent' => $line['discount_percent'],
                        'discount_amount' => $line['discount_amount'],
                        'line_total' => $line['line_total'],
                        'expiry_date' => $line['expiry_date'],
                    ]);

                    if ($receive && $line['product']->manage_stock) {
                        $inventory->apply([
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'product_id' => $line['product']->id,
                            'type' => \App\Models\StockMovement::PURCHASE_RECEIPT,
                            'quantity_in' => $line['quantity'],
                            'unit_cost' => $line['unit_cost'],
                            'user_id' => auth()->id(),
                            'notes' => 'Purchase ' . $purchase->number,
                            'reference_type' => PurchaseOrder::class,
                            'reference_id' => $purchase->id,
                            'reference_number' => $purchase->number,
                            'occurred_at' => $purchase->order_date,
                        ]);

                        GoodsReceiptItem::query()->create([
                            'company_id' => $companyId,
                            'goods_receipt_id' => $receipt->id,
                            'purchase_order_item_id' => $item->id,
                            'product_id' => $line['product']->id,
                            'quantity_received' => $line['quantity'],
                            'unit_cost' => $line['unit_cost'],
                        ]);

                        ProductBatch::query()->create([
                            'company_id' => $companyId,
                            'product_id' => $line['product']->id,
                            'branch_id' => $branchId,
                            'batch_date' => $purchase->order_date,
                            'expiry_date' => $line['expiry_date'],
                            'cost_price' => $line['unit_cost'],
                            'retail_price' => $line['selling_price'],
                            'wholesale_price' => $line['product']->wholesale_price ?? 0,
                            'promo_price' => $line['product']->promo_price ?? 0,
                            'stocked_qty' => $line['quantity'],
                            'balance_qty' => $line['quantity'],
                            'is_active' => true,
                        ]);
                    } elseif ($receive) {
                        GoodsReceiptItem::query()->create([
                            'company_id' => $companyId,
                            'goods_receipt_id' => $receipt->id,
                            'purchase_order_item_id' => $item->id,
                            'product_id' => $line['product']->id,
                            'quantity_received' => $line['quantity'],
                            'unit_cost' => $line['unit_cost'],
                        ]);
                    }

                    if ($receive) {
                        $receiptTotal += (float) $line['line_total'];
                        $receiptTax += (float) $line['tax_amount'];
                    }
                }

                if ($payAmount > 0) {
                    $payment = Payment::query()->create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'user_id' => auth()->id(),
                        'supplier_id' => $purchase->supplier_id,
                        'number' => $numbers->next($companyId, 'payment'),
                        'payable_type' => PurchaseOrder::class,
                        'payable_id' => $purchase->id,
                        'method' => $data['payment_method'] ?: Payment::METHOD_CASH,
                        'amount' => $payAmount,
                        'reference' => $data['payment_reference'] ?? null,
                        'paid_at' => now(),
                        'notes' => $data['payment_notes'] ?? null,
                    ]);
                    $accounting->postPurchasePayment($purchase->fresh(), $payment);
                    $audit->record('payment', 'purchases', $payment, null, [
                        'purchase_id' => $purchase->id,
                        'amount' => $payAmount,
                    ]);
                }

                if ($receive && $receipt) {
                    $accounting->postPurchaseReceipt(
                        $purchase->fresh(),
                        round($receiptTotal, 2),
                        round($receiptTax, 2),
                        $receipt
                    );
                    $audit->record('receive', 'purchases', $receipt, null, [
                        'purchase_id' => $purchase->id,
                        'number' => $receipt->number,
                        'total' => round($receiptTotal, 2),
                    ]);
                }

                $audit->record('create', 'purchases', $purchase, null, [
                    'number' => $purchase->number,
                    'total' => $purchase->total,
                    'status' => $purchase->status,
                ]);

                return $purchase;
            });
        } catch (NegativeStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.index')->with('success', 'Purchase ' . $purchase->number . ' saved.');
    }

    public function update(Request $request, PurchaseOrder $purchase, AuditLogger $audit)
    {
        $this->authorizePermission('purchases.update');
        $purchase->load('items');
        abort_unless($purchase->isEditable(), 403, 'This purchase order can no longer be edited.');

        $companyId = auth()->user()->company_id;
        $editableStatuses = collect(config('sellix.purchase_statuses', []))
            ->keys()
            ->reject(fn ($status) => in_array($status, [
                PurchaseOrder::STATUS_RECEIVED,
                PurchaseOrder::STATUS_CANCELLED,
            ], true))
            ->values()
            ->all();

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'order_date' => 'required|date',
            'due_date' => 'nullable|date',
            'status' => 'required|in:' . implode(',', $editableStatuses ?: ['pending', 'ordered', 'draft', 'sent']),
            'reference_no' => 'nullable|string|max:64',
            'cu_number' => 'nullable|string|max:64',
            'attachment' => 'nullable|file|max:5120',
            'notes' => 'nullable|string|max:2000',
            'round_off' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'expenses' => 'nullable|array',
            'expenses.*.description' => 'nullable|string|max:255',
            'expenses.*.amount' => 'nullable|numeric|min:0',
        ]);

        $built = $this->buildPurchaseLines($data);

        DB::transaction(function () use ($request, $data, $built, $purchase, $companyId, $audit) {
            $path = $purchase->attachment_path;
            if ($request->hasFile('attachment')) {
                if ($path) {
                    Storage::disk('public')->delete($path);
                }
                $path = $request->file('attachment')->store('purchases', 'public');
            }

            $before = $purchase->only(['supplier_id', 'order_date', 'status', 'total', 'reference_no']);

            $purchase->update([
                'supplier_id' => $data['supplier_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['due_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => $data['status'],
                'reference_no' => $data['reference_no'] ?? null,
                'cu_number' => $data['cu_number'] ?? null,
                'subtotal' => $built['subtotal'],
                'discount_amount' => $built['discountTotal'],
                'tax_amount' => $built['taxTotal'],
                'round_off' => $built['roundOff'],
                'expense_amount' => $built['expenseAmount'],
                'total' => $built['total'],
                'notes' => $data['notes'] ?? null,
                'attachment_path' => $path,
                'expenses_json' => $built['expenses'] ?: null,
            ]);

            $purchase->items()->delete();

            foreach ($built['lines'] as $line) {
                PurchaseOrderItem::query()->create([
                    'company_id' => $companyId,
                    'purchase_order_id' => $purchase->id,
                    'product_id' => $line['product']->id,
                    'description' => $line['product']->name,
                    'quantity' => $line['quantity'],
                    'quantity_received' => 0,
                    'unit_cost' => $line['unit_cost'],
                    'selling_price' => $line['selling_price'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'discount_percent' => $line['discount_percent'],
                    'discount_amount' => $line['discount_amount'],
                    'line_total' => $line['line_total'],
                    'expiry_date' => $line['expiry_date'],
                ]);
            }

            $purchase->payments()->update(['supplier_id' => $data['supplier_id']]);
            $purchase->receipts()->update(['supplier_id' => $data['supplier_id']]);

            $audit->record('update', 'purchases', $purchase, $before, $purchase->only([
                'supplier_id', 'order_date', 'status', 'total', 'reference_no',
            ]));
        });

        return redirect()->route('purchases.index')->with('success', 'Purchase order ' . $purchase->number . ' updated.');
    }

    public function show(PurchaseOrder $purchase)
    {
        $this->authorizePermission('purchases.view');

        $purchase->load([
            'supplier',
            'user',
            'branch',
            'company',
            'items.product.tax',
            'payments.user',
            'receipts.user',
            'receipts.items',
        ]);

        $suppliers = Supplier::query()
            ->where(function ($query) use ($purchase) {
                $query->where('is_active', true);
                if ($purchase->supplier_id) {
                    $query->orWhere('id', $purchase->supplier_id);
                }
            })
            ->orderBy('name')
            ->get();

        return view('purchases.show', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'purchases.index',
            'purchase' => $purchase,
            'company' => $purchase->company ?: auth()->user()->company,
            'suppliers' => $suppliers,
            'paymentMethods' => config('sellix.payment_methods', []),
            'purchaseStatuses' => config('sellix.purchase_statuses', []),
            'canUpdate' => auth()->user()->hasPermission('purchases.update'),
            'canReceive' => auth()->user()->hasPermission('purchases.receive') || auth()->user()->hasPermission('purchases.update'),
            'canPay' => auth()->user()->hasPermission('purchases.update'),
        ]));
    }

    public function updateStatus(Request $request, PurchaseOrder $purchase, DocumentNumberService $numbers, InventoryService $inventory)
    {
        $this->authorizePermission('purchases.update');

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(config('sellix.purchase_statuses', ['pending' => 'Pending']))),
        ]);

        $newStatus = $data['status'];
        if ($newStatus === $purchase->status) {
            return back()->with('success', 'Purchase status unchanged.');
        }

        if ($purchase->status === PurchaseOrder::STATUS_RECEIVED && $newStatus !== PurchaseOrder::STATUS_RECEIVED) {
            return back()->with('error', 'This purchase is already received. Status cannot be reversed.');
        }

        if ($newStatus === PurchaseOrder::STATUS_RECEIVED) {
            abort_unless(
                auth()->user()->hasPermission('purchases.receive') || auth()->user()->hasPermission('purchases.update'),
                403
            );

            try {
                DB::transaction(function () use ($purchase, $numbers, $inventory, $newStatus) {
                    $this->receiveOutstanding($purchase, $numbers, $inventory);
                    $purchase->update(['status' => $newStatus]);
                });
            } catch (NegativeStockException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', 'Purchase marked as received and stock updated.');
        }

        $purchase->update(['status' => $newStatus]);

        return back()->with('success', 'Purchase status updated.');
    }

    public function storePayment(Request $request, PurchaseOrder $purchase, DocumentNumberService $numbers, AccountingPoster $accounting, AuditLogger $audit)
    {
        $this->authorizePermission('purchases.update');

        $balance = $purchase->balance();
        if ($balance <= 0) {
            return back()->with('error', 'This purchase is already fully paid.');
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max($balance, 0.01),
            'method' => 'required|in:' . implode(',', array_keys(config('sellix.payment_methods', ['cash' => 'Cash']))),
            'reference' => 'nullable|string|max:64',
            'notes' => 'nullable|string|max:1000',
            'paid_at' => 'required|date',
        ]);

        DB::transaction(function () use ($purchase, $numbers, $data, $accounting, $audit) {
            $amount = round((float) $data['amount'], 2);
            $payment = Payment::query()->create([
                'company_id' => $purchase->company_id,
                'branch_id' => $purchase->branch_id,
                'user_id' => auth()->id(),
                'supplier_id' => $purchase->supplier_id,
                'number' => $numbers->next($purchase->company_id, 'payment'),
                'payable_type' => PurchaseOrder::class,
                'payable_id' => $purchase->id,
                'method' => $data['method'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'],
                'notes' => $data['notes'] ?? null,
            ]);

            $purchase->update([
                'paid_amount' => round((float) $purchase->paid_amount + $amount, 2),
            ]);

            $accounting->postPurchasePayment($purchase->fresh(), $payment);
            $audit->record('payment', 'purchases', $payment, null, [
                'purchase_id' => $purchase->id,
                'amount' => $amount,
            ]);
        });

        return back()->with('success', 'Supplier payment saved.');
    }

    public function updateDetails(Request $request, PurchaseOrder $purchase)
    {
        $this->authorizePermission('purchases.update');

        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'order_date' => 'required|date',
        ]);

        $purchase->update([
            'supplier_id' => $data['supplier_id'],
            'order_date' => $data['order_date'],
        ]);

        $purchase->payments()->update(['supplier_id' => $data['supplier_id']]);
        $purchase->receipts()->update(['supplier_id' => $data['supplier_id']]);

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase details updated.');
    }

    public function payments(PurchaseOrder $purchase)
    {
        $this->authorizePermission('purchases.view');

        $purchase->load(['supplier', 'payments.user']);
        $methods = config('sellix.payment_methods', []);

        return response()->json([
            'number' => $purchase->number,
            'supplier' => optional($purchase->supplier)->name,
            'total' => number_format((float) $purchase->total, 2),
            'paid' => number_format((float) $purchase->paid_amount, 2),
            'balance' => number_format($purchase->balance(), 2),
            'status' => $purchase->paymentStatusLabel(),
            'payments' => $purchase->payments->map(function (Payment $payment) use ($methods) {
                return [
                    'number' => $payment->number,
                    'date' => optional($payment->paid_at)->format('d-m-Y H:i') ?: '-',
                    'method' => $methods[$payment->method] ?? ucfirst((string) $payment->method),
                    'reference' => $payment->reference ?: '-',
                    'amount' => number_format((float) $payment->amount, 2),
                    'user' => optional($payment->user)->name ?: '-',
                ];
            })->values(),
        ]);
    }

    public function print(PurchaseOrder $purchase, Request $request)
    {
        $this->authorizePermission('purchases.view');

        $mode = $request->query('mode', 'po');
        if (! in_array($mode, ['po', 'noprice', 'thermal'], true)) {
            $mode = 'po';
        }

        $purchase->load(['supplier', 'user', 'items.product', 'company']);

        return view('purchases.print', array_merge(fleet_shared_view_data(), [
            'purchase' => $purchase,
            'mode' => $mode,
            'hidePrices' => $mode === 'noprice',
            'thermal' => $mode === 'thermal',
        ]));
    }

    public function printGrn(PurchaseOrder $purchase, GoodsReceipt $receipt)
    {
        $this->authorizePermission('purchases.view');
        abort_unless((int) $receipt->purchase_order_id === (int) $purchase->id, 404);

        $receipt->load(['user', 'items.product', 'supplier']);
        $purchase->load(['supplier', 'branch', 'company']);

        return view('purchases.grn-print', array_merge(fleet_shared_view_data(), [
            'purchase' => $purchase,
            'receipt' => $receipt,
        ]));
    }

    private function receiveOutstanding(PurchaseOrder $purchase, DocumentNumberService $numbers, InventoryService $inventory, ?AccountingPoster $accounting = null, ?AuditLogger $audit = null): void
    {
        $purchase->loadMissing(['items.product']);
        $outstanding = $purchase->items->filter(function (PurchaseOrderItem $item) {
            return ((float) $item->quantity - (float) $item->quantity_received) > 0.0001;
        });

        if ($outstanding->isEmpty()) {
            return;
        }

        $receipt = GoodsReceipt::query()->create([
            'company_id' => $purchase->company_id,
            'branch_id' => $purchase->branch_id,
            'purchase_order_id' => $purchase->id,
            'supplier_id' => $purchase->supplier_id,
            'user_id' => auth()->id(),
            'number' => $numbers->next($purchase->company_id, 'goods_receipt'),
            'received_at' => now(),
            'status' => 'received',
            'notes' => $purchase->notes,
        ]);

        $receiptTotal = 0.0;

        foreach ($outstanding as $item) {
            $qty = round((float) $item->quantity - (float) $item->quantity_received, 4);
            $item->update(['quantity_received' => $item->quantity]);
            $lineTotal = round($qty * (float) $item->unit_cost, 2);
            $receiptTotal += $lineTotal;

            if ($item->product && $item->product->manage_stock) {
                $inventory->apply([
                    'company_id' => $purchase->company_id,
                    'branch_id' => $purchase->branch_id,
                    'product_id' => $item->product_id,
                    'type' => \App\Models\StockMovement::PURCHASE_RECEIPT,
                    'quantity_in' => $qty,
                    'unit_cost' => $item->unit_cost,
                    'user_id' => auth()->id(),
                    'notes' => 'Purchase ' . $purchase->number,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $purchase->id,
                    'reference_number' => $purchase->number,
                    'occurred_at' => $purchase->order_date,
                ]);

                GoodsReceiptItem::query()->create([
                    'company_id' => $purchase->company_id,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity_received' => $qty,
                    'unit_cost' => $item->unit_cost,
                ]);

                ProductBatch::query()->create([
                    'company_id' => $purchase->company_id,
                    'product_id' => $item->product_id,
                    'branch_id' => $purchase->branch_id,
                    'batch_date' => $purchase->order_date,
                    'expiry_date' => $item->expiry_date,
                    'cost_price' => $item->unit_cost,
                    'retail_price' => $item->selling_price,
                    'wholesale_price' => $item->product->wholesale_price ?? 0,
                    'promo_price' => $item->product->promo_price ?? 0,
                    'stocked_qty' => $qty,
                    'balance_qty' => $qty,
                    'is_active' => true,
                ]);
            } else {
                GoodsReceiptItem::query()->create([
                    'company_id' => $purchase->company_id,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity_received' => $qty,
                    'unit_cost' => $item->unit_cost,
                ]);
            }
        }

        $accounting = $accounting ?: app(AccountingPoster::class);
        $audit = $audit ?: app(AuditLogger::class);
        $accounting->postPurchaseReceipt($purchase, round($receiptTotal, 2), 0, $receipt);
        $audit->record('receive', 'purchases', $receipt, null, [
            'purchase_id' => $purchase->id,
            'total' => $receiptTotal,
            'number' => $receipt->number,
        ]);
    }

    private function purchaseFormData(?PurchaseOrder $purchase = null): array
    {
        $statuses = collect(config('sellix.purchase_statuses', []))
            ->when($purchase, function ($collection) {
                return $collection->reject(function ($label, $status) {
                    return in_array($status, [
                        PurchaseOrder::STATUS_RECEIVED,
                        PurchaseOrder::STATUS_CANCELLED,
                    ], true);
                });
            })
            ->all();

        $existingItems = [];
        if ($purchase) {
            $purchase->loadMissing(['items.product']);
            foreach ($purchase->items as $item) {
                $product = $item->product;
                $existingItems[] = [
                    'id' => (int) $item->product_id,
                    'name' => $item->description ?: optional($product)->name,
                    'purchase_price' => (float) $item->unit_cost,
                    'selling_price' => (float) ($item->selling_price ?: optional($product)->selling_price),
                    'tax_rate' => (float) $item->tax_rate,
                    'discount_percent' => (float) $item->discount_percent,
                    'quantity' => (float) $item->quantity,
                    'stock' => $product ? $product->quantityAtBranch($this->currentBranchId()) : 0,
                    'expiry' => optional($item->expiry_date)->format('Y-m-d'),
                ];
            }
        }

        return [
            'activeMenu' => $purchase ? 'purchases.index' : 'purchases.create',
            'purchase' => $purchase,
            'isEdit' => (bool) $purchase,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => $statuses,
            'paymentMethods' => config('sellix.payment_methods', []),
            'canCreateSupplier' => auth()->user()->hasPermission('suppliers.create'),
            'existingItems' => $existingItems,
            'existingExpenses' => $purchase ? ($purchase->expenses_json ?: []) : [],
        ];
    }

    private function buildPurchaseLines(array $data): array
    {
        $lines = [];
        $subtotal = 0;
        $taxTotal = 0;
        $discountTotal = 0;

        foreach ($data['items'] as $line) {
            $qty = (float) $line['quantity'];
            $cost = (float) $line['unit_cost'];
            $taxRate = (float) ($line['tax_rate'] ?? 0);
            $discPct = (float) ($line['discount_percent'] ?? 0);
            $base = $qty * $cost;
            $discountAmt = round($base * ($discPct / 100), 2);
            $taxable = $base - $discountAmt;
            $taxAmt = round($taxable * ($taxRate / 100), 2);
            $lineTotal = round($taxable + $taxAmt, 2);
            $subtotal += $lineTotal;
            $taxTotal += $taxAmt;
            $discountTotal += $discountAmt;
            $product = Product::query()->findOrFail($line['product_id']);
            $lines[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'selling_price' => (float) ($line['selling_price'] ?? $product->selling_price),
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmt,
                'discount_percent' => $discPct,
                'discount_amount' => $discountAmt,
                'line_total' => $lineTotal,
                'expiry_date' => $line['expiry_date'] ?? null,
            ];
        }

        $expenses = collect($data['expenses'] ?? [])
            ->filter(function ($row) {
                return ! empty($row['description']) || (float) ($row['amount'] ?? 0) > 0;
            })
            ->values()
            ->all();
        $expenseAmount = round(collect($expenses)->sum(function ($row) {
            return (float) ($row['amount'] ?? 0);
        }), 2);
        $roundOff = (float) ($data['round_off'] ?? 0);
        $total = round($subtotal + $expenseAmount + $roundOff, 2);

        return compact('lines', 'subtotal', 'taxTotal', 'discountTotal', 'expenses', 'expenseAmount', 'roundOff', 'total');
    }
}
