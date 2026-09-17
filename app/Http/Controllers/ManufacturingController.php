<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Packaging;
use App\Models\PackagingItem;
use App\Models\PackagingSetup;
use App\Models\PackagingSetupItem;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionItem;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManufacturingController extends Controller
{
    public function __construct(private InventoryService $inventory)
    {
    }

    public function bomIndex()
    {
        $this->authorizeView();

        return view('manufacturing.bom-index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'manufacturing.bom',
            'boms' => Bom::query()->with(['product', 'branch'])->orderByDesc('id')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function bomCreate()
    {
        $this->authorizeManage();

        return view('manufacturing.bom-form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'manufacturing.bom.create',
            'bom' => new Bom(['branch_id' => $this->currentBranchId()]),
            'canManage' => true,
        ]));
    }

    public function bomStore(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateBom($request, $companyId);
        $lines = $this->normalizeLines($data['items'] ?? []);

        if ($lines->isEmpty()) {
            return back()->with('error', 'Add at least one BOM item.')->withInput();
        }

        DB::transaction(function () use ($data, $lines, $companyId) {
            $bom = Bom::query()->create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'product_id' => $data['product_id'],
                'user_id' => auth()->id(),
                'description' => $data['description'],
                'expected_production' => round((float) ($data['expected_production'] ?? 0), 4),
                'production_cost' => round($lines->sum('sub_total'), 2),
                'is_active' => true,
            ]);
            foreach ($lines as $line) {
                BomItem::query()->create(array_merge($line, ['bom_id' => $bom->id]));
            }
        });

        return redirect()->route('manufacturing.bom.index')->with('success', 'BOM saved.');
    }

    public function bomEdit(Bom $bom)
    {
        $this->authorizeManage();
        $bom->load('items');

        return view('manufacturing.bom-form', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'manufacturing.bom.create',
            'bom' => $bom,
            'canManage' => true,
        ]));
    }

    public function bomUpdate(Request $request, Bom $bom)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $this->validateBom($request, $companyId);
        $lines = $this->normalizeLines($data['items'] ?? []);

        if ($lines->isEmpty()) {
            return back()->with('error', 'Add at least one BOM item.')->withInput();
        }

        DB::transaction(function () use ($bom, $data, $lines) {
            $bom->update([
                'branch_id' => $data['branch_id'],
                'product_id' => $data['product_id'],
                'description' => $data['description'],
                'expected_production' => round((float) ($data['expected_production'] ?? 0), 4),
                'production_cost' => round($lines->sum('sub_total'), 2),
            ]);
            $bom->items()->delete();
            foreach ($lines as $line) {
                BomItem::query()->create(array_merge($line, ['bom_id' => $bom->id]));
            }
        });

        return redirect()->route('manufacturing.bom.index')->with('success', 'BOM updated.');
    }

    public function bomDestroy(Bom $bom)
    {
        $this->authorizeManage();
        if ($bom->productions()->exists()) {
            return back()->with('error', 'BOM has productions and cannot be deleted.');
        }
        $bom->items()->delete();
        $bom->delete();

        return redirect()->route('manufacturing.bom.index')->with('success', 'BOM deleted.');
    }

    public function productionIndex()
    {
        $this->authorizeView();

        return view('manufacturing.production-index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'manufacturing.production',
            'productions' => Production::query()->with('user')->orderByDesc('id')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function productionCreate()
    {
        $this->authorizeManage();

        return view('manufacturing.production-form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'manufacturing.production',
            'production' => new Production(['production_date' => now()->toDateString()]),
            'boms' => Bom::query()->with(['product', 'items.product'])->where('is_active', true)->orderByDesc('id')->get(),
            'canManage' => true,
        ]));
    }

    public function productionStore(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $request->validate([
            'production_date' => 'required|date',
            'bom_id' => ['required', Rule::exists('boms', 'id')->where('company_id', $companyId)],
            'expected_production' => 'required|numeric|min:0.0001',
            'description' => 'required|string|max:2000',
            'items' => 'nullable|array',
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string|max:255',
        ]);

        $bom = Bom::query()->with('items')->findOrFail($data['bom_id']);
        $qty = round((float) $data['expected_production'], 4);
        $lines = $this->normalizeLines($data['items'] ?? []);
        if ($lines->isEmpty()) {
            $factor = $bom->expected_production > 0 ? ($qty / (float) $bom->expected_production) : $qty;
            $lines = $bom->items->map(function (BomItem $item) use ($factor) {
                $quantity = round((float) $item->quantity * $factor, 4);
                $unit = (float) $item->unit_price;

                return [
                    'product_id' => $item->product_id,
                    'unit_price' => $unit,
                    'quantity' => $quantity,
                    'sub_total' => round($unit * $quantity, 2),
                    'description' => $item->description,
                ];
            });
        }

        if ($lines->isEmpty()) {
            return back()->with('error', 'No production items found.')->withInput();
        }

        $branchId = (int) ($bom->branch_id ?: $this->currentBranchId());
        $cost = round($lines->sum('sub_total'), 2);
        $product = Product::query()->findOrFail($bom->product_id);
        $title = trim(($product->name ?: 'Production') . ' production as on ' . $data['production_date']);

        DB::transaction(function () use ($data, $lines, $companyId, $branchId, $bom, $qty, $cost, $title) {
            $production = Production::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'bom_id' => $bom->id,
                'user_id' => auth()->id(),
                'title' => $title,
                'production_date' => $data['production_date'],
                'description' => $data['description'],
                'expected_production' => $qty,
                'actual_production' => $qty,
                'production_cost' => $cost,
            ]);

            foreach ($lines as $line) {
                ProductionItem::query()->create(array_merge($line, ['production_id' => $production->id]));
                $this->inventory->apply([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'product_id' => (int) $line['product_id'],
                    'type' => StockMovement::ISSUE,
                    'quantity_out' => $line['quantity'],
                    'unit_cost' => $line['unit_price'],
                    'reference_type' => Production::class,
                    'reference_id' => $production->id,
                    'user_id' => auth()->id(),
                    'notes' => 'Production materials',
                    'occurred_at' => $data['production_date'],
                ]);
            }

            $this->inventory->apply([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'product_id' => (int) $bom->product_id,
                'type' => StockMovement::ADJUSTMENT,
                'quantity_in' => $qty,
                'unit_cost' => $qty > 0 ? round($cost / $qty, 4) : 0,
                'reference_type' => Production::class,
                'reference_id' => $production->id,
                'user_id' => auth()->id(),
                'notes' => 'Finished production',
                'occurred_at' => $data['production_date'],
            ]);
        });

        return redirect()->route('manufacturing.production.index')->with('success', 'Production saved.');
    }

    public function productionDestroy(Production $production)
    {
        $this->authorizeManage();

        return back()->with('error', 'Delete production is disabled after stock posting.');
    }

    public function packagingSetupIndex()
    {
        $this->authorizeView();

        return view('manufacturing.packaging-setup-index', array_merge(fleet_shared_view_data(), $this->formLookups(), [
            'activeMenu' => 'manufacturing.packaging.setups',
            'setups' => PackagingSetup::query()->with(['product', 'user', 'items.product'])->orderByDesc('id')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function packagingSetupStore(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'description' => 'required|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.quantity' => 'nullable|numeric|min:0.0001',
        ]);

        $items = collect($data['items'])->filter(fn ($row) => ! empty($row['product_id']) && ((float) ($row['quantity'] ?? 0)) > 0);
        if ($items->isEmpty()) {
            return back()->with('error', 'Add at least one packaging item.')->withInput();
        }

        DB::transaction(function () use ($data, $items, $companyId) {
            $setup = PackagingSetup::query()->create([
                'company_id' => $companyId,
                'branch_id' => $this->currentBranchId(),
                'product_id' => $data['product_id'],
                'user_id' => auth()->id(),
                'description' => $data['description'],
            ]);
            foreach ($items as $item) {
                PackagingSetupItem::query()->create([
                    'packaging_setup_id' => $setup->id,
                    'product_id' => $item['product_id'],
                    'quantity' => round((float) $item['quantity'], 4),
                ]);
            }
        });

        return redirect()->route('manufacturing.packaging.setups')->with('success', 'Packaging setup saved.');
    }

    public function packagingSetupDestroy(PackagingSetup $setup)
    {
        $this->authorizeManage();
        $setup->items()->delete();
        $setup->delete();

        return redirect()->route('manufacturing.packaging.setups')->with('success', 'Packaging setup deleted.');
    }

    public function packagingIndex()
    {
        $this->authorizeView();

        return view('manufacturing.packaging-index', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'manufacturing.packaging',
            'packagings' => Packaging::query()->with(['product', 'user'])->orderByDesc('id')->get(),
            'canManage' => $this->canManage(),
        ]));
    }

    public function packagingCreate()
    {
        $this->authorizeManage();
        $branchId = (int) $this->currentBranchId();
        $products = Product::query()
            ->availableAtBranch($branchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($branchId) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock' => $this->inventory->quantityOnHand((int) auth()->user()->company_id, $branchId, (int) $product->id),
                ];
            });

        return view('manufacturing.packaging-form', array_merge(fleet_shared_view_data(), [
            'activeMenu' => 'manufacturing.packaging',
            'products' => $products,
            'canManage' => true,
        ]));
    }

    public function packagingStore(Request $request)
    {
        $this->authorizeManage();
        $companyId = (int) auth()->user()->company_id;
        $branchId = (int) $this->currentBranchId();
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.unit_control' => 'nullable|string|max:100',
            'items.*.qty_to_package' => 'nullable|numeric|min:0',
            'items.*.stock_packaged' => 'nullable|numeric|min:0',
        ]);

        $items = collect($data['items'])->filter(fn ($row) => ! empty($row['product_id']) && ((float) ($row['qty_to_package'] ?? 0)) > 0)
            ->map(function ($row) {
                $qty = round((float) $row['qty_to_package'], 4);

                return [
                    'product_id' => (int) $row['product_id'],
                    'unit_control' => $row['unit_control'] ?? null,
                    'qty_to_package' => $qty,
                    'stock_packaged' => round((float) ($row['stock_packaged'] ?? $qty), 4),
                ];
            });

        if ($items->isEmpty()) {
            return back()->with('error', 'Add at least one packaging line.')->withInput();
        }

        $bulkOut = round($items->sum('qty_to_package'), 4);

        DB::transaction(function () use ($data, $items, $companyId, $branchId, $bulkOut) {
            $packaging = Packaging::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'product_id' => $data['product_id'],
                'user_id' => auth()->id(),
                'packaging_date' => now()->toDateString(),
                'qty_packaged' => $bulkOut,
                'grand_total' => round($items->sum('stock_packaged'), 4),
            ]);

            $this->inventory->apply([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'product_id' => (int) $data['product_id'],
                'type' => StockMovement::CONVERSION,
                'quantity_out' => $bulkOut,
                'reference_type' => Packaging::class,
                'reference_id' => $packaging->id,
                'user_id' => auth()->id(),
                'notes' => 'Bulk packaging out',
            ]);

            foreach ($items as $item) {
                PackagingItem::query()->create(array_merge($item, ['packaging_id' => $packaging->id]));
                $this->inventory->apply([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'product_id' => $item['product_id'],
                    'type' => StockMovement::CONVERSION,
                    'quantity_in' => $item['stock_packaged'],
                    'reference_type' => Packaging::class,
                    'reference_id' => $packaging->id,
                    'user_id' => auth()->id(),
                    'notes' => 'Packaged stock in',
                ]);
            }
        });

        return redirect()->route('manufacturing.packaging.index')->with('success', 'Packaging saved.');
    }

    public function bomJson(Bom $bom)
    {
        $this->authorizeView();
        $bom->load(['items.product', 'product']);

        return response()->json([
            'id' => $bom->id,
            'product_id' => $bom->product_id,
            'product_name' => optional($bom->product)->name,
            'expected_production' => (float) $bom->expected_production,
            'production_cost' => (float) $bom->production_cost,
            'items' => $bom->items->map(fn (BomItem $item) => [
                'product_id' => $item->product_id,
                'name' => optional($item->product)->name,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (float) $item->quantity,
                'sub_total' => (float) $item->sub_total,
                'description' => $item->description,
            ]),
        ]);
    }

    private function validateBom(Request $request, int $companyId): array
    {
        return $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'description' => 'required|string|max:2000',
            'expected_production' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string|max:255',
        ]);
    }

    private function normalizeLines(array $items)
    {
        return collect($items)->filter(function ($row) {
            return ! empty($row['product_id']) && ((float) ($row['quantity'] ?? 0)) > 0;
        })->map(function ($row) {
            $qty = round((float) $row['quantity'], 4);
            $price = round((float) ($row['unit_price'] ?? 0), 2);

            return [
                'product_id' => (int) $row['product_id'],
                'unit_price' => $price,
                'quantity' => $qty,
                'sub_total' => round($price * $qty, 2),
                'description' => $row['description'] ?? null,
            ];
        })->values();
    }

    private function formLookups(): array
    {
        return [
            'products' => Product::query()
                ->availableAtBranch($this->currentBranchId())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'selectedBranchId' => $this->currentBranchId(),
        ];
    }

    private function authorizeView(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('products.view') || $user->hasPermission('inventory.view')), 403);
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasPermission('products.create') || $user->hasPermission('products.update') || $user->hasPermission('inventory.adjust')), 403);
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasPermission('products.create') || $user->hasPermission('products.update') || $user->hasPermission('inventory.adjust'));
    }
}
