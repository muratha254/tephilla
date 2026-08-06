<?php

namespace App\Http\Controllers;

use App\Models\FleetCustomer;
use App\Models\FleetDriver;
use App\Models\FleetMaintenance;
use App\Models\FleetReminder;
use App\Models\FleetTrip;
use App\Models\FleetTripExpense;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleFuelRefill;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PDF;

class FleetReportController extends Controller
{
    public function booking(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-31');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $vehicleId = $this->queryFilter($request, 'vehicle_id');
        $statusFilter = $this->queryFilter($request, 'status');
        $customerId = $this->queryFilter($request, 'customer_id');

        $report = $this->bookingReport($dateFrom, $dateTo, $vehicleId, $statusFilter, $customerId);

        return view('fleet.reports.booking', array_merge($this->sharedViewData(), [
            'activeMenu' => 'reports-booking',
            'openMenu' => 'reports',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'vehicleId' => $vehicleId,
            'statusFilter' => $statusFilter,
            'customerId' => $customerId,
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
            'customers' => FleetCustomer::query()->orderBy('name')->get(),
            'summary' => $report['summary'],
            'statusCounts' => $report['statusCounts'],
            'revenueTrend' => $report['revenueTrend'],
            'tripRows' => $report['tripRows'],
        ]));
    }

    public function exportBookingPdf(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-31');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $vehicleId = $this->queryFilter($request, 'vehicle_id');
        $statusFilter = $this->queryFilter($request, 'status');
        $customerId = $this->queryFilter($request, 'customer_id');

        $report = $this->bookingReport($dateFrom, $dateTo, $vehicleId, $statusFilter, $customerId);

        $pdf = PDF::loadView('fleet.reports.booking-pdf', array_merge(fleet_company_profile(), [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'vehicleLabel' => $this->vehicleFilterLabel($vehicleId),
            'customerLabel' => $this->customerFilterLabel($customerId),
            'statusLabel' => $this->statusFilterLabel($statusFilter),
            'summary' => $report['summary'],
            'statusCounts' => $report['statusCounts'],
            'tripRows' => $report['tripRows'],
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]))->setPaper('a4', 'landscape');

        $filename = 'trips-report-' . $dateFrom . '-to-' . $dateTo . '.pdf';

        return $pdf->download($filename);
    }

    public function driver(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-31');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $driverId = $this->queryFilter($request, 'driver_id');

        $report = $this->driverActivityReport($dateFrom, $dateTo, $driverId);

        return view('fleet.reports.driver', array_merge($this->sharedViewData(), [
            'activeMenu' => 'reports-driver',
            'openMenu' => 'reports',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'driverId' => $driverId,
            'drivers' => FleetDriver::query()->orderBy('name')->get(),
            'summary' => $report['summary'],
            'chart' => $report['chart'],
        ]));
    }

    public function exportDriverPdf(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-31');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $driverId = $this->queryFilter($request, 'driver_id');

        $report = $this->driverActivityReport($dateFrom, $dateTo, $driverId);

        $pdf = PDF::loadView('fleet.reports.driver-pdf', array_merge(fleet_company_profile(), [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'driverLabel' => $this->driverFilterLabel($driverId),
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]))->setPaper('a4', 'portrait');

        $filename = 'driver-report-' . $dateFrom . '-to-' . $dateTo . '.pdf';

        return $pdf->download($filename);
    }

    public function income(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-31');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $vehicleId = $this->queryFilter($request, 'vehicle_id');

        $report = $this->incomeExpenseReport($dateFrom, $dateTo, $vehicleId);

        return view('fleet.reports.income', array_merge($this->sharedViewData(), [
            'activeMenu' => 'reports-income',
            'openMenu' => 'reports',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'vehicleId' => $vehicleId,
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
            'summary' => $report['summary'],
            'chart' => $report['chart'],
        ]));
    }

