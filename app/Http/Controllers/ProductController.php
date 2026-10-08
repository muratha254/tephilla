<?php

namespace App\Http\Controllers;

use App\Exceptions\NegativeStockException;
use App\Models\AuditLog;
use App\Models\Colour;
use App\Models\Folding;
use App\Models\FoldingOutput;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductBranchStock;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('products.view');

        $branchId = $this->currentBranchId();

        $products = Product::query()
            ->with(['category.parent', 'unit', 'tax', 'variants'])
            ->availableAtBranch($branchId)
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->whereIn('category_id', ProductCategory::idsIncludingChildren((int) $request->category_id));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->status === 'active') {
                    $query->where('is_active', true);
                } elseif ($request->status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->orderByDesc('id')
            ->get();

        $stockByVariant = collect();
        if ($branchId) {
            $stockByVariant = ProductBranchStock::query()
                ->withoutGlobalScope('branch')
                ->where('branch_id', $branchId)
                ->whereIn('product_id', $products->pluck('id'))
                ->get()
                ->groupBy(function ($row) {
                    return $row->product_id . ':' . (int) $row->product_variant_id;
                })
                ->map(function ($rows) {
                    return (float) $rows->sum('quantity');
                });
        }

        $usedByVariant = app(InventoryService::class)->consumedByVariant($branchId, $products->pluck('id'));

        return view('products.index', array_merge(fleet_shared_view_data(), $this->catalogLookups(), [
            'activeMenu' => 'products.index',
            'products' => $products,
            'stockByVariant' => $stockByVariant,
            'usedByVariant' => $usedByVariant,
            'selectedBranchId' => $branchId,
            'filters' => $request->only(['category_id', 'status']),
            'canCreate' => auth()->user()->hasPermission('products.create'),
            'canUpdate' => auth()->user()->hasPermission('products.update'),
            'canDelete' => auth()->user()->hasPermission('products.delete'),
            'canConvert' => auth()->user()->hasPermission('products.create'),
            'canViewCost' => auth()->user()->hasPermission('products.view_cost'),
            'currencyCode' => optional(auth()->user()->company)->currency_code ?? 'KES',
        ]));
    }

    public function create()
    {
        $this->authorizePermission('products.create');

        return view('products.form', array_merge(fleet_shared_view_data(), $this->catalogLookups(), $this->colourLookups(), [
            'activeMenu' => 'products.create',
            'selectedBranchId' => $this->currentBranchId(),
            'product' => new Product([
                'is_active' => true,
                'for_sale' => true,
                'manage_stock' => true,
                'allow_negative_stock' => false,
                'tax_inclusive' => true,
                'wholesale_price' => 0,
                'promo_price' => 0,
                'profit_margin' => 0,
                'sales_commission' => 0,
                'reorder_level' => 0,
            ]),
        ]));
    }

    public function store(Request $request, InventoryService $inventory, AuditLogger $audit)
    {
        $this->authorizePermission('products.create');

        $data = $this->validated($request);
        $data['company_id'] = auth()->user()->company_id;
        $data['sku'] = $data['sku'] ?: null;
        $data['barcode'] = $data['barcode'] ?: null;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $opening = (float) $request->input('opening_stock', 0);
        $colourIds = $data['colour_ids'] ?? [];
        unset($data['opening_stock'], $data['branch_id'], $data['colour_ids']);
        $this->rejectOpeningAcrossColours($colourIds, $opening);

        if (empty($data['barcode']) && ! empty($data['sku'])) {
            $data['barcode'] = $data['sku'];
        }

        $product = Product::query()->create($data);
        $branchId = $this->currentBranchId();
        abort_unless($branchId, 422, 'Select an active branch before creating items.');
        $this->assertBranchAccess($branchId);
        $variantId = $this->syncColours($product, $colourIds);

        try {
            $this->ensureColourStock($product, $branchId, $colourIds);
            $this->applyOpeningStock($inventory, $product, $branchId, $opening, StockMovement::OPENING, 'Opening stock', $variantId);
            $this->recordBatch($product, $branchId, $opening);
        } catch (NegativeStockException $e) {
            return redirect()->route('products.edit', $product)->with('error', $e->getMessage());
        }

        $audit->record('create', 'products', $product, null, $product->only(['name', 'sku', 'purchase_price', 'selling_price']));

        return redirect()->route('products.index')->with('success', 'Item saved.');
    }

    public function show(Product $product)
    {
        $this->authorizePermission('products.view');
        $this->authorizeProductBranch($product);

        $product->load(['category', 'brand', 'unit', 'tax']);
        $branchId = $this->currentBranchId();
        $qty = $product->quantityAtBranch($branchId);

        if ($branchId && $qty != 0.0 && ! $product->batches()->withoutGlobalScope('branch')->where('branch_id', $branchId)->exists()) {
            $this->recordBatch($product, $branchId, $qty);
        }

        $batches = $product->batches()
            ->withoutGlobalScope('branch')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with('branch')
            ->orderBy('id')
            ->get();
        $children = $product->children()->with(['unit', 'brand'])->orderBy('name')->get();
        $childStock = ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->whereIn('product_id', $children->pluck('id'))
            ->pluck('quantity', 'product_id');

        $foldingHistory = $this->foldingHistory($product);

        return view('products.show', array_merge(fleet_shared_view_data(), $this->catalogLookups(), [
            'activeMenu' => 'products.index',
            'product' => $product,
            'quantity' => $qty,
            'batches' => $batches,
            'children' => $children,
            'childStock' => $childStock,
            'usedOnFolding' => $foldingHistory['used'],
            'producedOnFolding' => $foldingHistory['produced'],
            'currencyCode' => optional(auth()->user()->company)->currency_code ?? 'KES',
            'canUpdate' => auth()->user()->hasPermission('products.update'),
            'canCreate' => auth()->user()->hasPermission('products.create'),
            'canViewCost' => auth()->user()->hasPermission('products.view_cost'),
        ]));
    }

    /**
     * Folding rows where this item was consumed, plus rows where it was produced.
     */
    private function foldingHistory(Product $product): array
    {
        $foldings = Folding::query()
            ->with(['colour', 'outputs.product', 'outputs.colour'])
            ->where('product_id', $product->id)
            ->orderByDesc('folded_on')
            ->orderByDesc('id')
            ->get();

        $movements = StockMovement::query()
            ->where('reference_type', Folding::class)
            ->where('type', StockMovement::FOLDING)
            ->where('product_id', $product->id)
            ->whereIn('reference_id', $foldings->pluck('id'))
            ->get()
            ->groupBy('reference_id');

        $used = $foldings->filter(function (Folding $folding) use ($movements) {
            $rows = $movements->get($folding->id, collect());

            return $folding->outputs->isNotEmpty()
                || $rows->contains(fn (StockMovement $row) => (float) $row->quantity_out > 0);
        })->values();

        $produced = FoldingOutput::query()
            ->with(['colour', 'folding.product', 'folding.colour'])
            ->where('product_id', $product->id)
            ->get()
            ->map(function (FoldingOutput $output) {
                $folding = $output->folding;
                if (! $folding) {
                    return null;
                }

                return [
                    'sort' => optional($folding->folded_on)->format('Y-m-d') . '-' . str_pad((string) $folding->id, 8, '0', STR_PAD_LEFT),
                    'date' => optional($folding->folded_on)->format('d/m/Y'),
                    'number' => $folding->number(),
                    'raw' => optional($folding->product)->name ?: '—',
                    'raw_colour' => optional($folding->colour)->name ?: '—',
                    'raw_qty' => (float) $folding->quantity,
                    'colour' => optional($output->colour)->name ?: '—',
                    'qty' => (float) $output->quantity,
                    'notes' => $folding->notes,
                    'status' => $folding->status ?: 'confirmed',
                ];
            })
            ->filter();

        $historical = $foldings->filter(function (Folding $folding) use ($movements) {
            if ($folding->outputs->isNotEmpty()) {
                return false;
            }
            $rows = $movements->get($folding->id, collect());

            return $rows->contains(fn (StockMovement $row) => (float) $row->quantity_in > 0)
                && ! $rows->contains(fn (StockMovement $row) => (float) $row->quantity_out > 0);
        })->map(function (Folding $folding) {
            return [
                'sort' => optional($folding->folded_on)->format('Y-m-d') . '-' . str_pad((string) $folding->id, 8, '0', STR_PAD_LEFT),
                'date' => optional($folding->folded_on)->format('d/m/Y'),
                'number' => $folding->number(),
                'raw' => '—',
                'raw_colour' => '—',
                'raw_qty' => null,
                'colour' => optional($folding->colour)->name ?: '—',
                'qty' => (float) $folding->quantity,
                'notes' => $folding->notes,
                'status' => $folding->status ?: 'confirmed',
            ];
        });

        return [
            'used' => $used,
            'produced' => $produced->concat($historical)->sortByDesc('sort')->values(),
        ];
    }

    public function updateImage(Request $request, Product $product)
    {
        $this->authorizePermission('products.update');

        $request->validate([
            'image' => 'required|image|max:2048',
        ]);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->update([
            'image_path' => $request->file('image')->store('products', 'public'),
        ]);

        return back()->with('success', 'Image updated.');
    }

    public function children(Product $product)
    {
        $this->authorizePermission('products.view');

        $rows = $product->children()->with('unit')->orderBy('name')->get()->map(function (Product $child) {
            return [
                'id' => $child->id,
                'code' => $child->item_code,
                'name' => $child->name,
                'unit' => optional($child->unit)->name,
                'rate' => (float) $child->conversion_rate,
                'selling_price' => (float) $child->selling_price,
            ];
        });

        return response()->json($rows);
    }

    public function storeChild(Request $request, Product $product)
    {
        $this->authorizePermission('products.create');

        $companyId = auth()->user()->company_id;
        $data = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'name' => 'required|string|max:255',
            'conversion_rate' => 'required|numeric|min:0.0001',
            'unit_id' => ['required', Rule::exists('units', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'selling_price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:2000',
        ]);

        $rate = (float) $data['conversion_rate'];

        Product::query()->create([
            'company_id' => $companyId,
            'parent_id' => $product->id,
            'conversion_rate' => $rate,
            'name' => $data['name'],
            'unit_id' => $data['unit_id'],
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'tax_id' => $product->tax_id,
            'description' => $data['description'] ?? null,
            'purchase_price' => $rate > 0 ? round((float) $product->purchase_price / $rate, 2) : $product->purchase_price,
            'selling_price' => $data['selling_price'],
            'wholesale_price' => $product->wholesale_price ?? 0,
            'promo_price' => $product->promo_price ?? 0,
            'reorder_level' => 0,
            'for_sale' => $product->for_sale,
            'manage_stock' => $product->manage_stock,
            'allow_negative_stock' => $product->allow_negative_stock,
            'tax_inclusive' => $product->tax_inclusive,
            'is_active' => true,
        ]);

        return back()->with('success', 'Child item created.');
    }

    public function storeChildStock(Request $request, Product $product, InventoryService $inventory)
    {
        $this->authorizePermission('products.create');

        $data = $request->validate([
            'batch_id' => 'required|exists:product_batches,id',
            'name' => 'required|string|max:255',
            'reorder_level' => 'required|numeric|min:0',
            'qty_to_convert' => 'required|numeric|min:0.0001',
            'qty_to_produce' => 'required|numeric|min:0.0001',
            'control_qty' => 'nullable|numeric|min:0.0001',
            'break_now' => 'required|in:0,1',
        ]);

        $batch = ProductBatch::query()
            ->where('product_id', $product->id)
            ->findOrFail($data['batch_id']);

        $rate = (float) $data['qty_to_produce'];
        $control = (float) ($data['control_qty'] ?? $rate);

        $child = Product::query()->firstOrNew([
            'company_id' => $product->company_id,
            'parent_id' => $product->id,
            'name' => $data['name'],
        ]);

        $child->fill([
            'unit_id' => $child->unit_id ?: $product->unit_id,
            'category_id' => $child->category_id ?: $product->category_id,
            'brand_id' => $child->brand_id ?: $product->brand_id,
            'tax_id' => $child->tax_id ?: $product->tax_id,
            'conversion_rate' => $control,
            'reorder_level' => $data['reorder_level'],
            'purchase_price' => $rate > 0 ? round((float) $product->purchase_price / $rate, 2) : $product->purchase_price,
            'selling_price' => $child->selling_price ?: $product->selling_price,
            'wholesale_price' => $child->wholesale_price ?: ($product->wholesale_price ?? 0),
            'promo_price' => $child->promo_price ?: ($product->promo_price ?? 0),
            'for_sale' => $product->for_sale,
            'manage_stock' => $product->manage_stock,
            'allow_negative_stock' => $product->allow_negative_stock,
            'tax_inclusive' => $product->tax_inclusive,
            'is_active' => true,
        ]);
        $child->save();

        if ($request->boolean('break_now')) {
            $qtyOut = (float) $data['qty_to_convert'];
            $qtyIn = round($qtyOut * $rate, 4);

            try {
                $out = $inventory->apply([
                    'company_id' => $product->company_id,
                    'branch_id' => $batch->branch_id,
                    'product_id' => $product->id,
                    'type' => StockMovement::CONVERSION,
                    'quantity_out' => $qtyOut,
                    'unit_cost' => $product->purchase_price,
                    'user_id' => auth()->id(),
                    'notes' => 'Break parent into child',
                    'reference_number' => 'CHILD',
                ]);

                $inventory->apply([
                    'company_id' => $child->company_id,
                    'branch_id' => $batch->branch_id,
                    'product_id' => $child->id,
                    'type' => StockMovement::CONVERSION,
                    'quantity_in' => $qtyIn,
                    'unit_cost' => $child->purchase_price,
                    'user_id' => auth()->id(),
                    'notes' => 'Child stock from parent',
                    'reference_type' => StockMovement::class,
                    'reference_id' => $out->id,
                    'reference_number' => 'CHILD',
                ]);
            } catch (NegativeStockException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }

            $batch->balance_qty = round((float) $batch->balance_qty - $qtyOut, 4);
            $batch->save();
            $this->recordBatch($child, (int) $batch->branch_id, $qtyIn);
        }

        return back()->with('success', 'Child item saved.');
    }

    public function edit(Product $product)
    {
        $this->authorizePermission('products.update');
        $this->authorizeProductBranch($product);

        $branchId = $this->currentBranchId();
        $lookups = $this->colourLookups($product);
        $colourStock = [];
        foreach ($product->variants()->where('is_active', true)->get() as $variant) {
            $colourStock[(string) $variant->colour_id] = round($product->quantityAtBranch($branchId, (int) $variant->id), 4);
        }
        $selectedColour = (string) ($lookups['selectedColourId'] ?? '');
        if ($selectedColour !== '') {
            $openingStock = $colourStock[$selectedColour] ?? 0;
        } elseif ($colourStock === []) {
            $openingStock = round($product->quantityAtBranch($branchId, 0), 4);
        } else {
            $openingStock = null;
        }

        $viewData = array_merge(fleet_shared_view_data(), $this->catalogLookups(), $lookups, [
            'activeMenu' => 'products.index',
            'selectedBranchId' => $branchId,
            'product' => $product,
            'openingStock' => $openingStock,
            'colourStock' => $colourStock,
        ]);
        if ($product->category_id && ! $viewData['categories']->contains(fn ($category) => (int) $category->id === (int) $product->category_id)) {
            $assigned = ProductCategory::withTrashed()->with('parent')->find($product->category_id);
            if ($assigned) {
                $viewData['categories']->push($assigned);
            }
        }

        return view('products.form', $viewData);
    }

    public function update(Request $request, Product $product, AuditLogger $audit, InventoryService $inventory)
    {
        $this->authorizePermission('products.update');
        $this->authorizeProductBranch($product);

        $before = $product->only(['name', 'sku', 'purchase_price', 'selling_price', 'wholesale_price']);
        $data = $this->validated($request, $product->id);
        $data['sku'] = $data['sku'] ?: null;
        $data['barcode'] = $data['barcode'] ?: null;

        if (empty($data['barcode']) && ! empty($data['sku'])) {
            $data['barcode'] = $data['sku'];
        }

        if ($request->boolean('remove_image') && $product->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $openingProvided = $request->filled('opening_stock');
        $opening = $openingProvided ? round((float) $request->input('opening_stock'), 4) : 0.0;
        $colourIds = $data['colour_ids'] ?? [];
        unset($data['opening_stock'], $data['branch_id'], $data['colour_ids']);
        $this->rejectOpeningAcrossColours($colourIds, $openingProvided ? $opening : 0.0);
        $product->update($data);

        $branchId = $this->currentBranchId();
        abort_unless($branchId, 422, 'Select an active branch before updating stock.');
        $this->assertBranchAccess($branchId);
        $variantId = $this->syncColours($product, $colourIds);
        $delta = 0.0;
        if ($openingProvided && count($colourIds) <= 1) {
            $current = round($product->quantityAtBranch($branchId, $variantId), 4);
            $delta = round($opening - $current, 4);
        }

        try {
            $this->ensureColourStock($product, $branchId, $colourIds);
            $this->applyOpeningStock($inventory, $product, $branchId, $delta, StockMovement::ADJUSTMENT, 'New opening stock', $variantId);
            $this->recordBatch($product, $branchId, $delta);
        } catch (NegativeStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $after = $product->only(['name', 'sku', 'purchase_price', 'selling_price', 'wholesale_price']);
        $priceChanged = $before['purchase_price'] != $after['purchase_price']
            || $before['selling_price'] != $after['selling_price']
            || $before['wholesale_price'] != $after['wholesale_price'];

        $audit->record($priceChanged ? 'price_change' : 'update', 'products', $product, $before, $after);

        return redirect()->route('products.index')->with('success', 'Item updated.');
    }

    public function destroy(Product $product, AuditLogger $audit)
    {
        $this->authorizePermission('products.delete');
        $this->authorizeProductBranch($product);

        $audit->record('delete', 'products', $product, $product->only(['name', 'sku']), null);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Item deleted.');
    }

    public function search(Request $request)
    {
        $this->authorizePermission('products.view');

        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $branchId = $this->currentBranchId();
        $products = Product::query()
            ->with(['tax', 'unit', 'variants'])
            ->availableAtBranch($branchId)
            ->where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('sku', 'like', '%' . $q . '%')
                    ->orWhere('barcode', 'like', '%' . $q . '%');
            })
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json($products->map(function (Product $product) use ($branchId) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'purchase_price' => (float) $product->purchase_price,
                'selling_price' => (float) $product->selling_price,
                'tax_rate' => (float) optional($product->tax)->rate,
                'unit' => optional($product->unit)->short_name ?: optional($product->unit)->name,
                'stock' => $product->quantityAtBranch($branchId),
                'expiry' => optional($product->expiry_date)->format('Y-m-d'),
                'variants' => $product->variants->where('is_active', true)->map(function (ProductVariant $variant) use ($product, $branchId) {
                    return [
                        'id' => $variant->id,
                        'name' => $variant->color,
                        'qty' => $product->quantityAtBranch($branchId, $variant->id),
                    ];
                })->values(),
            ];
        }));
    }

    public function labels()
    {
        $this->authorizePermission('products.view');

        return view('products.labels', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'products.labels',
        ]));
    }

    public function previewLabels(Request $request)
    {
        $this->authorizePermission('products.view');

        $payload = $this->labelSelection($request);
        if ($payload === null) {
            return response()->json(['message' => 'Add at least one item.'], 422);
        }

        return view('products.partials.label-stickers', $payload);
    }

    public function printLabels(Request $request)
    {
        $this->authorizePermission('products.view');

        $payload = $this->labelSelection($request);
        if ($payload === null) {
            return back()->with('error', 'Add at least one item.');
        }

        return view('products.labels-print', array_merge($payload, [
            'autoPrint' => $request->boolean('auto_print'),
            'systemName' => fleet_system_name(),
        ]));
    }

    private function labelSelection(Request $request): ?array
    {
        $items = $request->input('items', []);
        if (! is_array($items) || count($items) === 0) {
            return null;
        }

        $quantities = [];
        foreach ($items as $id => $qty) {
            $id = (int) $id;
            $qty = max(1, min(500, (int) $qty));
            if ($id > 0) {
                $quantities[$id] = $qty;
            }
        }

        if (count($quantities) === 0) {
            return null;
        }

        $products = Product::query()->whereIn('id', array_keys($quantities))->orderBy('name')->get();
        if ($products->isEmpty()) {
            return null;
        }

        $profile = fleet_company_profile();

        return [
            'products' => $products,
            'quantities' => $quantities,
            'companyName' => $profile['companyName'] ?? fleet_system_name(),
        ];
    }

    public function priceLog()
    {
        $this->authorizePermission('products.view');

        $logs = AuditLog::query()
            ->with('user')
            ->where('module', 'products')
            ->where('action', 'price_change')
            ->orderByDesc('created_at')
            ->get();

        $productNames = Product::withTrashed()
            ->whereIn('id', $logs->pluck('auditable_id')->filter()->unique())
            ->pluck('name', 'id');

        return view('products.price-log', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'products.prices',
            'logs' => $logs,
            'productNames' => $productNames,
        ]));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'sku' => $request->filled('sku') ? $request->input('sku') : null,
            'barcode' => $request->filled('barcode') ? $request->input('barcode') : null,
            'brand_id' => $request->filled('brand_id') ? $request->input('brand_id') : null,
            'colour_id' => $request->filled('colour_id') ? $request->input('colour_id') : null,
            'expiry_date' => $request->filled('expiry_date') ? $request->input('expiry_date') : null,
        ]);

        $companyId = auth()->user()->company_id;

        $uniqueSku = Rule::unique('products', 'sku')
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->whereNull('deleted_at');
            });
        $uniqueBarcode = Rule::unique('products', 'barcode')
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->whereNull('deleted_at');
            });

        if ($ignoreId) {
            $uniqueSku->ignore($ignoreId);
            $uniqueBarcode->ignore($ignoreId);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', 'max:64', $uniqueSku],
            'barcode' => ['nullable', 'string', 'max:64', $uniqueBarcode],
            'description' => 'nullable|string|max:2000',
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'category_id' => ['required', Rule::exists('product_categories', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'unit_id' => ['required', Rule::exists('units', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'tax_id' => ['required', Rule::exists('taxes', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'promo_price' => 'nullable|numeric|min:0',
            'profit_margin' => 'nullable|numeric',
            'sales_commission' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'size' => 'nullable|string|max:64',
            'color' => 'nullable|string|max:64',
            'image' => 'nullable|image|max:2048',
            'remove_image' => 'nullable|boolean',
            'manage_stock' => 'nullable|boolean',
            'allow_negative_stock' => 'nullable|boolean',
            'for_sale' => 'nullable|boolean',
            'tax_inclusive' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'opening_stock' => 'nullable|numeric',
            'colour_id' => ['nullable', 'integer', Rule::exists('colours', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
            'colour_ids' => 'nullable|array',
            'colour_ids.*' => ['integer', Rule::exists('colours', 'id')->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })],
        ]);

        $chosen = ProductCategory::query()->find($data['category_id']);
        if ($chosen && ProductCategory::query()->where('parent_id', $chosen->id)->exists()) {
            throw ValidationException::withMessages([
                'category_id' => 'Choose a product type under this category.',
            ]);
        }

        $data['manage_stock'] = $request->boolean('manage_stock');
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['for_sale'] = $request->boolean('for_sale');
        $data['tax_inclusive'] = $request->boolean('tax_inclusive');
        $data['is_active'] = $request->exists('is_active') ? $request->boolean('is_active') : true;
        $data['colour_ids'] = array_values(array_unique(array_filter(array_map('intval', $data['colour_ids'] ?? []))));
        if (! empty($data['colour_id'])) {
            $data['colour_ids'][] = (int) $data['colour_id'];
            $data['colour_ids'] = array_values(array_unique($data['colour_ids']));
        }
        unset($data['colour_id']);
        $data['purchase_price'] = $data['purchase_price'] ?? 0;
        $data['wholesale_price'] = $data['wholesale_price'] ?? 0;
        $data['promo_price'] = $data['promo_price'] ?? 0;
        $data['profit_margin'] = $data['profit_margin'] ?? 0;
        $data['sales_commission'] = $data['sales_commission'] ?? 0;
        $data['reorder_level'] = $data['reorder_level'] ?? 0;
        $data['branch_id'] = $this->currentBranchId();

        return $data;
    }

    private function authorizeProductBranch(Product $product): void
    {
        abort_unless(
            $product->isAvailableAtBranch($this->currentBranchId()),
            403,
            'This item is not available at the active branch.'
        );
    }

    private function ensureBranchStock(Product $product, int $branchId, int $variantId = 0): void
    {
        if (! $product->manage_stock || ! $branchId) {
            return;
        }

        ProductBranchStock::query()
            ->withoutGlobalScope('company')
            ->withoutGlobalScope('branch')
            ->firstOrCreate(
                [
                    'company_id' => $product->company_id,
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                ],
                [
                    'quantity' => 0,
                    'average_cost' => (float) ($product->purchase_price ?? 0),
                ]
            );
    }

    /**
     * @param  array<int, int>  $colourIds
     */
    private function ensureColourStock(Product $product, int $branchId, array $colourIds): void
    {
        if ($colourIds === []) {
            $this->ensureBranchStock($product, $branchId, 0);

            return;
        }

        $variantIds = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereIn('colour_id', $colourIds)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($variantIds as $variantId) {
            $this->ensureBranchStock($product, $branchId, (int) $variantId);
        }
    }

    /**
     * @param  array<int, int>  $colourIds
     */
    private function rejectOpeningAcrossColours(array $colourIds, float $opening): void
    {
        if (count($colourIds) > 1 && abs($opening) > 0.0001) {
            throw ValidationException::withMessages([
                'opening_stock' => 'Enter opening stock for one colour at a time.',
            ]);
        }
    }

    /**
     * @param  array<int, int>  $colourIds
     */
    private function syncColours(Product $product, array $colourIds): int
    {
        if ($colourIds === []) {
            return 0;
        }

        $primary = 0;
        foreach ($colourIds as $colourId) {
            $colour = Colour::query()->find($colourId);
            if (! $colour) {
                continue;
            }

            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('colour_id', $colour->id)
                ->first();

            if (! $variant) {
                $variant = ProductVariant::query()->create([
                    'company_id' => $product->company_id,
                    'product_id' => $product->id,
                    'colour_id' => $colour->id,
                    'color' => $colour->name,
                    'is_active' => true,
                ]);
            } else {
                $variant->update([
                    'color' => $colour->name,
                    'is_active' => true,
                ]);
            }

            if ($primary === 0) {
                $primary = (int) $variant->id;
            }
        }

        $product->update(['has_variants' => true]);

        ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereNotIn('colour_id', $colourIds)
            ->where('is_active', true)
            ->get()
            ->each(function (ProductVariant $variant) use ($product) {
                $onHand = ProductBranchStock::query()
                    ->withoutGlobalScope('branch')
                    ->where('product_id', $product->id)
                    ->where('product_variant_id', $variant->id)
                    ->sum('quantity');
                if (abs((float) $onHand) < 0.0001) {
                    $variant->update(['is_active' => false]);
                }
            });

        return count($colourIds) === 1 ? $primary : 0;
    }

    private function colourLookups(?Product $product = null): array
    {
        $selected = old('colour_ids');
        if ($selected === null && $product && $product->exists) {
            $selected = $product->variants()->where('is_active', true)->pluck('colour_id')->all();
        }
        $selected = collect($selected ?: [])->map(fn ($id) => (string) $id)->filter()->values();
        $selectedColourId = old('colour_id');
        if ($selectedColourId === null && $selected->count() === 1) {
            $selectedColourId = $selected->first();
        }

        return [
            'colours' => Colour::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedColourId' => (string) ($selectedColourId ?? ''),
            'selectedColourIds' => $selected->all(),
        ];
    }

    private function applyOpeningStock(
        InventoryService $inventory,
        Product $product,
        int $branchId,
        float $opening,
        string $type,
        string $notes,
        int $variantId = 0
    ): void {
        if ($opening == 0.0 || ! $product->manage_stock || ! $branchId) {
            return;
        }

        $payload = [
            'company_id' => $product->company_id,
            'branch_id' => $branchId,
            'product_id' => $product->id,
            'product_variant_id' => $variantId,
            'type' => $type,
            'unit_cost' => $product->purchase_price,
            'user_id' => auth()->id(),
            'notes' => $notes,
        ];

        if ($opening > 0) {
            $payload['quantity_in'] = $opening;
        } else {
            $payload['quantity_out'] = abs($opening);
        }

        $inventory->apply($payload);
    }

    private function recordBatch(Product $product, int $branchId, float $qty): void
    {
        if ($qty == 0.0 || ! $branchId || ! $product->manage_stock) {
            return;
        }

        $onHand = (float) ProductBranchStock::query()
            ->withoutGlobalScope('branch')
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->sum('quantity');

        ProductBatch::query()->create([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'branch_id' => $branchId,
            'batch_date' => now()->toDateString(),
            'expiry_date' => optional($product->expiry_date)->format('Y-m-d'),
            'cost_price' => $product->purchase_price ?? 0,
            'retail_price' => $product->selling_price ?? 0,
            'wholesale_price' => $product->wholesale_price ?? 0,
            'promo_price' => $product->promo_price ?? 0,
            'stocked_qty' => abs($qty),
            'balance_qty' => $onHand,
            'is_active' => true,
        ]);
    }
}
