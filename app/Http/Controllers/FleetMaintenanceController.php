<?php

namespace App\Http\Controllers;

use App\Models\FleetMechanic;
use App\Models\FleetMaintenance;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FleetMaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $view = $request->query('view', 'grid') === 'list' ? 'list' : 'grid';

        $query = FleetMaintenance::query()->with(['vehicle', 'vendor']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('service_details', 'like', '%' . $search . '%')
                    ->orWhere('mechanic', 'like', '%' . $search . '%')
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('registration_number', 'like', '%' . $search . '%')
                            ->orWhere('model', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $maintenances = $query
            ->latest('start_date')
            ->latest('id')
            ->get();

        return view('fleet.maintenance.index', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-list',
            'openMenu' => 'maintenance',
            'maintenances' => $maintenances,
            'counts' => [
                'all' => FleetMaintenance::query()->count(),
                'planned' => FleetMaintenance::query()->where('status', 'Planned')->count(),
                'ongoing' => FleetMaintenance::query()->where('status', 'Ongoing')->count(),
                'completed' => FleetMaintenance::query()->where('status', 'Completed')->count(),
            ],
            'search' => $search,
            'statusFilter' => $status,
            'viewMode' => $view,
            'statuses' => ['Planned', 'Ongoing', 'Completed', 'Cancelled'],
        ]));
    }

    public function create()
    {
        return view('fleet.maintenance.create', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-add',
            'openMenu' => 'maintenance',
            'formOptions' => $this->formOptions(),
            'checklistItems' => $this->checklistItemsForForm(),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $this->validateMaintenance($request);

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store('maintenance/receipts', 'public');
        }

        FleetMaintenance::create($validated);

        return redirect()
            ->route('maintenance.index')
            ->with('success', 'Maintenance record saved successfully.');
    }

    public function show(FleetMaintenance $maintenance)
    {
        $maintenance->load(['vehicle', 'vendor']);

        return view('fleet.maintenance.show', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-list',
            'openMenu' => 'maintenance',
            'maintenance' => $maintenance,
        ]));
    }

    public function edit(FleetMaintenance $maintenance)
    {
        return view('fleet.maintenance.edit', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-list',
            'openMenu' => 'maintenance',
            'maintenance' => $maintenance,
            'formOptions' => $this->formOptions(),
            'checklistItems' => $this->checklistItemsForForm($maintenance),
        ]));
    }

    public function update(Request $request, FleetMaintenance $maintenance)
    {
        $validated = $this->validateMaintenance($request);

        if ($request->hasFile('receipt')) {
            if ($maintenance->receipt_path) {
                Storage::disk('public')->delete($maintenance->receipt_path);
            }

            $validated['receipt_path'] = $request->file('receipt')->store('maintenance/receipts', 'public');
        }

        $maintenance->update($validated);

        return redirect()
            ->route('maintenance.show', $maintenance)
            ->with('success', 'Maintenance record updated successfully.');
    }

    public function destroy(FleetMaintenance $maintenance)
    {
        if ($maintenance->receipt_path) {
            Storage::disk('public')->delete($maintenance->receipt_path);
        }

        $maintenance->delete();

        return redirect()
            ->route('maintenance.index')
            ->with('success', 'Maintenance record deleted successfully.');
    }

    public function updateStatus(Request $request, FleetMaintenance $maintenance)
    {
        $validated = $request->validate([
            'status' => 'required|in:Planned,Ongoing,Completed,Cancelled',
        ]);

        $maintenance->update(['status' => $validated['status']]);

        return redirect()
            ->back()
            ->with('success', 'Maintenance status updated.');
    }

    public function print(FleetMaintenance $maintenance)
    {
        $maintenance->load(['vehicle', 'vendor']);

        return view('fleet.maintenance.print', [
            'maintenance' => $maintenance,
            'companyName' => 'One Translines Pvt Ltd',
        ]);
    }

    private function validateMaintenance(Request $request): array
    {
        $validated = $request->validate([
            'fleet_vehicle_id' => 'required|exists:fleet_vehicles,id',
            'status' => 'required|string|max:30',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'service_details' => 'required|string|max:5000',
            'total_cost' => 'nullable|numeric|min:0',
            'fleet_vehicle_vendor_id' => 'nullable|exists:fleet_vehicle_vendors,id',
            'mechanic' => 'nullable|string|max:150',
            'priority' => 'required|string|max:20',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'checklist' => 'nullable|array',
            'checklist.*.task' => 'required|string|max:255',
            'checklist.*.done' => 'nullable|boolean',
        ]);

        $validated['checklist'] = collect($validated['checklist'] ?? [])
            ->map(fn ($item) => [
                'task' => $item['task'],
                'done' => ! empty($item['done']),
            ])
            ->values()
            ->all();

        $validated['total_cost'] = $validated['total_cost'] ?? 0;
        $validated['fleet_vehicle_vendor_id'] = $validated['fleet_vehicle_vendor_id'] ?? null;
        $validated['mechanic'] = $validated['mechanic'] ?? null;

        return $validated;
    }

    private function checklistItemsForForm(?FleetMaintenance $maintenance = null): array
    {
        if (old('checklist')) {
            return collect(old('checklist'))
                ->map(fn ($item) => [
                    'task' => $item['task'],
                    'done' => ! empty($item['done']),
                ])
                ->values()
                ->all();
        }

        if ($maintenance && ! empty($maintenance->checklist)) {
            return $maintenance->checklist;
        }

        return collect($this->defaultChecklistTasks())
            ->map(fn ($task) => ['task' => $task, 'done' => false])
            ->all();
    }

    public function costAnalytics(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $this->queryFilter($request, 'date_to', now()->format('Y-m-d'));
        $search = trim($this->queryFilter($request, 'search'));
        $report = $this->costAnalyticsReport($dateFrom, $dateTo, $search);

        return view('fleet.maintenance.cost_analytics', array_merge($this->sharedViewData(), [
            'activeMenu' => 'maintenance-cost',
            'openMenu' => 'maintenance',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'summary' => $report['summary'],
            'chart' => $report['chart'],
            'rows' => $report['rows'],
        ]));
    }

    private function queryFilter(Request $request, string $key, string $default = ''): string
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return trim((string) $value);
    }

    private function costAnalyticsReport(string $dateFrom, string $dateTo, string $search = ''): array
    {
        $records = FleetMaintenance::query()
            ->with(['vehicle', 'vendor'])
            ->whereDate('start_date', '>=', $dateFrom)
            ->whereDate('start_date', '<=', $dateTo)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('mechanic', 'like', '%' . $search . '%')
                        ->orWhere('service_details', 'like', '%' . $search . '%')
                        ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                            $vehicleQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('registration_number', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('vendor', function ($vendorQuery) use ($search) {
                            $vendorQuery->where('company', 'like', '%' . $search . '%')
                                ->orWhere('contact_person', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $costByVehicle = [];
        foreach ($records as $record) {
            $vehicleName = optional($record->vehicle)->displayName() ?: 'Unassigned';
            $costByVehicle[$vehicleName] = ($costByVehicle[$vehicleName] ?? 0) + (float) $record->total_cost;
        }

        arsort($costByVehicle);

        $rows = $records->values()->map(function (FleetMaintenance $record, int $index) {
            $vendorName = optional($record->vendor)->company
                ?: optional($record->vendor)->contact_person;

            return [
                'serial' => $index + 1,
                'start_date' => format_fleet_date($record->start_date),
                'end_date' => format_fleet_date($record->end_date),
                'vehicle_name' => optional($record->vehicle)->displayName() ?: '-',
                'odometer' => '-',
                'cost' => round((float) $record->total_cost, 2),
                'mechanic' => trim((string) $record->mechanic) ?: '-',
                'vendor_label' => $vendorName ? 'Vendor: ' . $vendorName : '-',
            ];
        });

        return [
            'summary' => [
                'total_records' => $records->count(),
                'total_cost' => round($records->sum('total_cost'), 2),
                'average_cost' => $records->count() > 0
                    ? round($records->sum('total_cost') / $records->count(), 2)
                    : 0,
                'vehicles_serviced' => count($costByVehicle),
            ],
            'chart' => [
                'labels' => array_keys($costByVehicle),
                'values' => array_values($costByVehicle),
            ],
            'rows' => $rows,
        ];
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
        return [
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
            'vendors' => FleetVehicleVendor::query()->where('is_active', true)->orderBy('company')->get(),
            'mechanics' => FleetMechanic::query()->orderBy('name')->pluck('name')->all(),
            'statuses' => ['Planned', 'Ongoing', 'Completed', 'Cancelled'],
            'priorities' => ['Low', 'Medium', 'High', 'Urgent'],
        ];
    }

    private function defaultChecklistTasks(): array
    {
        return [
            'Engine oil & filter check',
            'Brake system inspection',
            'Tyre tread & pressure check',
            'Battery terminals & charge',
            'Coolant / fluid levels',
            'Lights & indicator check',
            'Windscreen wipers',
            'Suspension & steering check',
            'Air filter inspection',
            'General body & undercarriage check',
        ];
    }
}