    public function exportIncomePdf(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-31');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $vehicleId = $this->queryFilter($request, 'vehicle_id');

        $report = $this->incomeExpenseReport($dateFrom, $dateTo, $vehicleId);

        $pdf = PDF::loadView('fleet.reports.income-pdf', array_merge(fleet_company_profile(), [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'vehicleId' => $vehicleId,
            'vehicleLabel' => $this->vehicleFilterLabel($vehicleId),
            'summary' => $report['summary'],
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]))->setPaper('a4', 'portrait');

        $filename = 'income-expense-report-' . $dateFrom . '-to-' . $dateTo . '.pdf';

        return $pdf->download($filename);
    }

    public function fuel(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-31');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $vehicleId = $this->queryFilter($request, 'vehicle_id');

        $report = $this->fuelReport($dateFrom, $dateTo, $vehicleId);

        return view('fleet.reports.fuel', array_merge($this->sharedViewData(), [
            'activeMenu' => 'reports-fuel',
            'openMenu' => 'reports',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'vehicleId' => $vehicleId,
            'vehicles' => FleetVehicle::query()->orderBy('name')->orderBy('registration_number')->get(),
            'summary' => $report['summary'],
            'chart' => $report['chart'],
        ]));
    }

    public function exportFuelPdf(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-31');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $vehicleId = $this->queryFilter($request, 'vehicle_id');

        $report = $this->fuelReport($dateFrom, $dateTo, $vehicleId);

        $pdf = PDF::loadView('fleet.reports.fuel-pdf', array_merge(fleet_company_profile(), [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'vehicleLabel' => $this->vehicleFilterLabel($vehicleId),
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]))->setPaper('a4', 'portrait');

        $filename = 'fuel-report-' . $dateFrom . '-to-' . $dateTo . '.pdf';

        return $pdf->download($filename);
    }

    public function reminders(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-30');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $search = trim($this->queryFilter($request, 'search'));

        $report = $this->remindersReport($dateFrom, $dateTo, $search);

        return view('fleet.reports.reminders', array_merge($this->sharedViewData(), [
            'activeMenu' => 'reports-reminders',
            'openMenu' => 'reports',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'summary' => $report['summary'],
            'chart' => $report['chart'],
            'rows' => $report['rows'],
        ]));
    }

    public function exportRemindersPdf(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-30');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $search = trim($this->queryFilter($request, 'search'));

        $report = $this->remindersReport($dateFrom, $dateTo, $search);

        $pdf = PDF::loadView('fleet.reports.reminders-pdf', array_merge(fleet_company_profile(), [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]))->setPaper('a4', 'landscape');

        $filename = 'reminders-report-' . $dateFrom . '-to-' . $dateTo . '.pdf';

        return $pdf->download($filename);
    }

    public function maintenance(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-30');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $search = trim($this->queryFilter($request, 'search'));

        $report = $this->maintenanceReport($dateFrom, $dateTo, $search);

        return view('fleet.reports.maintenance', array_merge($this->sharedViewData(), [
            'activeMenu' => 'reports-maintenance',
            'openMenu' => 'reports',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'summary' => $report['summary'],
            'chart' => $report['chart'],
            'rows' => $report['rows'],
        ]));
    }

    public function exportMaintenancePdf(Request $request)
    {
        $dateFrom = $this->queryFilter($request, 'date_from', '2025-12-30');
        $dateTo = $this->queryFilter($request, 'date_to', '2026-01-11');
        $search = trim($this->queryFilter($request, 'search'));

        $report = $this->maintenanceReport($dateFrom, $dateTo, $search);

        $pdf = PDF::loadView('fleet.reports.maintenance-pdf', array_merge(fleet_company_profile(), [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]))->setPaper('a4', 'landscape');

        $filename = 'maintenance-report-' . $dateFrom . '-to-' . $dateTo . '.pdf';

        return $pdf->download($filename);
    }

