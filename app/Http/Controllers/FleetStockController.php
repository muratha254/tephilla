<?php

namespace App\Http\Controllers;

use App\Models\FleetStockItem;
use App\Models\FleetStockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FleetStockController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $viewMode = $request->query('view', 'grid') === 'list' ? 'list' : 'grid';

        $query = FleetStockItem::query();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $items = $query->orderBy('name')->get();
        $itemCount = FleetStockItem::query()->count();
        $totalAssetValue = FleetStockItem::query()
            ->get()
            ->sum(fn (FleetStockItem $item) => $item->assetValue());

        return view('fleet.stock.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'parts-list',
            'openMenu' => 'parts',
            'items' => $items,
            'search' => $search,
            'viewMode' => $viewMode,
            'itemCount' => $itemCount,
            'totalAssetValue' => $totalAssetValue,
        ]));
    }

    public function create()
    {
        return view('fleet.stock.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'parts-add',
            'openMenu' => 'parts',
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedItem($request);

        DB::transaction(function () use ($validated) {
            $item = FleetStockItem::create($validated);

            if ($item->quantity > 0) {
                $this->recordMovement($item, 'purchase', $item->quantity, $item->quantity, 'Initial stock');
            }
        });

        return redirect()
            ->route('stock.index')
            ->with('success', 'Stock item added successfully.');
    }

    public function edit(FleetStockItem $stock)
    {
        return view('fleet.stock.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'parts-list',
            'openMenu' => 'parts',
            'item' => $stock,
        ]));
    }

    public function update(Request $request, FleetStockItem $stock)
    {
        $validated = $this->validatedItem($request);
        $previousQuantity = $stock->quantity;

        DB::transaction(function () use ($stock, $validated, $previousQuantity) {
            $stock->update($validated);

            $difference = $stock->quantity - $previousQuantity;

            if ($difference !== 0) {
                $this->recordMovement(
                    $stock,
                    'adjustment',
                    $difference,
                    $stock->quantity,
                    'Updated from edit form'
                );
            }
        });

        return redirect()
            ->route('stock.index')
            ->with('success', 'Stock item updated successfully.');
    }

    public function destroy(FleetStockItem $stock)
    {
        $stock->delete();

        return redirect()
            ->route('stock.index')
            ->with('success', 'Stock item deleted.');
    }

    public function adjust(Request $request, FleetStockItem $stock)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'purchased_from' => 'nullable|string|max:150',
            'cost' => 'required|numeric|min:0',
            'payment_status' => ['required', Rule::in($this->paymentStatuses())],
            'description' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($stock, $validated) {
            $stock->increment('quantity', $validated['quantity']);
            $stock->unit_price = $validated['cost'];
            $stock->save();
            $stock->refresh();

            FleetStockMovement::create([
                'fleet_stock_item_id' => $stock->id,
                'type' => 'purchase',
                'quantity_change' => (int) $validated['quantity'],
                'quantity_after' => $stock->quantity,
                'unit_price' => $validated['cost'],
                'purchased_from' => $validated['purchased_from'] ?? null,
                'payment_status' => $validated['payment_status'],
                'description' => $validated['description'] ?? null,
                'notes' => 'Stock added from inventory',
            ]);
        });

        return redirect()
            ->route('stock.index')
            ->with('success', 'Stock added successfully.');
    }

    public function history(FleetStockItem $stock)
    {
        $movements = $stock->movements()->paginate(15);

        return view('fleet.stock.history', array_merge($this->sharedViewData(), [
            'activeMenu' => 'parts-list',
            'openMenu' => 'parts',
            'item' => $stock,
            'movements' => $movements,
        ]));
    }

    public function purchases(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = FleetStockMovement::query()
            ->with('item')
            ->where('type', 'purchase');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('notes', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('purchased_from', 'like', '%' . $search . '%')
                    ->orWhereHas('item', function ($itemQuery) use ($search) {
                        $itemQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $purchases = $query->latest('id')->paginate(15)->withQueryString();

        return view('fleet.stock.purchases', array_merge($this->sharedViewData(), [
            'activeMenu' => 'parts-purchases',
            'openMenu' => 'parts',
            'purchases' => $purchases,
            'search' => $search,
        ]));
    }

    private function validatedItem(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'quantity' => 'required|integer|min:0',
            'unit_price' => 'required|numeric|min:0',
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function recordMovement(
        FleetStockItem $item,
        string $type,
        int $quantityChange,
        int $quantityAfter,
        ?string $notes = null
    ): void {
        FleetStockMovement::create([
            'fleet_stock_item_id' => $item->id,
            'type' => $type,
            'quantity_change' => $quantityChange,
            'quantity_after' => $quantityAfter,
            'unit_price' => $item->unit_price,
            'notes' => $notes,
        ]);
    }

    private function paymentStatuses(): array
    {
        return ['Paid', 'Pending', 'Partial'];
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
