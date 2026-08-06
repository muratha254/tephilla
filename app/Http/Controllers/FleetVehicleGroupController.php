<?php

namespace App\Http\Controllers;

use App\Models\FleetVehicleGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FleetVehicleGroupController extends Controller
{
    public function index()
    {
        $groups = FleetVehicleGroup::query()
            ->orderBy('name')
            ->get()
            ->map(function (FleetVehicleGroup $group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'status' => $group->status,
                    'vehicle_count' => $group->vehicleCount(),
                ];
            });

        return view('fleet.vehicle-groups.index', [
            'activeMenu' => 'vehicle-group',
            'openMenu' => 'vehicle',
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
            'groups' => $groups,
        ]);
    }

    public function create()
    {
        return view('fleet.vehicle-groups.create', $this->sharedViewData());
    }

    public function store(Request $request)
    {
        $validated = $this->validateGroup($request);

        FleetVehicleGroup::create($validated);

        return redirect()
            ->route('vehicle-groups.index')
            ->with('success', 'Vehicle group created successfully.');
    }

    public function edit(FleetVehicleGroup $vehicleGroup)
    {
        return view('fleet.vehicle-groups.edit', array_merge($this->sharedViewData(), [
            'group' => $vehicleGroup,
        ]));
    }

    public function update(Request $request, FleetVehicleGroup $vehicleGroup)
    {
        $validated = $this->validateGroup($request, $vehicleGroup);
        $oldName = $vehicleGroup->name;

        $vehicleGroup->update($validated);

        if ($oldName !== $vehicleGroup->name) {
            \App\Models\FleetVehicle::query()
                ->where('vehicle_group', $oldName)
                ->update(['vehicle_group' => $vehicleGroup->name]);
        }

        return redirect()
            ->route('vehicle-groups.index')
            ->with('success', 'Vehicle group updated successfully.');
    }

    public function destroy(FleetVehicleGroup $vehicleGroup)
    {
        if ($vehicleGroup->vehicleCount() > 0) {
            return redirect()
                ->route('vehicle-groups.index')
                ->with('success', 'Cannot delete a group that still has vehicles assigned.');
        }

        $vehicleGroup->delete();

        return redirect()
            ->route('vehicle-groups.index')
            ->with('success', 'Vehicle group deleted successfully.');
    }

    private function validateGroup(Request $request, ?FleetVehicleGroup $group = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fleet_vehicle_groups', 'name')->ignore($group?->id),
            ],
            'description' => 'nullable|string|max:500',
            'status' => 'required|string|max:30',
        ]);
    }

    private function sharedViewData(): array
    {
        return [
            'activeMenu' => 'vehicle-group',
            'openMenu' => 'vehicle',
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