    private function fuelReport(string $dateFrom, string $dateTo, $vehicleId): array
    {
        $refills = FleetVehicleFuelRefill::query()
            ->with('vehicle')
            ->whereDate('refill_date', '>=', $dateFrom)
            ->whereDate('refill_date', '<=', $dateTo)
            ->when($vehicleId !== '' && $vehicleId !== null, fn ($query) => $query->where('fleet_vehicle_id', (int) $vehicleId))
            ->orderBy('refill_date')
            ->get();

        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->startOfDay();

        $litersByDay = [];
        $costByDay = [];

        foreach ($refills as $refill) {
            if (! $refill->refill_date) {
                continue;
            }

            $key = $refill->refill_date->format('Y-m-d');
            $litersByDay[$key] = ($litersByDay[$key] ?? 0) + (float) $refill->liters;
            $costByDay[$key] = ($costByDay[$key] ?? 0) + (float) $refill->cost;
        }

        $labels = [];
        $litersValues = [];
        $costValues = [];
        $rows = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $liters = round($litersByDay[$key] ?? 0, 2);
            $cost = round($costByDay[$key] ?? 0, 2);

            $labels[] = $key;
            $litersValues[] = $liters;
            $costValues[] = $cost;

            if ($liters > 0 || $cost > 0) {
                $rows[] = [
                    'date' => $key,
                    'liters' => $liters,
                    'cost' => $cost,
                ];
            }
        }

