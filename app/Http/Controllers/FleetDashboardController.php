<?php



namespace App\Http\Controllers;



use App\Models\FleetCustomer;

use App\Models\FleetDriver;

use App\Models\FleetIncidentReport;

use App\Models\FleetMaintenance;

use App\Models\FleetReminder;

use App\Models\FleetTrip;

use App\Models\FleetTripExpense;

use App\Models\FleetTripPayment;

use App\Models\FleetVehicle;

use App\Models\FleetVehicleFuelRefill;

use Illuminate\Support\Carbon;



class FleetDashboardController extends Controller

{

    public function index()

    {

        $today = Carbon::today();

        $monthStart = Carbon::now()->startOfMonth();

        $monthEnd = Carbon::now()->endOfMonth();



        $vehicleCount = FleetVehicle::query()->count();

        $driverCount = FleetDriver::query()->count();

        $customerCount = FleetCustomer::query()->count();

        $todayTrips = FleetTrip::query()->whereDate('start_date', $today)->count();



        $fleet = $this->fleetTrackingStats($vehicleCount);

        [$fuelLabels, $fuelData] = $this->fuelConsumptionSeries($today);



        $monthFuelCost = (float) FleetVehicleFuelRefill::query()

            ->whereBetween('refill_date', [$monthStart, $monthEnd])

            ->sum('cost');

        $monthMaintenanceCost = (float) FleetMaintenance::query()

            ->whereBetween('start_date', [$monthStart, $monthEnd])

            ->sum('total_cost');

        $monthExpenseCost = (float) FleetTripExpense::query()

            ->whereBetween('expense_date', [$monthStart, $monthEnd])

            ->sum('amount');



        $monthDistance = (float) FleetTrip::query()

            ->whereBetween('start_date', [$monthStart, $monthEnd])

            ->sum('distance_km');

        $monthFuelLiters = (float) FleetVehicleFuelRefill::query()

            ->whereBetween('refill_date', [$monthStart, $monthEnd])

            ->sum('liters');



        $monthTotalCost = round($monthFuelCost + $monthMaintenanceCost + $monthExpenseCost, 2);

        $avgEfficiency = $monthFuelLiters > 0 ? round($monthDistance / $monthFuelLiters, 2) : 0;

        $costPerKm = $monthDistance > 0 ? round($monthTotalCost / $monthDistance, 2) : 0;



        $serviceRemindersPending = FleetReminder::query()->where('is_completed', false)->count();

        $openIncidents = FleetIncidentReport::query()

            ->whereRaw('LOWER(status) = ?', ['open'])

            ->count();



        $recentTransactions = FleetTripPayment::query()

            ->with(['customer', 'trip'])

            ->latest('payment_date')

            ->latest('id')

            ->limit(5)

            ->get();



        $todayTripRows = FleetTrip::query()

            ->with(['customer', 'vehicle'])

            ->whereDate('start_date', $today)

            ->orderBy('start_time')

            ->orderBy('id')

            ->limit(5)

            ->get();



        $shared = fleet_shared_view_data($serviceRemindersPending + $openIncidents);



        return view('fleet.dashboard', array_merge($shared, [

            'activeMenu' => 'dashboard',

            'stats' => [

                'vehicles' => $vehicleCount,

                'drivers' => $driverCount,

                'customers' => $customerCount,

                'today_trips' => $todayTrips,

            ],

            'fleet' => $fleet,

            'fuelLabels' => $fuelLabels,

            'fuelData' => $fuelData,
            'monthLabel' => Carbon::now()->format('M Y'),

            'costOverview' => [

                'month_label' => Carbon::now()->format('M Y'),

                'total' => $monthTotalCost,

                'fuel' => round($monthFuelCost, 2),

                'maintenance' => round($monthMaintenanceCost, 2),

                'expenses' => round($monthExpenseCost, 2),

            ],

            'performance' => [

                'total_distance' => round($monthDistance, 2),

                'avg_efficiency' => $avgEfficiency,

                'cost_per_km' => $costPerKm,

            ],

            'vehicleHealth' => [

                'service_overdue' => FleetReminder::query()

                    ->where('is_completed', false)

                    ->whereDate('due_date', '<', $today)

                    ->count(),

                'due_soon' => FleetReminder::query()

                    ->where('is_completed', false)

                    ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])

                    ->count(),

                'dtc_codes' => 0,

            ],

            'serviceRemindersPending' => $serviceRemindersPending,

            'openIncidents' => $openIncidents,

            'recentTransactions' => $recentTransactions,

            'todayTripRows' => $todayTripRows,

        ]));

    }



    private function fleetTrackingStats(int $vehicleCount): array

    {

        $vehicles = FleetVehicle::query()->with('position')->get();



        $moving = 0;

        $parked = 0;

        $offline = 0;

        $noData = 0;



        foreach ($vehicles as $vehicle) {

            $position = $vehicle->position;



            if (! $position || $position->latitude === null || $position->longitude === null) {

                $noData++;

                continue;

            }



            match (strtolower((string) ($position->tracking_status ?: 'idle'))) {

                'moving' => $moving++,

                'offline' => $offline++,

                default => $parked++,

            };

        }



        $inTrip = FleetTrip::query()

            ->whereNotNull('fleet_vehicle_id')

            ->whereIn('status', ['ongoing', 'Ongoing'])

            ->distinct()

            ->count('fleet_vehicle_id');



        return [

            'moving' => $moving,

            'parked' => $parked,

            'offline' => $offline,

            'no_data' => $noData,

            'in_trip' => $inTrip,

            'available' => max(0, $vehicleCount - $inTrip),

        ];

    }



    private function fuelConsumptionSeries(Carbon $today): array

    {

        $labels = [];

        $data = [];



        for ($i = 6; $i >= 0; $i--) {

            $day = $today->copy()->subDays($i);

            $labels[] = $day->format('D');

            $data[] = round((float) FleetVehicleFuelRefill::query()

                ->whereDate('refill_date', $day)

                ->sum('liters'), 2);

        }



        return [$labels, $data];

    }

}

