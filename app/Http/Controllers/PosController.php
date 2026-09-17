<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\DocumentNumberService;
use App\Services\InventoryService;
use App\Services\SettingsService;
use App\Services\AccountingPoster;
use App\Services\LoyaltyService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function index()
    {
        $this->authorizePermission('pos.view');

        $walkIn = $this->walkInCustomer();
        $customers = Customer::query()
            ->where('is_active', true)
            ->orderByDesc('is_walk_in')
            ->orderBy('name')
            ->get();

        $heldCount = Sale::query()
            ->where('status', Sale::STATUS_HELD)
            ->where('document_type', Sale::TYPE_POS)
            ->count();

        return view('pos.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'sales.pos',
            'customers' => $customers,
            'walkInId' => $walkIn ? $walkIn->id : null,
            'heldCount' => $heldCount,
            'canCreateCustomer' => auth()->user()->hasPermission('customers.create'),
            'paymentMethods' => config('sellix.payment_methods', []),
        ]));
    }

    public function catalog(Request $request)
    {
        $this->authorizePermission('pos.view');

        $branchId = $this->currentBranchId();
        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 6;

        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->where('show_on_pos', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Product::query()
            ->with('tax')
            ->availableAtBranch($branchId)
            ->where('is_active', true)
            ->where('for_sale', true)
            ->when($categoryId, function ($builder) use ($categoryId) {
                $builder->where('category_id', $categoryId);
            })
            ->orderBy('name');

        $total = $query->count();
        $products = $query->forPage($page, $perPage)->get();

        return response()->json([
            'categories' => $categories,
            'products' => $products->map(function (Product $product) use ($branchId) {
                return $this->productPayload($product, $branchId);
            })->values(),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function search(Request $request)
    {
        $this->authorizePermission('pos.view');

        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $branchId = $this->currentBranchId();
        $products = Product::query()
            ->with('tax')
            ->availableAtBranch($branchId)
            ->where('is_active', true)
            ->where('for_sale', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('sku', 'like', '%' . $q . '%')
                    ->orWhere('barcode', $q);
            })
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json($products->map(function (Product $product) use ($branchId) {
            return $this->productPayload($product, $branchId);
        })->values());
    }

    public function storeCustomer(Request $request)
    {
        $this->authorizePermission('customers.create');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
        ]);

        $customer = Customer::query()->create([
            'company_id' => auth()->user()->company_id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'type' => Customer::TYPE_REGULAR,
            'is_walk_in' => false,
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);
    }

    public function holds()
    {
        $this->authorizePermission('pos.view');

        $holds = Sale::query()
            ->with('customer')
            ->where('status', Sale::STATUS_HELD)
            ->orderByDesc('held_at')
            ->orderByDesc('id')
            ->get();

        return response()->json($holds->map(function (Sale $sale) {
            return [
                'id' => $sale->id,
                'number' => $sale->number,
                'customer' => optional($sale->customer)->name ?: 'WALK-IN',
                'total' => number_format((float) $sale->total, 2),
                'held_at' => optional($sale->held_at ?: $sale->created_at)->format('d-m-Y H:i'),
                'url' => route('pos.holds.show', $sale),
            ];
        }));
    }

    public function showHold(Sale $sale)
    {
        $this->authorizePermission('pos.view');
        abort_unless($sale->status === Sale::STATUS_HELD, 404);

        $sale->load('items.product.tax');
        $branchId = $this->currentBranchId();

        return response()->json([
            'id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'due_date' => optional($sale->due_date)->format('Y-m-d'),
            'discount_amount' => (float) $sale->discount_amount,
            'items' => $sale->items->map(function (SaleItem $item) use ($branchId) {
                $payload = $item->product
                    ? $this->productPayload($item->product, $branchId)
                    : [
                        'id' => $item->product_id,
                        'name' => $item->name,
                        'price' => (float) $item->unit_price,
                        'selling_price' => (float) $item->unit_price,
                        'tax_rate' => (float) $item->tax_rate,
                        'tax_inclusive' => true,
                        'qty' => 0,
                        'cost' => (float) $item->cost_price,
                    ];
                $payload['qty_sold'] = (float) $item->quantity;

                return $payload;
            })->values(),
        ]);
    }

    public function hold(Request $request, DocumentNumberService $numbers)
    {
        $this->authorizePermission('pos.operate');

        $data = $this->validateCart($request);
        $sale = $this->writeSale($data, $numbers, Sale::STATUS_HELD, 0, null);

        return response()->json([
            'id' => $sale->id,
            'number' => $sale->number,
            'held_count' => Sale::query()->where('status', Sale::STATUS_HELD)->count(),
        ]);
    }

    public function order(Request $request, DocumentNumberService $numbers, InventoryService $inventory, AccountingPoster $accounting, LoyaltyService $loyalty, AuditLogger $audit)
    {
        $this->authorizePermission('pos.operate');

        $data = $this->validateCart($request);
        $data = array_merge($data, $request->validate([
            'payment_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:2000',
            'service_type' => 'nullable|string|max:32',
            'payments' => 'nullable|array',
            'payments.*.method' => 'required_with:payments|string|max:32',
            'payments.*.amount' => 'required_with:payments|numeric|min:0.01',
            'payments.*.reference' => 'nullable|string|max:64',
            'payments.*.bank_name' => 'nullable|string|max:128',
        ]));

        $payments = collect($data['payments'] ?? [])
            ->filter(function ($row) {
                return (float) ($row['amount'] ?? 0) > 0;
            })
            ->values();

        $payAmount = (float) $payments->sum('amount');
        if ($payAmount <= 0) {
            $payAmount = (float) ($data['payment_amount'] ?? 0);
            if ($payAmount > 0) {
                $payments = collect([[
                    'method' => $data['payment_method'] ?: Payment::METHOD_CASH,
                    'amount' => $payAmount,
                ]]);
            }
        }

        $noteParts = array_filter([
            $data['service_type'] ?? null,
            $data['notes'] ?? null,
        ]);
        $data['notes'] = $noteParts ? implode(' | ', $noteParts) : null;

        try {
            $sale = DB::transaction(function () use ($data, $numbers, $inventory, $payAmount, $payments, $accounting, $loyalty, $audit) {
                $sale = $this->writeSale($data, $numbers, Sale::STATUS_COMPLETED, $payAmount, $inventory);

                foreach ($payments as $row) {
                    Payment::query()->create([
                        'company_id' => $sale->company_id,
                        'branch_id' => $sale->branch_id,
                        'user_id' => auth()->id(),
                        'customer_id' => $sale->customer_id,
                        'number' => $numbers->next($sale->company_id, 'payment'),
                        'payable_type' => Sale::class,
                        'payable_id' => $sale->id,
                        'method' => $row['method'],
                        'amount' => (float) $row['amount'],
                        'reference' => $row['reference'] ?? null,
                        'paid_at' => now(),
                        'notes' => trim(implode(' | ', array_filter([
                            $row['bank_name'] ?? null,
                            $data['notes'] ?? null,
                        ]))) ?: null,
                    ]);
                }

                $sale->load(['items.product', 'payments']);
                $accounting->postSale($sale);
                $loyalty->earnForSale($sale);
                $audit->record('create', 'sales', $sale, null, [
                    'number' => $sale->number,
                    'total' => $sale->total,
                    'paid_amount' => $sale->paid_amount,
                    'status' => $sale->status,
                ]);

                return $sale;
            });
        } catch (NegativeStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $sale->id,
            'number' => $sale->number,
            'invoice_number' => $sale->invoice_number,
            'receipt_number' => $sale->receipt_number,
            'receipt_url' => route('pos.receipt', $sale),
        ]);
    }

    public function receipt(Sale $sale, SettingsService $settings)
    {
        $this->authorizePermission('pos.view');
        abort_unless($sale->status === Sale::STATUS_COMPLETED, 404);

        $sale->load(['items', 'customer', 'cashier', 'payments']);
        $paper = $settings->get((int) $sale->company_id, 'receipt_paper_size', '80mm');
        if (! in_array($paper, ['58mm', '80mm'], true)) {
            $paper = '80mm';
        }

        return view('pos.receipt', [
            'sale' => $sale,
            'paper' => $paper,
            'profile' => fleet_company_profile(),
            'paymentLabels' => config('sellix.payment_methods', []),
        ]);
    }

    private function validateCart(Request $request): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'hold_id' => 'nullable|integer|exists:sales,id',
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'sale_date' => 'required|date',
            'due_date' => 'nullable|date',
            'document_type' => 'required|in:pos,invoice',
            'discount_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);
    }

    private function writeSale(array $data, DocumentNumberService $numbers, string $status, float $payAmount, ?InventoryService $inventory): Sale
    {
        $companyId = auth()->user()->company_id;
        $branchId = $this->currentBranchId();
        abort_unless($branchId, 422, 'Select a branch first.');

        $hold = null;
        if (! empty($data['hold_id'])) {
            $hold = Sale::query()->where('status', Sale::STATUS_HELD)->find($data['hold_id']);
        }

        $lines = [];
        foreach ($data['items'] as $row) {
            $product = Product::query()->with('tax')->findOrFail($row['product_id']);
            abort_unless(
                $product->isAvailableAtBranch($branchId),
                403,
                'Item is not available at the active branch.'
            );
            $payload = $this->productPayload($product, $branchId);
            $qty = (float) $row['quantity'];
            $lineTax = round($payload['tax_amount'] * $qty, 2);
            $lineTotal = round($payload['price'] * $qty, 2);
            $lines[] = [
                'product' => $product,
                'quantity' => $qty,
                'unit_price' => $payload['selling_price'],
                'display_price' => $payload['price'],
                'cost_price' => $payload['cost'],
                'tax_rate' => $payload['tax_rate'],
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
            ];
        }

        $subtotal = round(collect($lines)->sum('line_total'), 2);
        $taxTotal = round(collect($lines)->sum('tax_amount'), 2);
        $discount = min($subtotal, (float) ($data['discount_amount'] ?? 0));
        $total = round(max(0, $subtotal - $discount), 2);
        $paid = min($total, $payAmount);
        $isInvoice = ($data['document_type'] ?? Sale::TYPE_POS) === Sale::TYPE_INVOICE;

        $attrs = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $data['customer_id'],
            'user_id' => auth()->id(),
            'document_type' => $isInvoice ? Sale::TYPE_INVOICE : Sale::TYPE_POS,
            'sale_date' => $data['sale_date'],
            'due_date' => $data['due_date'] ?? null,
            'status' => $status,
            'payment_status' => $status === Sale::STATUS_HELD
                ? Sale::PAYMENT_UNPAID
                : ($paid <= 0 ? Sale::PAYMENT_UNPAID : ($paid + 0.009 >= $total ? Sale::PAYMENT_PAID : Sale::PAYMENT_PARTIAL)),
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $taxTotal,
            'total' => $total,
            'paid_amount' => $status === Sale::STATUS_HELD ? 0 : $paid,
            'balance' => $status === Sale::STATUS_HELD ? $total : round($total - $paid, 2),
            'notes' => $data['notes'] ?? null,
            'held_at' => $status === Sale::STATUS_HELD ? now() : null,
        ];

        if ($hold) {
            $hold->items()->delete();
            $hold->update($attrs);
            $sale = $hold;
        } else {
            $attrs['number'] = $numbers->next($companyId, 'sale');
            if ($status === Sale::STATUS_COMPLETED) {
                if ($isInvoice) {
                    $attrs['invoice_number'] = $numbers->next($companyId, 'invoice');
                } else {
                    $attrs['receipt_number'] = $numbers->next($companyId, 'receipt');
                }
            }
            $sale = Sale::query()->create($attrs);
        }

        if ($status === Sale::STATUS_COMPLETED && ! $sale->invoice_number && ! $sale->receipt_number) {
            $sale->update($isInvoice
                ? ['invoice_number' => $numbers->next($companyId, 'invoice')]
                : ['receipt_number' => $numbers->next($companyId, 'receipt')]);
        }

        foreach ($lines as $line) {
            SaleItem::query()->create([
                'company_id' => $companyId,
                'sale_id' => $sale->id,
                'product_id' => $line['product']->id,
                'name' => $line['product']->name,
                'sku' => $line['product']->sku,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'cost_price' => $line['cost_price'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
            ]);

            if ($inventory && $status === Sale::STATUS_COMPLETED && $line['product']->manage_stock) {
                $inventory->apply([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'product_id' => $line['product']->id,
                    'type' => StockMovement::POS_SALE,
                    'quantity_out' => $line['quantity'],
                    'unit_cost' => $line['cost_price'],
                    'user_id' => auth()->id(),
                    'notes' => 'POS ' . $sale->number,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'reference_number' => $sale->number,
                    'occurred_at' => $sale->sale_date,
                ]);
            }
        }

        return $sale->fresh();
    }

    private function cartTotals(array $data): array
    {
        $branchId = $this->currentBranchId();
        $subtotal = 0;
        foreach ($data['items'] as $row) {
            $product = Product::query()->with('tax')->find($row['product_id']);
            if (! $product) {
                continue;
            }
            $payload = $this->productPayload($product, $branchId);
            $subtotal += $payload['price'] * (float) $row['quantity'];
        }
        $discount = min($subtotal, (float) ($data['discount_amount'] ?? 0));

        return ['total' => round(max(0, $subtotal - $discount), 2)];
    }

    private function productPayload(Product $product, ?int $branchId): array
    {
        $rate = (float) optional($product->tax)->rate;
        $selling = (float) $product->selling_price;
        $inclusive = (bool) $product->tax_inclusive;
        $price = $inclusive ? $selling : round($selling * (1 + $rate / 100), 2);
        $taxAmount = $rate > 0
            ? ($inclusive ? round($selling * $rate / (100 + $rate), 2) : round($selling * $rate / 100, 2))
            : 0;
        $stock = 0;
        if ($branchId) {
            $stock = $product->quantityAtBranch($branchId);
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'selling_price' => $selling,
            'wholesale_price' => (float) $product->wholesale_price,
            'price' => $price,
            'tax_rate' => $rate,
            'tax_amount' => $taxAmount,
            'tax_inclusive' => $inclusive,
            'qty' => $stock,
            'cost' => (float) $product->purchase_price,
            'image' => $product->image_path ? asset('storage/' . ltrim($product->image_path, '/')) : null,
        ];
    }

    private function walkInCustomer(): Customer
    {
        $customer = Customer::query()->where('is_walk_in', true)->first();
        if ($customer) {
            return $customer;
        }

        return Customer::query()->create([
            'company_id' => auth()->user()->company_id,
            'name' => 'WALK-IN',
            'type' => Customer::TYPE_WALK_IN,
            'is_walk_in' => true,
            'is_active' => true,
        ]);
    }
}
