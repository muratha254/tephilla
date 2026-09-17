<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\AccountingPoster;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    private const EDITABLE = [
        Quotation::STATUS_DRAFT,
        Quotation::STATUS_SENT,
    ];

    private const CONVERTIBLE = [
        Quotation::STATUS_DRAFT,
        Quotation::STATUS_SENT,
        Quotation::STATUS_ACCEPTED,
    ];

    public function index()
    {
        $this->authorizePermission('quotations.view');

        $quotations = Quotation::query()
            ->with(['customer', 'user'])
            ->orderByDesc('quote_date')
            ->orderByDesc('id')
            ->get();

        $user = auth()->user();

        return view('quotations.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'quotations.index',
            'quotations' => $quotations,
            'statuses' => $this->statusLabels(),
            'canCreate' => $user->hasPermission('quotations.create'),
            'canUpdate' => $user->hasPermission('quotations.update'),
            'canDelete' => $user->hasPermission('quotations.delete'),
            'canConvert' => $user->hasPermission('quotations.convert'),
        ]));
    }

    public function create()
    {
        $this->authorizePermission('quotations.create');

        return view('quotations.create', array_merge(fleet_shared_view_data(), $this->formData(new Quotation([
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'status' => Quotation::STATUS_DRAFT,
            'discount_amount' => 0,
        ])), [
            'activeMenu' => 'quotations.create',
        ]));
    }

    public function store(Request $request, DocumentNumberService $numbers, AuditLogger $audit)
    {
        $this->authorizePermission('quotations.create');

        $companyId = (int) auth()->user()->company_id;
        $branchId = $this->currentBranchId();
        abort_unless($branchId, 422, 'Select a branch first.');

        [$data, $lines, $totals] = $this->validatedPayload($request, $companyId);

        $quotation = DB::transaction(function () use ($data, $lines, $totals, $numbers, $companyId, $branchId, $audit) {
            $quotation = Quotation::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'customer_id' => $data['customer_id'],
                'user_id' => auth()->id(),
                'number' => $numbers->next($companyId, 'quotation'),
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'status' => Quotation::STATUS_DRAFT,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $this->syncItems($quotation, $lines, $companyId);

            $audit->record('create', 'quotations', $quotation, null, $quotation->only([
                'number', 'customer_id', 'status', 'total',
            ]));

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('success', 'Quotation ' . $quotation->number . ' saved.');
    }

    public function show(Quotation $quotation)
    {
        $this->authorizePermission('quotations.view');

        $quotation->load(['customer', 'user', 'branch', 'company', 'items.product', 'convertedSale']);
        $user = auth()->user();

        return view('quotations.show', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'quotations.index',
            'quotation' => $quotation,
            'statuses' => $this->statusLabels(),
            'canUpdate' => $user->hasPermission('quotations.update') && in_array($quotation->status, self::EDITABLE, true),
            'canDelete' => $user->hasPermission('quotations.delete') && $quotation->status === Quotation::STATUS_DRAFT,
            'canSend' => $user->hasPermission('quotations.update') && $quotation->status === Quotation::STATUS_DRAFT,
            'canConvert' => $user->hasPermission('quotations.convert') && $this->canConvert($quotation),
        ]));
    }

    public function edit(Quotation $quotation)
    {
        $this->authorizePermission('quotations.update');
        abort_unless(in_array($quotation->status, self::EDITABLE, true), 422, 'Only draft or sent quotations can be edited.');

        $quotation->load('items.product');

        return view('quotations.edit', array_merge(fleet_shared_view_data(), $this->formData($quotation), [
            'activeMenu' => 'quotations.index',
        ]));
    }

    public function update(Request $request, Quotation $quotation, AuditLogger $audit)
    {
        $this->authorizePermission('quotations.update');
        abort_unless(in_array($quotation->status, self::EDITABLE, true), 422, 'Only draft or sent quotations can be edited.');

        $companyId = (int) auth()->user()->company_id;
        [$data, $lines, $totals] = $this->validatedPayload($request, $companyId);
        $before = $quotation->only(['customer_id', 'quote_date', 'valid_until', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'notes', 'terms']);

        DB::transaction(function () use ($quotation, $data, $lines, $totals, $companyId, $audit, $before) {
            $quotation->update([
                'customer_id' => $data['customer_id'],
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $lines, $companyId);

            $audit->record('update', 'quotations', $quotation, $before, $quotation->fresh()->only([
                'customer_id', 'quote_date', 'valid_until', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'notes', 'terms',
            ]));
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('success', 'Quotation ' . $quotation->number . ' updated.');
    }

    public function destroy(Quotation $quotation, AuditLogger $audit)
    {
        $this->authorizePermission('quotations.delete');
        abort_unless($quotation->status === Quotation::STATUS_DRAFT, 422, 'Only draft quotations can be deleted.');

        $before = $quotation->only(['number', 'customer_id', 'status', 'total']);
        $number = $quotation->number;

        DB::transaction(function () use ($quotation, $audit, $before) {
            $quotation->items()->delete();
            $quotation->delete();
            $audit->record('delete', 'quotations', $quotation, $before, null);
        });

        return redirect()->route('quotations.index')
            ->with('success', 'Quotation ' . $number . ' deleted.');
    }

    public function send(Quotation $quotation, AuditLogger $audit)
    {
        $this->authorizePermission('quotations.update');
        abort_unless($quotation->status === Quotation::STATUS_DRAFT, 422, 'Only draft quotations can be marked as sent.');

        $before = ['status' => $quotation->status];
        $quotation->update(['status' => Quotation::STATUS_SENT]);
        $audit->record('send', 'quotations', $quotation, $before, ['status' => Quotation::STATUS_SENT]);

        return redirect()->route('quotations.show', $quotation)
            ->with('success', 'Quotation marked as sent.');
    }

    public function convert(
        Quotation $quotation,
        DocumentNumberService $numbers,
        InventoryService $inventory,
        AccountingPoster $accounting,
        LoyaltyService $loyalty,
        AuditLogger $audit
    ) {
        $this->authorizePermission('quotations.convert');

        if (! $this->canConvert($quotation)) {
            return back()->with('error', 'This quotation cannot be converted.');
        }

        try {
            $sale = DB::transaction(function () use ($quotation, $numbers, $inventory, $accounting, $loyalty, $audit) {
                $locked = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

                if (! $this->canConvert($locked)) {
                    throw new \RuntimeException('This quotation was already converted or is not convertible.');
                }

                $locked->load(['items.product']);
                $companyId = (int) $locked->company_id;
                $branchId = (int) $locked->branch_id;
                $total = round((float) $locked->total, 2);

                $sale = Sale::query()->create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'customer_id' => $locked->customer_id,
                    'user_id' => auth()->id(),
                    'quotation_id' => $locked->id,
                    'document_type' => Sale::TYPE_INVOICE,
                    'number' => $numbers->next($companyId, 'sale'),
                    'invoice_number' => $numbers->next($companyId, 'invoice'),
                    'sale_date' => now(),
                    'due_date' => $locked->valid_until,
                    'status' => Sale::STATUS_COMPLETED,
                    'payment_status' => Sale::PAYMENT_UNPAID,
                    'subtotal' => $locked->subtotal,
                    'discount_percent' => 0,
                    'discount_amount' => $locked->discount_amount,
                    'tax_amount' => $locked->tax_amount,
                    'total' => $total,
                    'paid_amount' => 0,
                    'balance' => $total,
                    'notes' => trim(implode("\n", array_filter([
                        $locked->notes,
                        'Converted from quotation ' . $locked->number,
                    ]))) ?: null,
                ]);

                foreach ($locked->items as $item) {
                    $product = $item->product;
                    SaleItem::query()->create([
                        'company_id' => $companyId,
                        'sale_id' => $sale->id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => (int) ($item->product_variant_id ?: 0),
                        'name' => $item->description ?: optional($product)->name ?: 'Item',
                        'sku' => optional($product)->sku,
                        'size' => $item->size,
                        'color' => $item->color,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'cost_price' => optional($product)->purchase_price ?? 0,
                        'discount_amount' => $item->discount_amount,
                        'tax_rate' => $item->tax_rate,
                        'tax_amount' => $item->tax_amount,
                        'line_total' => $item->line_total,
                    ]);

                    if ($product && $product->manage_stock) {
                        $inventory->apply([
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'product_id' => $product->id,
                            'product_variant_id' => (int) ($item->product_variant_id ?: 0),
                            'type' => StockMovement::SALE,
                            'quantity_out' => (float) $item->quantity,
                            'unit_cost' => (float) ($product->purchase_price ?? 0),
                            'user_id' => auth()->id(),
                            'notes' => 'Invoice from quotation ' . $locked->number,
                            'reference_type' => Sale::class,
                            'reference_id' => $sale->id,
                            'reference_number' => $sale->number,
                            'occurred_at' => $sale->sale_date,
                        ]);
                    }
                }

                $before = [
                    'status' => $locked->status,
                    'converted_sale_id' => $locked->converted_sale_id,
                ];

                $locked->update([
                    'status' => Quotation::STATUS_CONVERTED,
                    'converted_sale_id' => $sale->id,
                ]);

                $sale = $sale->fresh(['items', 'customer']);
                $accounting->postSale($sale);
                $loyalty->earnForSale($sale);

                $audit->record('convert', 'quotations', $locked, $before, [
                    'status' => Quotation::STATUS_CONVERTED,
                    'converted_sale_id' => $sale->id,
                    'sale_number' => $sale->number,
                    'invoice_number' => $sale->invoice_number,
                ]);

                return $sale;
            });
        } catch (NegativeStockException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales.show', $sale)
            ->with('success', 'Quotation converted to invoice ' . ($sale->invoice_number ?: $sale->number) . '.');
    }

    public function print(Quotation $quotation)
    {
        $this->authorizePermission('quotations.view');

        $quotation->load(['customer', 'user', 'items.product', 'company', 'branch']);

        return view('quotations.print', array_merge(fleet_shared_view_data(), [
            'quotation' => $quotation,
            'statuses' => $this->statusLabels(),
        ]));
    }

    private function canConvert(Quotation $quotation): bool
    {
        if ($quotation->converted_sale_id) {
            return false;
        }

        if ($quotation->status === Quotation::STATUS_CONVERTED) {
            return false;
        }

        return in_array($quotation->status, self::CONVERTIBLE, true);
    }

    /**
     * @return array{0: array, 1: array<int, array>, 2: array{subtotal: float, discount_amount: float, tax_amount: float, total: float}}
     */
    private function validatedPayload(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'quote_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:quote_date',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'terms' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        $lines = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($data['items'] as $row) {
            $product = Product::query()->with('tax')->findOrFail($row['product_id']);
            $qty = (float) $row['quantity'];
            $unitPrice = (float) $row['unit_price'];
            $taxRate = array_key_exists('tax_rate', $row) && $row['tax_rate'] !== null && $row['tax_rate'] !== ''
                ? (float) $row['tax_rate']
                : (float) (optional($product->tax)->rate ?? 0);
            $lineDiscount = min(round((float) ($row['discount_amount'] ?? 0), 2), round($qty * $unitPrice, 2));
            $base = round($qty * $unitPrice, 2);
            $taxable = round(max(0, $base - $lineDiscount), 2);
            $taxAmt = round($taxable * ($taxRate / 100), 2);
            $lineTotal = round($taxable + $taxAmt, 2);

            $subtotal += $taxable;
            $taxTotal += $taxAmt;

            $lines[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => $lineDiscount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmt,
                'line_total' => $lineTotal,
            ];
        }

        $headerDiscount = min(round((float) ($data['discount_amount'] ?? 0), 2), round($subtotal, 2));
        $total = round(max(0, $subtotal - $headerDiscount + $taxTotal), 2);

        return [
            $data,
            $lines,
            [
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $headerDiscount,
                'tax_amount' => round($taxTotal, 2),
                'total' => $total,
            ],
        ];
    }

    /**
     * @param  array<int, array>  $lines
     */
    private function syncItems(Quotation $quotation, array $lines, int $companyId): void
    {
        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];
            QuotationItem::query()->create([
                'company_id' => $companyId,
                'quotation_id' => $quotation->id,
                'product_id' => $product->id,
                'product_variant_id' => 0,
                'description' => $product->name,
                'size' => $product->size,
                'color' => $product->color,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
            ]);
        }
    }

    private function formData(Quotation $quotation): array
    {
        $products = Product::query()
            ->with('tax')
            ->availableAtBranch($this->currentBranchId())
            ->where('is_active', true)
            ->where('for_sale', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'selling_price', 'tax_id', 'size', 'color']);

        return [
            'quotation' => $quotation,
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderByDesc('is_walk_in')
                ->orderBy('name')
                ->get(),
            'products' => $products,
            'productOptions' => $products->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'selling_price' => (float) $product->selling_price,
                    'tax_rate' => (float) (optional($product->tax)->rate ?? 0),
                ];
            })->values(),
            'statuses' => $this->statusLabels(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusLabels(): array
    {
        return [
            Quotation::STATUS_DRAFT => 'Draft',
            Quotation::STATUS_SENT => 'Sent',
            Quotation::STATUS_ACCEPTED => 'Accepted',
            Quotation::STATUS_CONVERTED => 'Converted',
            Quotation::STATUS_EXPIRED => 'Expired',
            Quotation::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
