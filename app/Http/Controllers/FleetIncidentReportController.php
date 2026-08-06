<?php

namespace App\Http\Controllers;

use App\Models\FleetIncidentReport;
use App\Models\FleetMaintenance;
use App\Models\FleetVehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FleetIncidentReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = FleetIncidentReport::query()->with(['vehicle', 'maintenance']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('description', 'like', '%' . $search . '%')
                    ->orWhere('reported_by', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('registration_number', 'like', '%' . $search . '%');
                    });
            });
        }

        $incidents = $query
            ->latest('incident_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('fleet.incidents.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-incidents',
            'openMenu' => 'maintenance',
            'incidents' => $incidents,
            'search' => $search,
        ]));
    }

    public function create()
    {
        return view('fleet.incidents.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-incidents',
            'openMenu' => 'maintenance',
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fleet_vehicle_id' => 'required|exists:fleet_vehicles,id',
            'description' => 'required|string|max:5000',
            'incident_date' => 'required|date',
        ]);

        FleetIncidentReport::create([
            'fleet_vehicle_id' => $validated['fleet_vehicle_id'],
            'reported_by' => $this->reporterName(),
            'description' => trim($validated['description']),
            'incident_date' => $validated['incident_date'],
            'status' => 'Open',
        ]);

        return redirect()
            ->route('incidents.index')
            ->with('success', 'Incident report submitted successfully.');
    }

    public function convertToMaintenance(FleetIncidentReport $incident)
    {
        if (! $incident->canConvert()) {
            return redirect()
                ->route('incidents.index')
                ->withErrors(['incident' => 'This incident has already been converted or closed.']);
        }

        $maintenance = FleetMaintenance::create([
            'fleet_vehicle_id' => $incident->fleet_vehicle_id,
            'status' => 'Planned',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'service_details' => "Converted from incident report #{$incident->id}:\n\n" . $incident->description,
            'total_cost' => 0,
            'priority' => 'Medium',
            'checklist' => [],
        ]);

        $incident->update([
            'status' => 'Converted',
            'fleet_maintenance_id' => $maintenance->id,
        ]);

        return redirect()
            ->route('maintenance.show', $maintenance)
            ->with('success', 'Incident converted to maintenance record.');
    }

    public function destroy(FleetIncidentReport $incident)
    {
        $incident->delete();

        return redirect()
            ->route('incidents.index')
            ->with('success', 'Incident report deleted successfully.');
    }

    private function reporterName(): string
    {
        $user = Auth::user();

        if (! $user) {
            return 'admin';
        }

        return $user->name ?? $user->email ?? 'admin';
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
