<?php

namespace App\Http\Controllers;

use App\Models\FleetDriver;
use App\Models\FleetTrip;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleGroup;
use App\Models\FleetVehicleManufacturer;
use App\Models\FleetVehicleModel;
use App\Models\FleetVehicleType;
use App\Services\FleetVehicleAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FleetVehicleController extends Controller
{
    public function index()
    {
        $vehicles = FleetVehicle::query()
            ->latest()
            ->get()
            ->map(fn (FleetVehicle $vehicle) => $this->formatVehicleRow($vehicle));

        return view('fleet.vehicles.index', [
            'activeMenu' => 'vehicle-list',
            'openMenu' => 'vehicle',
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
            'vehicles' => $vehicles,
            'counts' => [
                'all' => $vehicles->count(),
                'active' => $vehicles->where('status', 'Active')->count(),
                'inactive' => $vehicles->where('status', 'Inactive')->count(),
                'low_fuel' => $vehicles->where('low_fuel', true)->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('fleet.vehicles.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'vehicle-add',
            'openMenu' => 'vehicle',
            'vehicle' => null,
            'formOptions' => $this->formOptions(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validateVehicle($request);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('vehicles', 'public');
        }

        $validated['current_fuel'] = $validated['opening_fuel'] ?? 0;
        unset($validated['image']);

        FleetVehicle::create($validated);

        return redirect()
            ->route('vehicles.index')
            ->with('success', 'Vehicle created successfully.');
    }

    public function show(FleetVehicle $vehicle, FleetVehicleAvailabilityService $availability)
    {
        $vehicle->load(['fuelRefills', 'maintenances']);

        $trips = FleetTrip::query()
            ->where('fleet_vehicle_id', $vehicle->id)
            ->latest('start_date')
            ->latest('id')
            ->get();

        return view('fleet.vehicles.show', array_merge($this->sharedViewData(), [
            'activeMenu' => 'vehicle-list',
            'openMenu' => 'vehicle',
            'vehicle' => $vehicle,
            'trips' => $trips,
            'totalTrips' => $trips->count(),
            'totalDistance' => 0,
            'paymentMethods' => $this->paymentMethods(),
            'bookingAvailability' => $availability->currentAvailability($vehicle),
        ]));
    }

    public function edit(FleetVehicle $vehicle)
    {
        return view('fleet.vehicles.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'vehicle-list',
            'openMenu' => 'vehicle',
            'vehicle' => $vehicle,
            'formOptions' => $this->formOptions(),
        ]));
    }

    public function update(Request $request, FleetVehicle $vehicle)
    {
        $validated = $this->validateVehicle($request, $vehicle);

        if ($request->hasFile('image')) {
            if ($vehicle->image_path) {
                Storage::disk('public')->delete($vehicle->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('vehicles', 'public');
        }

        if (array_key_exists('opening_fuel', $validated)) {
            $validated['current_fuel'] = $validated['opening_fuel'];
        }

        unset($validated['image']);

        $vehicle->update($validated);

        return redirect()
            ->route('vehicles.show', $vehicle)
            ->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(FleetVehicle $vehicle)
    {
        if ($vehicle->image_path) {
            Storage::disk('public')->delete($vehicle->image_path);
        }

        $vehicle->delete();

        return redirect()
            ->route('vehicles.index')
            ->with('success', 'Vehicle deleted successfully.');
    }

    public function storeFuelRefill(Request $request, FleetVehicle $vehicle)
    {
        $validated = $request->validate([
            'refill_date' => 'required|date',
            'fleet_driver_id' => 'nullable|exists:fleet_drivers,id',
            'odometer' => 'nullable|integer|min:0',
            'liters' => 'required|numeric|min:0.01',
            'cost_per_liter' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        if ($request->filled('cost_per_liter')) {
            $validated['cost'] = round((float) $validated['liters'] * (float) $validated['cost_per_liter'], 2);
        }

        unset($validated['cost_per_liter']);

        $available = $vehicle->availableFuelCapacity();

        if ((float) $validated['liters'] > $available) {
            return redirect()
                ->route('vehicles.show', $vehicle)
                ->withInput()
                ->withErrors([
                    'liters' => 'Cannot add more than available tank space (' . number_format($available, 2) . ' L).',
                ])
                ->with('open_fuel_modal', true);
        }

        DB::transaction(function () use ($vehicle, $validated) {
            $vehicle->fuelRefills()->create($validated);

            $vehicle->current_fuel = round($vehicle->fuelBalance() + (float) $validated['liters'], 2);
            $vehicle->save();
        });

        return redirect()
            ->route('vehicles.show', $vehicle)
            ->with('success', 'Fuel refill recorded. Current balance: ' . number_format($vehicle->fresh()->fuelBalance(), 2) . ' L.');
    }

    public function destroyFuelRefill(FleetVehicle $vehicle, FleetVehicleFuelRefill $fuelRefill)
    {
        if ((int) $fuelRefill->fleet_vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        DB::transaction(function () use ($vehicle, $fuelRefill) {
            $liters = (float) $fuelRefill->liters;
            $fuelRefill->delete();

            $vehicle->current_fuel = max(0, round($vehicle->fuelBalance() - $liters, 2));
            $vehicle->save();
        });

        return redirect()
            ->route('vehicles.show', $vehicle)
            ->with('success', 'Fuel refill removed.');
    }

    private function validateVehicle(Request $request, ?FleetVehicle $vehicle = null): array
    {
        $validated = $request->validate([
            'registration_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('fleet_vehicles', 'registration_number')->ignore($vehicle?->id),
            ],
            'name' => 'nullable|string|max:255',
            'type' => 'required|string|max:50',
            'color' => 'required|string|max:20',
            'status' => 'nullable|string|max:30',
            'fuel_type' => 'nullable|string|max:50',
            'fuel_efficiency' => 'nullable|numeric|min:0',
            'opening_fuel' => 'nullable|numeric|min:0',
            'fuel_capacity' => 'nullable|numeric|min:1',
            'vehicle_group' => 'required|string|max:100',
            'driver' => 'nullable|string|max:100',
            'model' => 'required|string|max:100',
            'manufacturer' => 'required|string|max:100',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'chassis_number' => 'nullable|string|max:100',
            'engine_number' => 'nullable|string|max:100',
            'registration_date' => 'nullable|date',
            'registration_expiry' => 'nullable|date',
            'insurance_provider' => 'nullable|string|max:150',
            'insurance_policy_no' => 'nullable|string|max:100',
            'insurance_expiry' => 'nullable|date',
            'fitness_certificate_no' => 'nullable|string|max:100',
            'fitness_expiry' => 'nullable|date',
            'owner_type' => 'nullable|string|max:50',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'lease_start' => 'nullable|date',
            'lease_end' => 'nullable|date|after_or_equal:lease_start',
            'gps_device_id' => 'nullable|string|max:100',
            'gps_imei' => 'nullable|string|max:100',
            'gps_sim_number' => 'nullable|string|max:50',
            'gps_enabled' => 'nullable|boolean',
        ]);

        $validated['status'] = $validated['status'] ?? ($vehicle->status ?? 'Active');
        $validated['owner_type'] = $validated['owner_type'] ?? ($vehicle->owner_type ?? 'Owned');
        $validated['fuel_capacity'] = $validated['fuel_capacity'] ?? ($vehicle->fuel_capacity ?? 50);
        $validated['gps_enabled'] = $request->boolean('gps_enabled');

        return $validated;
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }

    private function formOptions(): array
    {
        $groups = FleetVehicleGroup::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        if (empty($groups)) {
            $groups = ['North Fleet', 'South Fleet', 'City Delivery', 'Long Haul'];
        }

        $drivers = FleetDriver::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        if (empty($drivers)) {
            $drivers = ['Driver 480', 'Driver 875', 'Driver 815', 'Driver 497', 'Driver 619'];
        }

        return [
            'types' => $this->typeOptions(),
            'statuses' => ['Active', 'Inactive', 'Maintenance'],
            'fuel_types' => ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'],
            'groups' => $groups,
            'drivers' => $drivers,
            'models' => $this->modelOptions(),
            'manufacturers' => $this->manufacturerOptions(),
            'manufacturer_lookups' => FleetVehicleManufacturer::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'owner_types' => ['Owned', 'Vendor', 'Leased'],
        ];
    }

    private function typeOptions(): array
    {
        $fromLookup = FleetVehicleType::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $fromVehicles = FleetVehicle::query()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->all();

        return collect($fromLookup)
            ->merge($fromVehicles)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function manufacturerOptions(): array
    {
        $fromLookup = FleetVehicleManufacturer::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $fromVehicles = FleetVehicle::query()
            ->whereNotNull('manufacturer')
            ->where('manufacturer', '!=', '')
            ->distinct()
            ->orderBy('manufacturer')
            ->pluck('manufacturer')
            ->all();

        return collect($fromLookup)
            ->merge($fromVehicles)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function modelOptions(): array
    {
        $fromLookup = FleetVehicleModel::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $fromVehicles = FleetVehicle::query()
            ->whereNotNull('model')
            ->where('model', '!=', '')
            ->distinct()
            ->orderBy('model')
            ->pluck('model')
            ->all();

        return collect($fromLookup)
            ->merge($fromVehicles)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function paymentMethods(): array
    {
        return ['Cash', 'M-Pesa', 'Bank Transfer', 'Petty Cash', 'Fuel Card'];
    }

    private function formatVehicleRow(FleetVehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'name' => $vehicle->displayName(),
            'reg_no' => $vehicle->registration_number,
            'model' => $vehicle->model,
            'type' => $vehicle->type,
            'driver' => $vehicle->driver ?: 'Unassigned',
            'fuel_liters' => (float) ($vehicle->current_fuel ?? 0),
            'fuel_max' => (float) ($vehicle->fuel_capacity ?? 50),
            'low_fuel' => $vehicle->isLowFuel(),
            'group' => $vehicle->vehicle_group,
            'owner' => $vehicle->owner_type,
            'lease_start' => optional($vehicle->lease_start)->format('Y-m-d'),
            'lease_end' => optional($vehicle->lease_end)->format('Y-m-d'),
            'status' => $vehicle->status,
            'reg_expiry' => optional($vehicle->registration_expiry)->format('Y-m-d'),
            'ins_expiry' => optional($vehicle->insurance_expiry)->format('Y-m-d'),
        ];
    }
}
