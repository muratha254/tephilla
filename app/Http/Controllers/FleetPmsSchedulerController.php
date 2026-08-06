<?php

namespace App\Http\Controllers;

use App\Models\FleetPmsRule;
use App\Models\FleetVehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FleetPmsSchedulerController extends Controller
{
    public function index()
    {
        $rules = FleetPmsRule::query()
            ->with('vehicle')
            ->latest('id')
            ->get();

        return view('fleet.maintenance.pms', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-pms',
            'openMenu' => 'maintenance',
            'rules' => $rules,
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fleet_vehicle_id' => 'required|exists:fleet_vehicles,id',
            'service_name' => 'required|string|max:150',
            'interval_km' => 'nullable|integer|min:1',
            'interval_days' => 'nullable|integer|min:1',
            'last_service_date' => 'nullable|date',
        ]);

        if (empty($validated['interval_km']) && empty($validated['interval_days'])) {
            throw ValidationException::withMessages([
                'interval_km' => 'Provide at least one interval (KM or Days).',
            ]);
        }

        FleetPmsRule::create([
            'fleet_vehicle_id' => $validated['fleet_vehicle_id'],
            'service_name' => $validated['service_name'],
            'interval_km' => $validated['interval_km'] ?? null,
            'interval_days' => $validated['interval_days'] ?? null,
            'last_service_date' => $validated['last_service_date'] ?? null,
        ]);

        return redirect()
            ->route('maintenance.pms.index')
            ->with('success', 'PMS rule added successfully.');
    }

    public function destroy(FleetPmsRule $pmsRule)
    {
        $pmsRule->delete();

        return redirect()
            ->route('maintenance.pms.index')
            ->with('success', 'PMS rule deleted.');
    }

    public function check()
    {
        $rules = FleetPmsRule::query()->with('vehicle')->get();
        $dueRules = $rules->filter(fn (FleetPmsRule $rule) => $rule->isDue())->values();

        if ($dueRules->isEmpty()) {
            return redirect()
                ->route('maintenance.pms.index')
                ->with('success', 'All PMS rules are up to date based on current vehicle status.');
        }

        return redirect()
            ->route('maintenance.pms.index')
            ->with('pms_due_rules', $dueRules->map(fn (FleetPmsRule $rule) => [
                'vehicle' => optional($rule->vehicle)->displayName(),
                'registration' => optional($rule->vehicle)->registration_number,
                'service' => $rule->service_name,
                'interval' => $rule->formattedInterval(),
                'last_service' => $rule->formattedLastService(),
            ])->all());
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
