<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Services\AccountingPoster;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditNoteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('sales.view');

        $status = $request->input('status');
        $from = $request->input('from_date');
        $to = $request->input('to_date');
        $search = trim((string) $request->input('q', ''));

        $notes = CreditNote::query()
            ->with(['sale', 'customer', 'user', 'items'])
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($from, function ($query) use ($from) {
                $query->whereDate('credit_date', '>=', $from);
            })
            ->when($to, function ($query) use ($to) {
                $query->whereDate('credit_date', '<=', $to);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('number', 'like', '%' . $search . '%')
                        ->orWhere('reason', 'like', '%' . $search . '%')
                        ->orWhereHas('sale', function ($saleQuery) use ($search) {
                            $saleQuery->where('number', 'like', '%' . $search . '%')
                                ->orWhere('invoice_number', 'like', '%' . $search . '%')
                                ->orWhere('receipt_number', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderByDesc('id')
            ->get();

        $user = auth()->user();

        return view('sales.credit_notes.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'sales.credit-notes',
            'notes' => $notes,
            'status' => $status,
            'fromDate' => $from,
            'toDate' => $to,
            'search' => $search,
            'canCreate' => $user->hasPermission('sales.return') || $user->hasPermission('sales.create'),
            'canVoid' => $user->hasPermission('sales.void'),
        ]));
    }

    public function create()
    {
        $this->authorizeCreate();

        $sales = Sale::query()
            ->with('customer')
            ->where('status', Sale::STATUS_COMPLETED)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('sales.credit_notes.create', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'sales.credit-notes',
            'sales' => $sales,
        ]));
    }

    public function eligibleSaleJson(Sale $sale)
    {
        $this->authorizeCreate();
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can receive a credit note.');

        return response()->json($this->creditablePayload($sale));
    }

    public function store(
        Request $request,
        DocumentNumberService $numbers,
        InventoryService $inventory,
        AccountingPoster $accounting,
        LoyaltyService $loyalty,
        AuditLogger $audit
    ) {
        $this->authorizeCreate();

        $data = $request->validate([
            'sale_id' => 'required|integer|exists:sales,id',
            'credit_date' => 'required|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'restore_stock' => 'nullable|boolean',
            'action' => 'required|in:draft,posted',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer',
            'items.*.quantity' => 'nullable|numeric|min:0',
        ]);

        $sale = Sale::query()->with(['items.product', 'customer'])->findOrFail($data['sale_id']);
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 422, 'Only a completed sale can receive a credit note.');

        $payload = $this->creditablePayload($sale);
        $available = collect($payload['items'])->keyBy('sale_item_id');
        $restoreStock = $request->boolean('restore_stock');
        $lines = [];

        foreach ($data['items'] as $row) {
            $qty = round((float) ($row['quantity'] ?? 0), 4);
            if ($qty <= 0) {
                continue;
            }

            $item = $available->get((int) $row['sale_item_id']);
            if (! $item || $qty - $item['available'] > 0.0001) {
                return back()->withInput()->with('error', 'Credit quantity exceeds what can be credited.');
            }

            $soldQty = (float) $item['sold_qty'];
            $ratio = $soldQty > 0 ? ($qty / $soldQty) : 0;
            $unitPrice = (float) $item['unit_price'];
            $lineTotal = round((float) $item['line_total'] * $ratio, 2);
            $taxAmount = round((float) $item['tax_amount'] * $ratio, 2);
            $discountAmount = round((float) $item['discount_amount'] * $ratio, 2);

            $lines[] = [
                'sale_item_id' => (int) $item['sale_item_id'],
                'product_id' => $item['product_id'] ? (int) $item['product_id'] : null,
                'product_variant_id' => (int) $item['product_variant_id'],
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'restore_stock' => $restoreStock,
                'manage_stock' => (bool) $item['manage_stock'],
                'cost_price' => (float) $item['cost_price'],
            ];
        }

        if (count($lines) === 0) {
            return back()->withInput()->with('error', 'Enter a quantity to credit.');
        }

        $subtotal = round(collect($lines)->sum(function ($line) {
            return (float) $line['line_total'] - (float) $line['tax_amount'];
        }), 2);
        $taxAmount = round(collect($lines)->sum('tax_amount'), 2);
        $discountAmount = round(collect($lines)->sum('discount_amount'), 2);
        $total = round(collect($lines)->sum('line_total'), 2);
        $postNow = $data['action'] === 'posted';

        try {
            $note = null;

            DB::transaction(function () use (
                $sale,
                $data,
                $lines,
                $numbers,
                $inventory,
                $accounting,
                $loyalty,
                $audit,
                $restoreStock,
                $subtotal,
                $taxAmount,
                $discountAmount,
                $total,
                $postNow,
                &$note
            ) {
                $note = CreditNote::query()->create([
                    'company_id' => $sale->company_id,
                    'branch_id' => $sale->branch_id,
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'user_id' => auth()->id(),
                    'number' => $numbers->next((int) $sale->company_id, 'credit_note'),
                    'credit_date' => $data['credit_date'],
                    'status' => CreditNote::STATUS_DRAFT,
                    'reason' => $data['reason'],
                    'notes' => $data['notes'] ?? null,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'restore_stock' => $restoreStock,
                    'stock_restored' => false,
                    'accounting_posted' => false,
                    'loyalty_adjusted' => false,
                ]);

                foreach ($lines as $line) {
                    CreditNoteItem::query()->create([
                        'company_id' => $sale->company_id,
                        'credit_note_id' => $note->id,
                        'sale_item_id' => $line['sale_item_id'],
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $line['product_variant_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'discount_amount' => $line['discount_amount'],
                        'tax_amount' => $line['tax_amount'],
                        'line_total' => $line['line_total'],
                        'restore_stock' => $line['restore_stock'],
                    ]);
                }

                $audit->record('create', 'credit_notes', $note, null, [
                    'number' => $note->number,
                    'sale_id' => $note->sale_id,
                    'total' => $note->total,
                    'status' => $note->status,
                ]);

                if ($postNow) {
                    $this->processPost($note->fresh(['items.saleItem.product', 'sale']), $inventory, $accounting, $loyalty, $audit);
                }
            });
        } catch (NegativeStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('sales.credit-notes.show', $note)
            ->with('success', $postNow ? 'Credit note posted.' : 'Credit note saved as draft.');
    }

    public function show(CreditNote $creditNote)
    {
        $this->authorizePermission('sales.view');

        $creditNote->load(['sale.customer', 'customer', 'user', 'items.product', 'items.saleItem', 'voidedBy']);
        $user = auth()->user();

        return view('sales.credit_notes.show', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'sales.credit-notes',
            'note' => $creditNote,
            'canPost' => ($user->hasPermission('sales.return') || $user->hasPermission('sales.create'))
                && $creditNote->status === CreditNote::STATUS_DRAFT,
            'canVoid' => $user->hasPermission('sales.void') && ! $creditNote->isVoided(),
        ]));
    }

    public function print(CreditNote $creditNote)
    {
        $this->authorizePermission('sales.view');

        $creditNote->load(['sale', 'customer', 'user', 'items.product', 'items.saleItem', 'company', 'branch']);

        return view('sales.credit_notes.print', array_merge(fleet_shared_view_data(), [
            'note' => $creditNote,
            'profile' => fleet_company_profile(),
        ]));
    }

    public function post(
        CreditNote $creditNote,
        InventoryService $inventory,
        AccountingPoster $accounting,
        LoyaltyService $loyalty,
        AuditLogger $audit
    ) {
        $this->authorizeCreate();
        abort_unless($creditNote->status === CreditNote::STATUS_DRAFT, 422, 'Only a draft credit note can be posted.');

        try {
            DB::transaction(function () use ($creditNote, $inventory, $accounting, $loyalty, $audit) {
                $this->processPost(
                    $creditNote->fresh(['items.saleItem.product', 'sale']),
                    $inventory,
                    $accounting,
                    $loyalty,
                    $audit
                );
            });
        } catch (NegativeStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('sales.credit-notes.show', $creditNote)
            ->with('success', 'Credit note posted.');
    }

    public function void(
        Request $request,
        CreditNote $creditNote,
        InventoryService $inventory,
        AccountingPoster $accounting,
        AuditLogger $audit
    ) {
        $this->authorizePermission('sales.void');
        abort_if($creditNote->isVoided(), 422, 'Credit note is already voided.');

        $data = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($creditNote, $data, $inventory, $accounting, $audit) {
                $note = CreditNote::query()
                    ->with(['items.saleItem.product', 'sale'])
                    ->lockForUpdate()
                    ->findOrFail($creditNote->id);

                abort_if($note->isVoided(), 422, 'Credit note is already voided.');

                $before = $note->only([
                    'status', 'total', 'stock_restored', 'accounting_posted', 'loyalty_adjusted',
                ]);

                if ($note->isPosted()) {
                    if ($note->stock_restored) {
                        $movements = StockMovement::query()
                            ->where('reference_type', CreditNote::class)
                            ->where('reference_id', $note->id)
                            ->get();

                        foreach ($movements as $movement) {
                            $inventory->reverse($movement);
                        }

                        $note->stock_restored = false;
                    }

                    if ($note->accounting_posted) {
                        $accounting->reverseCreditNote($note);
                        $note->accounting_posted = false;
                    }

                    if ($note->loyalty_adjusted) {
                        $this->reverseLoyaltyAdjustment($note);
                        $note->loyalty_adjusted = false;
                    }

                    if ($note->sale) {
                        $this->reverseCreditOnSale($note->sale, (float) $note->total);
                    }
                }

                $note->fill([
                    'status' => CreditNote::STATUS_VOIDED,
                    'voided_at' => now(),
                    'voided_by' => auth()->id(),
                    'void_reason' => $data['void_reason'],
                ]);
                $note->save();

                $audit->record('void', 'credit_notes', $note, $before, [
                    'status' => $note->status,
                    'void_reason' => $note->void_reason,
                    'stock_restored' => $note->stock_restored,
                    'accounting_posted' => $note->accounting_posted,
                    'loyalty_adjusted' => $note->loyalty_adjusted,
                ]);
            });
        } catch (NegativeStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('sales.credit-notes.show', $creditNote)
            ->with('success', 'Credit note voided.');
    }

    private function authorizeCreate(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasPermission('sales.return') || $user->hasPermission('sales.create')),
            403
        );
    }

    private function processPost(
        CreditNote $note,
        InventoryService $inventory,
        AccountingPoster $accounting,
        LoyaltyService $loyalty,
        AuditLogger $audit
    ): void {
        abort_unless($note->status === CreditNote::STATUS_DRAFT, 422, 'Only a draft credit note can be posted.');

        $before = $note->only(['status', 'stock_restored', 'accounting_posted', 'loyalty_adjusted']);

        $note->status = CreditNote::STATUS_POSTED;
        $note->save();

        if ($note->restore_stock && ! $note->stock_restored) {
            $note->loadMissing(['items.saleItem.product', 'sale']);

            foreach ($note->items as $item) {
                if (! $item->restore_stock || ! $item->product_id) {
                    continue;
                }

                $product = optional($item->saleItem)->product ?: $item->product;
                if (! $product || ! $product->manage_stock) {
                    continue;
                }

                $inventory->apply([
                    'company_id' => (int) $note->company_id,
                    'branch_id' => (int) $note->branch_id,
                    'product_id' => (int) $item->product_id,
                    'product_variant_id' => (int) ($item->product_variant_id ?: 0),
                    'type' => StockMovement::SALE_RETURN,
                    'quantity_in' => (float) $item->quantity,
                    'unit_cost' => optional($item->saleItem)->cost_price,
                    'user_id' => auth()->id(),
                    'notes' => 'Credit note ' . $note->number,
                    'reference_type' => CreditNote::class,
                    'reference_id' => $note->id,
                    'reference_number' => $note->number,
                    'occurred_at' => $note->credit_date,
                ]);
            }

            $note->stock_restored = true;
            $note->save();
        }

        if (! $note->accounting_posted) {
            $accounting->postCreditNote($note->fresh(['items.saleItem.product']));
            $note->accounting_posted = true;
            $note->save();
        }

        $loyalty->adjustForCreditNote($note->fresh());

        if ($note->sale) {
            $this->applyCreditToSale($note->sale->fresh(), (float) $note->total);
        }

        $audit->record('post', 'credit_notes', $note->fresh(), $before, [
            'status' => $note->status,
            'stock_restored' => $note->stock_restored,
            'accounting_posted' => $note->accounting_posted,
            'loyalty_adjusted' => (bool) $note->fresh()->loyalty_adjusted,
            'total' => $note->total,
        ]);
    }

    private function creditablePayload(Sale $sale): array
    {
        $sale->load(['items.product', 'customer']);

        $returned = SaleReturnItem::query()
            ->selectRaw('sale_item_id, SUM(quantity) as qty')
            ->whereHas('saleReturn', function ($query) use ($sale) {
                $query->where('sale_id', $sale->id);
            })
            ->groupBy('sale_item_id')
            ->pluck('qty', 'sale_item_id');

        $credited = CreditNoteItem::query()
            ->selectRaw('sale_item_id, SUM(quantity) as qty')
            ->whereHas('creditNote', function ($query) use ($sale) {
                $query->where('sale_id', $sale->id)
                    ->where('status', '!=', CreditNote::STATUS_VOIDED);
            })
            ->groupBy('sale_item_id')
            ->pluck('qty', 'sale_item_id');

        $items = $sale->items->map(function (SaleItem $item) use ($returned, $credited) {
            $sold = (float) $item->quantity;
            $alreadyReturned = (float) ($returned[$item->id] ?? 0);
            $alreadyCredited = (float) ($credited[$item->id] ?? 0);
            $available = round(max(0, $sold - $alreadyReturned - $alreadyCredited), 4);

            return [
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => (int) $item->product_variant_id,
                'name' => $item->name,
                'sold_qty' => $sold,
                'returned_qty' => $alreadyReturned,
                'credited_qty' => $alreadyCredited,
                'available' => $available,
                'unit_price' => (float) $item->unit_price,
                'cost_price' => (float) $item->cost_price,
                'discount_amount' => (float) $item->discount_amount,
                'tax_amount' => (float) $item->tax_amount,
                'line_total' => (float) $item->line_total,
                'manage_stock' => optional($item->product)->manage_stock ? true : false,
            ];
        })->filter(function ($item) {
            return $item['available'] > 0;
        })->values();

        return [
            'id' => $sale->id,
            'number' => $sale->documentNumber(),
            'customer' => $sale->customerDisplayName(),
            'sale_date' => optional($sale->sale_date)->format('Y-m-d'),
            'total' => (float) $sale->total,
            'balance' => (float) $sale->balance,
            'items' => $items,
        ];
    }

    private function applyCreditToSale(Sale $sale, float $credit): void
    {
        $credit = round($credit, 2);
        if ($credit <= 0) {
            return;
        }

        $total = max(0, round((float) $sale->total - $credit, 2));
        $balance = round((float) $sale->balance, 2);
        $paid = round((float) $sale->paid_amount, 2);

        $fromBalance = min($balance, $credit);
        $fromPaid = round(max(0, $credit - $fromBalance), 2);
        $paid = max(0, round($paid - $fromPaid, 2));
        $paid = min($paid, $total);

        $sale->update([
            'total' => $total,
            'paid_amount' => $paid,
        ]);

        $this->syncPaymentState($sale->fresh());
    }

    private function reverseCreditOnSale(Sale $sale, float $credit): void
    {
        $credit = round($credit, 2);
        if ($credit <= 0) {
            return;
        }

        $total = round((float) $sale->total + $credit, 2);
        $paid = round((float) $sale->paid_amount, 2);

        $sale->update([
            'total' => $total,
            'paid_amount' => $paid,
        ]);

        $this->syncPaymentState($sale->fresh());
    }

    private function reverseLoyaltyAdjustment(CreditNote $note): void
    {
        if (! $note->customer_id) {
            return;
        }

        $existing = LoyaltyTransaction::query()
            ->where('source_type', CreditNote::class)
            ->where('source_id', $note->id)
            ->where('type', LoyaltyTransaction::TYPE_REVERSE)
            ->first();

        if (! $existing) {
            return;
        }

        $already = LoyaltyTransaction::query()
            ->where('source_type', CreditNote::class)
            ->where('source_id', $note->id)
            ->where('type', LoyaltyTransaction::TYPE_ADJUST)
            ->where('notes', 'like', 'Points restored for voided credit note%')
            ->exists();

        if ($already) {
            return;
        }

        $points = abs((float) $existing->points);
        if ($points <= 0) {
            return;
        }

        $customer = $note->customer_id
            ? Customer::query()->lockForUpdate()->find($note->customer_id)
            : null;

        if (! $customer) {
            return;
        }

        $balance = round((float) $customer->loyalty_points + $points, 2);
        $customer->update(['loyalty_points' => $balance]);

        LoyaltyTransaction::query()->create([
            'company_id' => $note->company_id,
            'customer_id' => $customer->id,
            'user_id' => auth()->id(),
            'type' => LoyaltyTransaction::TYPE_ADJUST,
            'points' => $points,
            'balance_after' => $balance,
            'source_type' => CreditNote::class,
            'source_id' => $note->id,
            'reference' => $note->number,
            'notes' => 'Points restored for voided credit note',
        ]);
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
