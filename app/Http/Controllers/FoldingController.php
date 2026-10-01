<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\Colour;
use App\Models\Folding;
use App\Models\FoldingOutput;
use App\Models\Product;
use App\Models\ProductBranchStock;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\IdempotencyService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FoldingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('inventory.view');

        $query = Folding::query()
            ->with(['product.unit', 'colour', 'user', 'branch', 'outputs.product', 'outputs.colour'])
            ->orderByDesc('folded_on')
            ->orderByDesc('id');

        if ($request->filled('from')) {
            $query->whereDate('folded_on', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('folded_on', '<=', $request->input('to'));
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }
        if ($request->filled('colour_id')) {
            $query->where('colour_id', (int) $request->input('colour_id'));
        }
        if ($request->filled('employee')) {
            $query->where('employee_name', 'like', '%' . $request->input('employee') . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('output_product_id')) {
            $query->whereHas('outputs', fn ($output) => $output->where('product_id', (int) $request->input('output_product_id')));
        }

        return view('folding.index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'folding.index',
            'foldings' => $query->paginate(25)->withQueryString(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'colours' => Colour::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['from', 'to', 'product_id', 'colour_id', 'employee', 'status', 'output_product_id']),
            'canRecord' => auth()->user()->hasPermission('inventory.adjust'),
        ]));
    }

    public function create()
    {
        $this->authorizePermission('inventory.adjust');

        [$materials, $finished] = $this->productionProducts();

        return view('folding.form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'folding.create',
            'products' => $materials,
            'finishedProducts' => $finished,
            'materialStock' => $this->materialStock($materials, $this->currentBranchId()),
            'colours' => Colour::query()->where('is_active', true)->orderBy('name')->get(),
        ]));
    }

    public function store(Request $request, InventoryService $inventory, AuditLogger $audit, IdempotencyService $idempotency)
    {
        $this->authorizePermission('inventory.adjust');
        $companyId = (int) auth()->user()->company_id;
        $branchId = $this->currentBranchId();
        abort_unless($branchId, 422, 'Select a branch first.');

        $data = $request->validate([
            'folded_on' => 'required|date',
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'colour_id' => ['nullable', Rule::exists('colours', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->where('is_active', true);
            })],
            'quantity' => 'required|numeric|min:0.0001',
            'effect' => 'nullable|in:produce,consume',
            'employee_name' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
            'outputs' => 'nullable|array',
            'outputs.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'outputs.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $companyId)],
            'outputs.*.quantity' => 'nullable|numeric|min:0',
        ]);

        $product = Product::query()->with('unit')->findOrFail($data['product_id']);
        $colour = ! empty($data['colour_id']) ? Colour::query()->findOrFail($data['colour_id']) : null;

        $outputs = collect($data['outputs'] ?? [])
            ->filter(function ($row) {
                return ! empty($row['product_id']) && (float) ($row['quantity'] ?? 0) > 0;
            })
            ->values();
        $this->assertColourAvailable($product, $colour, 'colour_id');
        foreach ($outputs as $row) {
            $finished = Product::query()->findOrFail($row['product_id']);
            $finishedColour = ! empty($row['colour_id']) ? Colour::query()->find($row['colour_id']) : null;
            $this->assertColourAvailable($finished, $finishedColour, 'outputs');
        }
        $quantity = round((float) $data['quantity'], 4);
        $consume = $outputs->isNotEmpty() || ($data['effect'] ?? 'produce') === 'consume';
        $narration = trim((string) ($data['notes'] ?? ''));
        $token = $idempotency->key($request, 'folding', [
            'branch_id' => $branchId,
            'folded_on' => $data['folded_on'],
            'product_id' => $product->id,
            'colour_id' => $colour ? $colour->id : null,
            'quantity' => $quantity,
            'effect' => $consume ? 'consume' : 'produce',
            'employee_name' => $data['employee_name'] ?? null,
            'notes' => $narration,
            'outputs' => $outputs->map(fn ($row) => [
                'product_id' => (int) $row['product_id'],
                'colour_id' => ! empty($row['colour_id']) ? (int) $row['colour_id'] : null,
                'quantity' => round((float) $row['quantity'], 4),
            ])->all(),
        ]);

        try {
        $result = $idempotency->remember(
            $companyId,
            (int) auth()->id(),
            'folding',
            $token,
            function () use ($data, $product, $colour, $quantity, $consume, $narration, $outputs, $companyId, $branchId, $inventory, $audit) {
                $folding = DB::transaction(function () use ($data, $product, $colour, $quantity, $consume, $narration, $outputs, $companyId, $branchId, $inventory) {
            $variantId = $this->variantFor($product, $colour, $companyId);

            if ($consume) {
                $onHand = $inventory->quantityOnHand($companyId, $branchId, $product->id, $variantId);
                if (round($onHand, 4) + 0.00005 < $quantity) {
                    throw new NegativeStockException($this->shortageMessage($product, $colour, $onHand));
                }
            }

            $buyingPrice = null;
            if ($outputs->isNotEmpty()) {
                $onHandForCost = $inventory->quantityOnHand($companyId, $branchId, $product->id, $variantId);
                $sheetPrice = (float) $product->purchase_price;
                $basis = $this->materialBasis($branchId, (int) $product->id, $variantId, $onHandForCost);
                $producedQty = round((float) $outputs->sum(fn ($row) => (float) $row['quantity']), 4);
                if ($sheetPrice <= 0 || $basis <= 0 || $producedQty <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'product_id' => "Enter a purchase price and quantity for {$product->name} before the buying price can be calculated.",
                    ]);
                }
                $buyingPrice = round(($sheetPrice / $basis) * $quantity / $producedQty, 2);
            }

            $folding = Folding::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'colour_id' => $colour ? $colour->id : null,
                'unit_id' => $product->unit_id,
                'user_id' => auth()->id(),
                'employee_name' => trim((string) ($data['employee_name'] ?? '')),
                'folded_on' => $data['folded_on'],
                'quantity' => $quantity,
                'notes' => $narration !== '' ? $narration : null,
            ]);

            $inventory->apply([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'type' => StockMovement::FOLDING,
                'quantity_in' => $consume ? 0 : $quantity,
                'quantity_out' => $consume ? $quantity : 0,
                'unit_cost' => $product->purchase_price,
                'reference_type' => Folding::class,
                'reference_id' => $folding->id,
                'reference_number' => 'FLD-' . $folding->id,
                'user_id' => auth()->id(),
                'notes' => $narration !== '' ? $narration : ('Folding ' . $product->name . ($colour ? ' / ' . $colour->name : '')),
                'occurred_at' => $data['folded_on'],
                'allow_negative' => false,
            ]);

            foreach ($outputs as $row) {
                $finished = Product::query()->with('unit')->findOrFail($row['product_id']);
                $finishedColour = ! empty($row['colour_id']) ? Colour::query()->findOrFail($row['colour_id']) : null;
                $finishedVariant = $this->variantFor($finished, $finishedColour, $companyId);
                if ($finished->variants()->where('is_active', true)->exists() && ! $finishedColour) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'outputs' => "Select a colour for {$finished->name}.",
                    ]);
                }
                $finishedQty = round((float) $row['quantity'], 4);
                if ($buyingPrice !== null) {
                    $finished->update(['purchase_price' => $buyingPrice]);
                    if ($finishedVariant) {
                        ProductVariant::query()->whereKey($finishedVariant)->update(['purchase_price' => $buyingPrice]);
                    }
                }

                FoldingOutput::query()->create([
                    'folding_id' => $folding->id,
                    'company_id' => $companyId,
                    'product_id' => $finished->id,
                    'product_variant_id' => $finishedVariant,
                    'colour_id' => $finishedColour ? $finishedColour->id : null,
                    'unit_id' => $finished->unit_id,
                    'quantity' => $finishedQty,
                    'unit_cost' => $buyingPrice,
                ]);

                $inventory->apply([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'product_id' => $finished->id,
                    'product_variant_id' => $finishedVariant,
                    'type' => StockMovement::FOLDING,
                    'quantity_in' => $finishedQty,
                    'unit_cost' => $buyingPrice ?? $finished->purchase_price,
                    'reference_type' => Folding::class,
                    'reference_id' => $folding->id,
                    'reference_number' => 'FLD-' . $folding->id,
                    'user_id' => auth()->id(),
                    'notes' => $narration !== '' ? $narration : ('Produced ' . $finished->name . ($finishedColour ? ' / ' . $finishedColour->name : '')),
                    'occurred_at' => $data['folded_on'],
                    'allow_negative' => false,
                ]);
            }

            return $folding;
                });

                $audit->record('create', 'folding', $folding, null, [
                    'branch_id' => $folding->branch_id,
                    'product_id' => $folding->product_id,
                    'colour_id' => $folding->colour_id,
                    'quantity' => (string) $folding->quantity,
                    'notes' => $folding->notes,
                    'employee_name' => $folding->employee_name,
                    'outputs' => $outputs->map(fn ($row) => [
                        'product_id' => (int) $row['product_id'],
                        'colour_id' => ! empty($row['colour_id']) ? (int) $row['colour_id'] : null,
                        'quantity' => round((float) $row['quantity'], 4),
                    ])->all(),
                ]);

                return $folding;
            }
        );
        } catch (NegativeStockException $e) {
            $variantId = 0;
            if ($colour) {
                $variant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('colour_id', $colour->id)
                    ->first();
                $variantId = $variant ? (int) $variant->id : 0;
            }
            $onHand = $inventory->quantityOnHand($companyId, $branchId, $product->id, $variantId);

            return back()->withInput()->with('error', $this->shortageMessage($product, $colour, $onHand));
        }

        $message = $result['replay']
            ? 'This folding was already recorded.'
            : ($outputs->isNotEmpty()
                ? 'Folding recorded. Flat sheet was used and the finished items were added.'
                : ($consume ? 'Folding recorded. This colour stock was reduced.' : 'Folding recorded. Finished stock increased.'));

        return redirect()->route('folding.index')->with('success', $message);
    }

    public function void(Request $request, Folding $folding, InventoryService $inventory, AuditLogger $audit)
    {
        $this->authorizePermission('inventory.adjust');
        $data = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        if ($folding->status === 'voided') {
            return back()->with('error', 'This folding was already reversed.');
        }

        try {
            DB::transaction(function () use ($folding, $inventory, $data) {
                $locked = Folding::query()->whereKey($folding->id)->lockForUpdate()->first();
                if ($locked->status === 'voided') {
                    throw new \RuntimeException('This folding was already reversed.');
                }

                $movements = StockMovement::query()
                    ->withoutGlobalScope('company')
                    ->withoutGlobalScope('branch')
                    ->where('reference_type', Folding::class)
                    ->where('reference_id', $locked->id)
                    ->where('type', StockMovement::FOLDING)
                    ->get();

                foreach ($movements as $movement) {
                    $inventory->apply([
                        'company_id' => $movement->company_id,
                        'branch_id' => $movement->branch_id,
                        'product_id' => $movement->product_id,
                        'product_variant_id' => (int) $movement->product_variant_id,
                        'type' => StockMovement::FOLDING_REVERSAL,
                        'quantity_in' => (float) $movement->quantity_out,
                        'quantity_out' => (float) $movement->quantity_in,
                        'unit_cost' => $movement->unit_cost,
                        'reference_type' => Folding::class,
                        'reference_id' => $locked->id,
                        'reference_number' => 'FLD-' . $locked->id . '-REV',
                        'user_id' => auth()->id(),
                        'notes' => 'Reversal of ' . $locked->number() . '. ' . $data['void_reason'],
                        'occurred_at' => now(),
                        'allow_negative' => false,
                    ]);
                }

                $locked->update([
                    'status' => 'voided',
                    'voided_at' => now(),
                    'voided_by' => auth()->id(),
                    'void_reason' => $data['void_reason'],
                ]);
            });
        } catch (NegativeStockException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $audit->record('void', 'folding', $folding, null, [
            'branch_id' => $folding->branch_id,
            'void_reason' => $data['void_reason'],
            'voided_by' => auth()->id(),
        ]);

        return back()->with('success', $folding->number() . ' was reversed.');
    }

    public function flatSheet()
    {
        $this->authorizePermission('inventory.view');
        $branchId = $this->currentBranchId();
        $product = Product::query()
            ->whereIn('name', ['Flat Sheet', 'Flatsheets'])
            ->orderBy('name')
            ->first();

        $balances = collect();
        $movements = collect();
        if ($product && $branchId) {
            $balances = ProductBranchStock::query()
                ->withoutGlobalScope('branch')
                ->with('variant')
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->where('product_variant_id', '>', 0)
                ->orderBy('product_variant_id')
                ->get();
            $movements = StockMovement::query()
                ->with(['variant', 'user'])
                ->where('product_id', $product->id)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        return view('folding.flat-sheet', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'folding.flat-sheet',
            'product' => $product,
            'balances' => $balances,
            'movements' => $movements,
            'branchName' => optional(\App\Models\Branch::query()->find($branchId))->name,
        ]));
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function productionProducts(): array
    {
        $flatIds = ProductCategory::query()
            ->whereIn('name', ['Flat Sheets / Raw Materials', 'Flat Sheet'])
            ->pluck('id');
        $materialCategoryIds = [];
        foreach ($flatIds as $id) {
            $materialCategoryIds = array_merge($materialCategoryIds, ProductCategory::idsIncludingChildren((int) $id));
        }
        $materialCategoryIds = array_values(array_unique($materialCategoryIds));

        $materials = Product::query()
            ->where('is_active', true)
            ->when($materialCategoryIds !== [], fn ($query) => $query->whereIn('category_id', $materialCategoryIds))
            ->with('unit')
            ->orderBy('name')
            ->get();
        $finished = Product::query()
            ->where('is_active', true)
            ->when($materialCategoryIds !== [], function ($query) use ($materialCategoryIds) {
                $query->where(function ($inner) use ($materialCategoryIds) {
                    $inner->whereNotIn('category_id', $materialCategoryIds)->orWhereNull('category_id');
                });
            })
            ->with('unit')
            ->orderBy('name')
            ->get();

        if ($materials->isEmpty()) {
            $materials = Product::query()->where('is_active', true)->with('unit')->orderBy('name')->get();
        }

        return [$materials, $finished];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $materials
     * @return array<string, array<int, array{id: string, name: string, qty: float}>>
     */
    private function materialStock($materials, ?int $branchId): array
    {
        $materials->load(['variants.colour']);
        $rows = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereIn('product_id', $materials->pluck('id'))
            ->get()
            ->groupBy(fn ($row) => $row->product_id . ':' . (int) $row->product_variant_id);

        $map = [];
        foreach ($materials as $product) {
            $variants = $product->variants->where('is_active', true)->values();
            $entries = [];
            if ($variants->isEmpty()) {
                $bucket = $rows->get($product->id . ':0');
                $entries[] = [
                    'id' => '',
                    'name' => '',
                    'qty' => round($bucket ? (float) $bucket->sum('quantity') : 0, 4),
                    'basis' => 0,
                ];
                $entries[0]['basis'] = $this->materialBasis((int) $branchId, (int) $product->id, 0, $entries[0]['qty']);
            } else {
                foreach ($variants as $variant) {
                    $bucket = $rows->get($product->id . ':' . $variant->id);
                    $qty = round($bucket ? (float) $bucket->sum('quantity') : 0, 4);
                    $entries[] = [
                        'id' => (string) $variant->colour_id,
                        'name' => $variant->color ?: optional($variant->colour)->name,
                        'qty' => $qty,
                        'basis' => $this->materialBasis((int) $branchId, (int) $product->id, (int) $variant->id, $qty),
                    ];
                }
            }
            $map[(string) $product->id] = $entries;
        }

        return $map;
    }

    private function materialBasis(int $branchId, int $productId, int $variantId, float $onHand): float
    {
        if ($branchId <= 0) {
            return round($onHand, 4);
        }

        $lastInId = StockMovement::query()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('quantity_in', '>', 0)
            ->max('id');

        $usedSince = 0.0;
        if ($lastInId) {
            $usedSince = (float) StockMovement::query()
                ->withoutGlobalScope('branch')
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->where('id', '>', $lastInId)
                ->sum('quantity_out');
        }

        return round($onHand + $usedSince, 4);
    }

    private function shortageMessage(Product $product, ?Colour $colour, float $available): string
    {
        $label = trim(($colour ? $colour->name . ' ' : '') . $product->name);
        $availableText = rtrim(rtrim(number_format(max(0, $available), 4, '.', ''), '0'), '.');

        return "Insufficient {$label} stock. Available: {$availableText}.";
    }

    private function assertColourAvailable(Product $product, ?Colour $colour, string $field): void
    {
        $hasColours = $product->variants()->where('is_active', true)->exists();
        if (! $hasColours) {
            return;
        }
        if (! $colour) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => "Select a colour for {$product->name}.",
            ]);
        }
        $available = $product->variants()->where('is_active', true)->where('colour_id', $colour->id)->exists();
        if (! $available) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => "That colour is not available for {$product->name}.",
            ]);
        }
    }

    private function variantFor(Product $product, ?Colour $colour, int $companyId): int
    {
        if (! $colour) {
            return 0;
        }

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('colour_id', $colour->id)
            ->first();

        if (! $variant) {
            $variant = ProductVariant::query()->create([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'colour_id' => $colour->id,
                'color' => $colour->name,
                'purchase_price' => $product->purchase_price,
                'selling_price' => $product->selling_price,
                'is_active' => true,
            ]);
            $product->update(['has_variants' => true]);
        } elseif (! $variant->is_active) {
            $variant->update(['is_active' => true, 'color' => $colour->name]);
            $product->update(['has_variants' => true]);
        }

        return (int) $variant->id;
    }
}
