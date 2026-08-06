<?php

namespace App\Http\Controllers;

use App\Models\FleetVehicle;
use App\Models\FleetVehicleTyre;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FleetTyreController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = FleetVehicleTyre::query()->with('vehicle');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('position', 'like', '%' . $search . '%')
                    ->orWhere('serial_number', 'like', '%' . $search . '%')
                    ->orWhere('brand_model', 'like', '%' . $search . '%')
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('registration_number', 'like', '%' . $search . '%');
                    });
            });
        }

        $tyres = $query
            ->latest('install_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('fleet.tyres.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-tyre',
            'openMenu' => 'maintenance',
            'tyres' => $tyres,
            'search' => $search,
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
            'positions' => $this->positions(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fleet_vehicle_id' => 'required|exists:fleet_vehicles,id',
            'position' => ['required', 'string', 'max:50', Rule::in($this->positions())],
            'serial_number' => 'required|string|max:100',
            'brand_model' => 'nullable|string|max:150',
            'install_date' => 'required|date',
            'install_odometer' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
        ]);

        $exists = FleetVehicleTyre::query()
            ->where('fleet_vehicle_id', $validated['fleet_vehicle_id'])
            ->where('position', $validated['position'])
            ->exists();

        if ($exists) {
            return redirect()
                ->route('tyres.index')
                ->withInput()
                ->withErrors(['position' => 'This vehicle already has a tyre assigned to that position.']);
        }

        FleetVehicleTyre::create($validated);

        return redirect()
            ->route('tyres.index')
            ->with('success', 'Tyre added successfully.');
    }

    public function destroy(FleetVehicleTyre $tyre)
    {
        $tyre->delete();

        return redirect()
            ->route('tyres.index')
            ->with('success', 'Tyre record deleted.');
    }

    private function positions(): array
    {
        return [
            'Front-Left',
            'Front-Right',
            'Rear-Left',
            'Rear-Right',
            'Rear-Inner-Left',
            'Rear-Inner-Right',
            'Spare',
        ];
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
