<?php

namespace App\Http\Controllers;

use App\Models\FleetReminder;
use App\Models\FleetVehicle;
use Illuminate\Http\Request;

class FleetReminderController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $viewMode = $request->query('view', 'grid') === 'list' ? 'list' : 'grid';

        $query = $this->filteredQuery($search, $status)->with('vehicle');

        $reminders = $query
            ->orderBy('is_completed')
            ->orderBy('due_date')
            ->get();

        $counts = [
            'total' => FleetReminder::query()->count(),
            'completed' => FleetReminder::query()->where('is_completed', true)->count(),
            'pending' => FleetReminder::query()->where('is_completed', false)->count(),
        ];

        return view('fleet.reminders.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'reminder-list',
            'openMenu' => 'reminder',
            'reminders' => $reminders,
            'search' => $search,
            'statusFilter' => $status,
            'viewMode' => $viewMode,
            'counts' => $counts,
        ]));
    }

    public function create()
    {
        return view('fleet.reminders.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'reminder-add',
            'openMenu' => 'reminder',
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
        ]));
    }

    public function store(Request $request)
    {
        FleetReminder::create($this->validatedReminder($request));

        return redirect()
            ->route('reminders.index')
            ->with('success', 'Reminder added successfully.');
    }

    public function edit(FleetReminder $reminder)
    {
        return view('fleet.reminders.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'reminder-list',
            'openMenu' => 'reminder',
            'reminder' => $reminder,
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
        ]));
    }

    public function update(Request $request, FleetReminder $reminder)
    {
        $reminder->update($this->validatedReminder($request));

        return redirect()
            ->route('reminders.index')
            ->with('success', 'Reminder updated successfully.');
    }

    public function updateStatus(Request $request, FleetReminder $reminder)
    {
        $validated = $request->validate([
            'is_completed' => 'required|in:0,1',
        ]);

        $reminder->update(['is_completed' => (bool) $validated['is_completed']]);

        return redirect()
            ->route('reminders.index', $request->only(['search', 'status', 'view']))
            ->with('success', 'Reminder status updated.');
    }

    public function destroy(FleetReminder $reminder)
    {
        $reminder->delete();

        return redirect()
            ->route('reminders.index')
            ->with('success', 'Reminder deleted.');
    }

    private function validatedReminder(Request $request): array
    {
        $validated = $request->validate([
            'fleet_vehicle_id' => 'required|exists:fleet_vehicles,id',
            'due_date' => 'required|date',
            'services' => 'nullable|string|max:500',
            'notes' => 'required|string|max:1000',
            'is_completed' => 'nullable|boolean',
        ]);

        return [
            'fleet_vehicle_id' => $validated['fleet_vehicle_id'],
            'due_date' => $validated['due_date'],
            'services' => $validated['services'] ?? null,
            'notes' => $validated['notes'],
            'is_completed' => (bool) ($validated['is_completed'] ?? false),
        ];
    }

    private function filteredQuery(string $search, string $status)
    {
        $query = FleetReminder::query();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('services', 'like', '%' . $search . '%')
                    ->orWhere('notes', 'like', '%' . $search . '%')
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('registration_number', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($status === 'completed') {
            $query->where('is_completed', true);
        } elseif ($status === 'pending') {
            $query->where('is_completed', false);
        }

        return $query;
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
