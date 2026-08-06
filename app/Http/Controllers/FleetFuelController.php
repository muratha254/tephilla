<?php

namespace App\Http\Controllers;

use App\Models\FleetDriver;
use App\Models\FleetStockItem;
use App\Models\FleetStockMovement;
use App\Models\FleetVehicle;
use App\Models\FleetFuelVendor;
use App\Models\FleetVehicleFuelRefill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FleetFuelController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $query = $this->filteredFuelQuery($search, $dateFrom, $dateTo)->with(['vehicle', 'driver']);

        $refills = $query
            ->orderByDesc('refill_date')
            ->orderByDesc('id')
            ->get();

        $summaryQuery = $this->filteredFuelQuery($search, $dateFrom, $dateTo);
        $recordCount = (clone $summaryQuery)->count();
        $totalCost = (float) (clone $summaryQuery)->sum('cost');
        $totalVolume = (float) (clone $summaryQuery)->sum('liters');

        return view('fleet.fuel.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'fuel-list',
            'openMenu' => 'fuel',
            'refills' => $refills,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'recordCount' => $recordCount,
            'totalCost' => $totalCost,
            'totalVolume' => $totalVolume,
        ]));
    }

    public function create()
    {
        return view('fleet.fuel.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'fuel-add',
            'openMenu' => 'fuel',
        ], $this->formOptions()));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedRefill($request);
        $vehicle = FleetVehicle::query()->findOrFail($validated['fleet_vehicle_id']);

        $this->assertTankCapacity($vehicle, (float) $validated['liters']);

        DB::transaction(function () use ($request, $vehicle, $validated) {
            if ($validated['source'] === 'tank') {
                $this->applyTankStockChange($validated, (float) $validated['liters'], false);
            }

            $validated['receipt_path'] = $this->storeReceipt($request, null);
            $refill = $vehicle->fuelRefills()->create($validated);
            $vehicle->current_fuel = round($vehicle->fuelBalance() + (float) $validated['liters'], 2);
            $vehicle->save();

            unset($refill);
        });

        return redirect()
            ->route('fuel.index')
            ->with('success', 'Fuel record added successfully.');
    }

    public function edit(FleetVehicleFuelRefill $fuel)
    {
        return view('fleet.fuel.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'fuel-add',
            'openMenu' => 'fuel',
            'refill' => $fuel,
        ], $this->formOptions()));
    }

    public function update(Request $request, FleetVehicleFuelRefill $fuel)
    {
        $validated = $this->validatedRefill($request);
        $vehicle = FleetVehicle::query()->findOrFail($validated['fleet_vehicle_id']);
        $previousVehicleId = (int) $fuel->fleet_vehicle_id;
        $previousLiters = (float) $fuel->liters;
        $previousSource = $fuel->source ?? 'tank';
        $previousStockItemId = $fuel->fleet_stock_item_id;

        DB::transaction(function () use ($request, $fuel, $validated, $vehicle, $previousVehicleId, $previousLiters, $previousSource, $previousStockItemId) {
            if ($previousSource === 'tank' && $previousStockItemId) {
                $this->restoreTankStock($previousStockItemId, $previousLiters);
            }

            if ($previousVehicleId !== (int) $vehicle->id) {
                $previousVehicle = FleetVehicle::query()->findOrFail($previousVehicleId);
                $previousVehicle->current_fuel = max(0, round($previousVehicle->fuelBalance() - $previousLiters, 2));
                $previousVehicle->save();

                $this->assertTankCapacity($vehicle, (float) $validated['liters']);
            } else {
                $available = $vehicle->availableFuelCapacity() + $previousLiters;
                if ((float) $validated['liters'] > $available) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'liters' => 'Cannot exceed available tank space (' . number_format($available, 2) . ' L).',
                    ]);
                }
            }

            if ($validated['source'] === 'tank') {
                $this->applyTankStockChange($validated, (float) $validated['liters'], false);
            } else {
                $validated['fleet_stock_item_id'] = null;
            }

            $validated['receipt_path'] = $this->storeReceipt($request, $fuel->receipt_path);
            $fuel->update($validated);

            if ($previousVehicleId !== (int) $vehicle->id) {
                $vehicle->current_fuel = round($vehicle->fuelBalance() + (float) $validated['liters'], 2);
            } else {
                $vehicle->current_fuel = max(0, round($vehicle->fuelBalance() - $previousLiters + (float) $validated['liters'], 2));
            }

            $vehicle->save();
        });

        return redirect()
            ->route('fuel.index')
            ->with('success', 'Fuel record updated successfully.');
    }

    public function destroy(FleetVehicleFuelRefill $fuel)
    {
        DB::transaction(function () use ($fuel) {
            $vehicle = $fuel->vehicle;
            $liters = (float) $fuel->liters;

            if (($fuel->source ?? 'tank') === 'tank' && $fuel->fleet_stock_item_id) {
                $this->restoreTankStock($fuel->fleet_stock_item_id, $liters);
            }

            if ($fuel->receipt_path) {
                Storage::disk('public')->delete($fuel->receipt_path);
            }

            $fuel->delete();

            if ($vehicle) {
                $vehicle->current_fuel = max(0, round($vehicle->fuelBalance() - $liters, 2));
                $vehicle->save();
            }
        });

        return redirect()
            ->route('fuel.index')
            ->with('success', 'Fuel record deleted.');
    }

    private function formOptions(): array
    {
        $fuelStock = $this->fuelStockItem();

        return [
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
            'drivers' => FleetDriver::query()->where('status', 'Active')->orderBy('name')->get(),
            'fuelTypes' => $this->fuelTypes(),
            'vendors' => FleetFuelVendor::query()->orderBy('name')->get(),
            'fuelStockLiters' => $fuelStock?->quantity ?? 0,
            'fuelStockUnitPrice' => $fuelStock?->unit_price ?? 0,
            'fuelStockItemId' => $fuelStock?->id,
        ];
    }

    private function fuelStockItem(): ?FleetStockItem
    {
        return FleetStockItem::query()
            ->where('status', 'Active')
            ->where(function ($query) {
                $query->where('name', 'like', '%Fuel%')
                    ->orWhere('name', 'like', '%Diesel%')
                    ->orWhere('name', 'like', '%Petrol%');
            })
            ->orderByDesc('quantity')
            ->first();
    }

    private function validatedRefill(Request $request): array
    {
        $validated = $request->validate([
            'fleet_vehicle_id' => 'required|exists:fleet_vehicles,id',
            'fleet_driver_id' => 'required|exists:fleet_drivers,id',
            'fuel_type' => ['required', Rule::in($this->fuelTypes())],
            'source' => ['required', Rule::in(['tank', 'vendor'])],
            'fleet_fuel_vendor_id' => 'nullable|required_if:source,vendor|exists:fleet_fuel_vendors,id',
            'fleet_stock_item_id' => 'nullable|exists:fleet_stock_items,id',
            'refill_date' => 'required|date',
            'odometer' => 'required|integer|min:0',
            'liters' => 'required|numeric|min:0.01',
            'cost_per_liter' => 'nullable|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'receipt' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        $liters = (float) $validated['liters'];
        $costPerLiter = isset($validated['cost_per_liter']) ? (float) $validated['cost_per_liter'] : null;

        if ($costPerLiter !== null && $costPerLiter >= 0) {
            $validated['cost'] = round($liters * $costPerLiter, 2);
        }

        $validated['payment_method'] = 'Cash';
        $validated['reference_no'] = null;

        if ($validated['source'] === 'tank') {
            $stock = $this->fuelStockItem();
            $validated['fleet_stock_item_id'] = $stock?->id;
            $validated['fleet_fuel_vendor_id'] = null;
        } else {
            $validated['fleet_stock_item_id'] = null;
        }

        unset($validated['cost_per_liter'], $validated['receipt']);

        return $validated;
    }

    private function applyTankStockChange(array &$validated, float $liters, bool $restore): void
    {
        $stockId = $validated['fleet_stock_item_id'] ?? $this->fuelStockItem()?->id;

        if (! $stockId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'liters' => 'No fuel stock item is configured in Parts Stock.',
            ]);
        }

        $stock = FleetStockItem::query()->lockForUpdate()->findOrFail($stockId);

        if (! $restore && $stock->quantity < $liters) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'liters' => 'Not enough fuel in stock. Available: ' . number_format($stock->quantity) . ' L.',
            ]);
        }

        if ($restore) {
            $stock->increment('quantity', (int) ceil($liters));
            return;
        }

        $stock->decrement('quantity', (int) ceil($liters));
        $stock->refresh();

        FleetStockMovement::create([
            'fleet_stock_item_id' => $stock->id,
            'type' => 'usage',
            'quantity_change' => -(int) ceil($liters),
            'quantity_after' => $stock->quantity,
            'unit_price' => $stock->unit_price,
            'description' => 'Fuel issued to vehicle',
            'notes' => 'Fuel tank issue',
        ]);

        $validated['fleet_stock_item_id'] = $stock->id;
    }

    private function restoreTankStock(int $stockItemId, float $liters): void
    {
        $stock = FleetStockItem::query()->lockForUpdate()->find($stockItemId);

        if (! $stock) {
            return;
        }

        $stock->increment('quantity', (int) ceil($liters));
        $stock->refresh();

        FleetStockMovement::create([
            'fleet_stock_item_id' => $stock->id,
            'type' => 'adjustment',
            'quantity_change' => (int) ceil($liters),
            'quantity_after' => $stock->quantity,
            'unit_price' => $stock->unit_price,
            'description' => 'Fuel issue reversed',
            'notes' => 'Fuel record updated/deleted',
        ]);
    }

    private function storeReceipt(Request $request, ?string $existingPath): ?string
    {
        if (! $request->hasFile('receipt')) {
            return $existingPath;
        }

        if ($existingPath) {
            Storage::disk('public')->delete($existingPath);
        }

        return $request->file('receipt')->store('fuel-receipts', 'public');
    }

    private function filteredFuelQuery(string $search, string $dateFrom, string $dateTo)
    {
        $query = FleetVehicleFuelRefill::query();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('reference_no', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%')
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('registration_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('driver', function ($driverQuery) use ($search) {
                        $driverQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($dateFrom !== '') {
            $query->whereDate('refill_date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('refill_date', '<=', $dateTo);
        }

        return $query;
    }

    private function assertTankCapacity(FleetVehicle $vehicle, float $liters): void
    {
        $available = $vehicle->availableFuelCapacity();

        if ($liters > $available) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'liters' => 'Cannot add more than available tank space (' . number_format($available, 2) . ' L).',
            ]);
        }
    }

    private function fuelTypes(): array
    {
        return ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'];
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