        return [
            'summary' => [
                'total_liters' => round($refills->sum('liters'), 2),
                'total_cost' => round($refills->sum('cost'), 2),
            ],
            'chart' => [
                'labels' => $labels,
                'liters' => $litersValues,
                'cost' => $costValues,
            ],
            'rows' => $rows,
        ];
    }

    private function incomeExpenseReport(string $dateFrom, string $dateTo, $vehicleId): array
    {
        $trips = FleetTrip::query()
            ->whereDate('start_date', '>=', $dateFrom)
            ->whereDate('start_date', '<=', $dateTo)
            ->when($vehicleId !== '' && $vehicleId !== null, fn ($query) => $query->where('fleet_vehicle_id', (int) $vehicleId))
            ->get();

        $tripIds = $trips->pluck('id');

        $tripExpenses = FleetTripExpense::query()
            ->whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->when($tripIds->isNotEmpty(), fn ($query) => $query->whereIn('fleet_trip_id', $tripIds))
            ->when($tripIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->get();

        $fuelCosts = FleetVehicleFuelRefill::query()
            ->whereDate('refill_date', '>=', $dateFrom)
            ->whereDate('refill_date', '<=', $dateTo)
            ->when($vehicleId !== '' && $vehicleId !== null, fn ($query) => $query->where('fleet_vehicle_id', (int) $vehicleId))
            ->get();

        $maintenanceCosts = FleetMaintenance::query()
            ->whereDate('start_date', '>=', $dateFrom)
            ->whereDate('start_date', '<=', $dateTo)
            ->when($vehicleId !== '' && $vehicleId !== null, fn ($query) => $query->where('fleet_vehicle_id', (int) $vehicleId))
            ->get();

        $totalIncome = round($trips->sum(fn (FleetTrip $trip) => $trip->totalAmount()), 2);
        $totalCosts = round(
            $tripExpenses->sum('amount')
            + $fuelCosts->sum('cost')
            + $maintenanceCosts->sum('total_cost'),
            2
        );

        $trend = $this->financialTrend($dateFrom, $dateTo, $trips, $tripExpenses, $fuelCosts, $maintenanceCosts);

        return [
            'summary' => [
                'total_income' => $totalIncome,
                'total_costs' => $totalCosts,
                'net_profit' => round($totalIncome - $totalCosts, 2),
            ],
            'chart' => [
                'income' => $totalIncome,
                'expense' => $totalCosts,
                'trend' => $trend,
            ],
        ];
    }

    private function financialTrend(
        string $dateFrom,
        string $dateTo,
        $trips,
        $tripExpenses,
        $fuelCosts,
        $maintenanceCosts
    ): array {
        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->startOfDay();

        $incomeByDay = [];
        foreach ($trips as $trip) {
            if (! $trip->start_date) {
                continue;
            }
            $key = $trip->start_date->format('Y-m-d');
            $incomeByDay[$key] = ($incomeByDay[$key] ?? 0) + $trip->totalAmount();
        }

        $expenseByDay = [];
        foreach ($tripExpenses as $expense) {
            if (! $expense->expense_date) {
                continue;
            }
            $key = $expense->expense_date->format('Y-m-d');
            $expenseByDay[$key] = ($expenseByDay[$key] ?? 0) + (float) $expense->amount;
        }
        foreach ($fuelCosts as $refill) {
            if (! $refill->refill_date) {
                continue;
            }
            $key = $refill->refill_date->format('Y-m-d');
            $expenseByDay[$key] = ($expenseByDay[$key] ?? 0) + (float) $refill->cost;
        }
        foreach ($maintenanceCosts as $maintenance) {
            if (! $maintenance->start_date) {
                continue;
            }
            $key = $maintenance->start_date->format('Y-m-d');
            $expenseByDay[$key] = ($expenseByDay[$key] ?? 0) + (float) $maintenance->total_cost;
        }

        $labels = [];
        $incomeValues = [];
        $expenseValues = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $labels[] = $key;
            $incomeValues[] = round($incomeByDay[$key] ?? 0, 2);
            $expenseValues[] = round($expenseByDay[$key] ?? 0, 2);
        }

        return [
            'labels' => $labels,
            'income' => $incomeValues,
            'expense' => $expenseValues,
        ];
    }

    private function vehicleFilterLabel($vehicleId): string
    {
        if ($vehicleId === '' || $vehicleId === null) {
            return 'All Vehicles';
        }

        $vehicle = FleetVehicle::query()->find((int) $vehicleId);

        return $vehicle ? $vehicle->displayName() : 'All Vehicles';
    }

    private function customerFilterLabel($customerId): string
    {
        if ($customerId === '' || $customerId === null) {
            return 'All Customers';
        }

        $customer = FleetCustomer::query()->find((int) $customerId);

        return $customer ? $customer->name : 'All Customers';
    }

    private function statusFilterLabel(string $statusFilter): string
    {
        return match ($statusFilter) {
            'completed' => 'Completed',
            'ongoing' => 'Ongoing',
            'yet_to_start' => 'Yet to Start',
            'cancelled' => 'Cancelled',
            default => 'All Status',
        };
    }

    private function bookingReport(string $dateFrom, string $dateTo, $vehicleId, string $statusFilter, $customerId): array
    {
        $trips = $this->filteredTrips($dateFrom, $dateTo, $vehicleId, $statusFilter, $customerId);
        $tripRows = $this->tripProfitRows($trips);

        $statusCounts = [
            'Completed' => 0,
            'Ongoing' => 0,
            'Yet to Start' => 0,
            'Cancelled' => 0,
        ];

        foreach ($trips as $trip) {
            $statusCounts[$this->reportStatusLabel($trip->status)]++;
        }

        return [
            'summary' => [
                'total_bookings' => $trips->count(),
                'total_revenue' => round($trips->sum(fn (FleetTrip $trip) => $trip->totalAmount()), 2),
                'total_expenses' => round($trips->sum(fn (FleetTrip $trip) => $trip->totalExpensesAmount()), 2),
                'total_profit' => round($trips->sum(fn (FleetTrip $trip) => $trip->profitAmount()), 2),
                'total_distance' => round($trips->sum(fn (FleetTrip $trip) => (float) ($trip->distance_km ?? 0)), 2),
            ],
            'statusCounts' => $statusCounts,
            'revenueTrend' => $this->revenueTrend($trips, $dateFrom, $dateTo),
            'tripRows' => $tripRows,
        ];
    }

    private function driverFilterLabel($driverId): string
    {
        if ($driverId === '' || $driverId === null) {
            return 'All Drivers';
        }

        $driver = FleetDriver::query()->find((int) $driverId);

        return $driver ? $driver->name : 'All Drivers';
    }

    private function driverActivityReport(string $dateFrom, string $dateTo, $driverId): array
    {
        $trips = FleetTrip::query()
            ->with('track')
            ->whereDate('start_date', '>=', $dateFrom)
            ->whereDate('start_date', '<=', $dateTo)
            ->whereNotNull('fleet_driver_id')
            ->when($driverId !== '' && $driverId !== null, fn ($query) => $query->where('fleet_driver_id', (int) $driverId))
            ->get();

        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->startOfDay();

        $tripsByDay = [];
        $distanceByDay = [];
        $durationByDay = [];

        foreach ($trips as $trip) {
            if (! $trip->start_date) {
                continue;
            }

            $key = $trip->start_date->format('Y-m-d');
            $tripsByDay[$key] = ($tripsByDay[$key] ?? 0) + 1;
            $distanceByDay[$key] = ($distanceByDay[$key] ?? 0) + $this->tripDistanceKm($trip);
            $durationByDay[$key] = ($durationByDay[$key] ?? 0) + $this->tripDurationMinutes($trip);
        }

        $labels = [];
        $tripCounts = [];
        $distanceValues = [];
        $rows = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $count = (int) ($tripsByDay[$key] ?? 0);
            $distance = round($distanceByDay[$key] ?? 0, 2);
            $durationHours = round(($durationByDay[$key] ?? 0) / 60, 2);

            $labels[] = $key;
            $tripCounts[] = $count;
            $distanceValues[] = $distance;

            if ($count > 0) {
                $rows[] = [
                    'date' => $key,
                    'trips' => $count,
                    'distance_km' => $distance,
                    'duration_hours' => $durationHours,
                ];
            }
        }

        $totalDurationMinutes = $trips->sum(fn (FleetTrip $trip) => $this->tripDurationMinutes($trip));

        return [
            'summary' => [
                'total_trips' => $trips->count(),
                'total_distance' => round($trips->sum(fn (FleetTrip $trip) => $this->tripDistanceKm($trip)), 2),
                'total_duration_hours' => round($totalDurationMinutes / 60, 2),
            ],
            'chart' => [
                'labels' => $labels,
                'trip_counts' => $tripCounts,
                'distance' => $distanceValues,
            ],
            'rows' => $rows,
        ];
    }

    private function tripDistanceKm(FleetTrip $trip): float
    {
        if ((float) $trip->distance_km > 0) {
            return (float) $trip->distance_km;
        }

        if ($trip->relationLoaded('track') && $trip->track) {
            return (float) ($trip->track->distance_km ?? 0);
        }

        return 0;
    }

    private function tripDurationMinutes(FleetTrip $trip): int
    {
        if ($trip->relationLoaded('track') && $trip->track && (int) $trip->track->duration_minutes > 0) {
            return (int) $trip->track->duration_minutes;
        }

        if ($trip->start_date && $trip->end_date) {
            $start = Carbon::parse($trip->start_date->format('Y-m-d') . ' ' . substr((string) ($trip->start_time ?: '00:00:00'), 0, 8));
            $end = Carbon::parse($trip->end_date->format('Y-m-d') . ' ' . substr((string) ($trip->end_time ?: '23:59:59'), 0, 8));

            return max(0, $start->diffInMinutes($end));
        }

        return 0;
    }

    private function filteredTrips(string $dateFrom, string $dateTo, $vehicleId, string $statusFilter, $customerId)
    {
        return FleetTrip::query()
            ->with(['vehicle'])
            ->withSum('expenses as expenses_total', 'amount')
            ->whereDate('start_date', '>=', $dateFrom)
            ->whereDate('start_date', '<=', $dateTo)
            ->when($vehicleId !== '' && $vehicleId !== null, fn ($query) => $query->where('fleet_vehicle_id', (int) $vehicleId))
            ->when($customerId !== '' && $customerId !== null, function ($query) use ($customerId) {
                $query->where(function ($inner) use ($customerId) {
                    $inner->where('fleet_customer_id', (int) $customerId)
                        ->orWhere('customer_name', FleetCustomer::query()->whereKey((int) $customerId)->value('name'));
                });
            })
            ->when($statusFilter !== '', function ($query) use ($statusFilter) {
                $statuses = $this->statusValuesForFilter($statusFilter);
                $query->whereIn('status', $statuses);
            })
            ->orderBy('start_date')
            ->get();
    }

    private function statusValuesForFilter(string $filter): array
    {
        return match ($filter) {
            'completed' => ['completed', 'Completed'],
            'ongoing' => ['ongoing', 'Ongoing'],
            'yet_to_start' => ['Booked', 'pending', 'Pending'],
            'cancelled' => ['cancelled', 'Cancelled'],
            default => [],
        };
    }

    private function tripProfitRows($trips)
    {
        return $trips->values()->map(function (FleetTrip $trip, int $index) {
            $amount = $trip->totalAmount();
            $expenses = $trip->totalExpensesAmount();
            $profit = $trip->profitAmount();

            return [
                'serial' => $index + 1,
                'trip_code' => $trip->displayTripCode(),
                'trip_url' => route('trips.show', $trip),
                'customer_name' => $trip->customer_name ?: '-',
                'vehicle_name' => optional($trip->vehicle)->displayName() ?: '-',
                'route' => $trip->routeLocationShort($trip->pickup_location) . ' → ' . $trip->routeLocationShort($trip->drop_location),
                'start_date' => format_fleet_date($trip->start_date),
                'status' => $this->reportStatusLabel($trip->status),
                'status_class' => $trip->statusBadgeClass(),
                'trip_amount' => $amount,
                'expenses' => $expenses,
                'profit' => $profit,
                'profit_class' => $profit >= 0 ? 'is-profit-positive' : 'is-profit-negative',
            ];
        });
    }

    private function reportStatusLabel(?string $status): string
    {
        $normalized = strtolower((string) $status);

        if (in_array($normalized, ['completed'], true)) {
            return 'Completed';
        }

        if (in_array($normalized, ['ongoing'], true)) {
            return 'Ongoing';
        }

        if (in_array($normalized, ['cancelled'], true)) {
            return 'Cancelled';
        }

        return 'Yet to Start';
    }

    private function revenueTrend($trips, string $dateFrom, string $dateTo): array
    {
        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->startOfDay();
        $totals = [];

        foreach ($trips as $trip) {
            if (! $trip->start_date) {
                continue;
            }

            $key = $trip->start_date->format('Y-m-d');
            $totals[$key] = ($totals[$key] ?? 0) + $trip->totalAmount();
        }

        $labels = [];
        $values = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $labels[] = $key;
            $values[] = round($totals[$key] ?? 0, 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function remindersReport(string $dateFrom, string $dateTo, string $search = ''): array
    {
        $reminders = FleetReminder::query()
            ->with('vehicle')
            ->whereDate('due_date', '>=', $dateFrom)
            ->whereDate('due_date', '<=', $dateTo)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('notes', 'like', '%' . $search . '%')
                        ->orWhere('services', 'like', '%' . $search . '%')
                        ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                            $vehicleQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('registration_number', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $completed = $reminders->where('is_completed', true)->count();
        $pending = $reminders->where('is_completed', false)->count();

        $rows = $reminders->values()->map(function (FleetReminder $reminder, int $index) {
            return [
                'serial' => $index + 1,
                'date' => $reminder->badgeDate(),
                'title' => trim((string) $reminder->notes) ?: '-',
                'vehicle_name' => optional($reminder->vehicle)->displayName() ?: '-',
                'services' => $reminder->servicesList(),
                'due_status' => $reminder->is_completed ? 'Completed' : $reminder->statusLabel(),
                'due_status_class' => $reminder->is_completed ? 'is-completed' : $reminder->statusClass(),
                'completion_status' => $reminder->is_completed ? 'Completed' : 'Pending',
                'completion_class' => $reminder->is_completed ? 'is-completed' : 'is-pending',
            ];
        });

        return [
            'summary' => [
                'total' => $reminders->count(),
                'completed' => $completed,
                'pending' => $pending,
            ],
            'chart' => [
                'completed' => $completed,
                'pending' => $pending,
            ],
            'rows' => $rows,
        ];
    }

    private function maintenanceReport(string $dateFrom, string $dateTo, string $search = ''): array
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

        $chartLabels = array_keys($costByVehicle);
        $chartValues = array_values($costByVehicle);

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
            ],
            'chart' => [
                'labels' => $chartLabels,
                'values' => $chartValues,
            ],
            'rows' => $rows,
        ];
    }

    private function queryFilter(Request $request, string $key, string $default = ''): string
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    private function sharedViewData(): array
    {
        return fleet_shared_view_data();
    }
}
