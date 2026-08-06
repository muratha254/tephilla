<?php



namespace App\Http\Controllers;



use App\Models\FleetDriver;
use App\Models\FleetTrip;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Storage;

use Illuminate\Validation\Rule;



class FleetDriverController extends Controller

{

    public function index(Request $request)

    {

        $search = trim((string) $request->query('search', ''));

        $status = trim((string) $request->query('status', ''));



        $counts = [

            'all' => FleetDriver::query()->count(),

            'active' => FleetDriver::query()->where('status', 'Active')->count(),

            'inactive' => FleetDriver::query()->where('status', 'Inactive')->count(),

        ];



        $drivers = FleetDriver::query()

            ->when($search !== '', function ($query) use ($search) {

                $query->where(function ($inner) use ($search) {

                    $inner->where('name', 'like', "%{$search}%")

                        ->orWhere('mobile', 'like', "%{$search}%")

                        ->orWhere('email', 'like', "%{$search}%")

                        ->orWhere('license_number', 'like', "%{$search}%");

                });

            })

            ->when($status !== '', function ($query) use ($status) {

                $query->where('status', $status);

            })

            ->orderBy('name')

            ->get();



        return view('fleet.drivers.index', array_merge($this->sharedViewData(), [

            'activeMenu' => 'driver-list',

            'openMenu' => 'drivers',

            'drivers' => $drivers,

            'counts' => $counts,

            'search' => $search,

            'statusFilter' => $status,

        ]));

    }



    public function show(FleetDriver $driver)

    {

        return view('fleet.drivers.show', array_merge($this->sharedViewData(), [

            'activeMenu' => 'driver-list',

            'openMenu' => 'drivers',

            'driver' => $driver,

        ]));

    }



    public function create()

    {

        return view('fleet.drivers.create', array_merge($this->sharedViewData(), [

            'activeMenu' => 'driver-add',

            'openMenu' => 'drivers',

            'driver' => null,

        ]));

    }



    public function store(Request $request)

    {

        $validated = $this->validateDriver($request);

        $validated['password'] = Hash::make($validated['password']);



        if ($request->hasFile('photo')) {

            $validated['photo_path'] = $request->file('photo')->store('drivers', 'public');

        }



        if ($request->hasFile('license_doc')) {

            $validated['license_doc'] = $request->file('license_doc')->store('driver-docs', 'public');

        }



        if ($request->hasFile('id_doc')) {

            $validated['id_doc'] = $request->file('id_doc')->store('driver-docs', 'public');

        }



        unset($validated['photo']);



        FleetDriver::create($validated);



        return redirect()

            ->route('drivers.index')

            ->with('success', 'Driver created successfully.');

    }



    public function edit(FleetDriver $driver)

    {

        return view('fleet.drivers.edit', array_merge($this->sharedViewData(), [

            'activeMenu' => 'driver-list',

            'openMenu' => 'drivers',

            'driver' => $driver,

        ]));

    }



    public function update(Request $request, FleetDriver $driver)

    {

        $validated = $this->validateDriver($request, $driver);



        if (! empty($validated['password'])) {

            $validated['password'] = Hash::make($validated['password']);

        } else {

            unset($validated['password']);

        }



        if ($request->hasFile('photo')) {

            if ($driver->photo_path) {

                Storage::disk('public')->delete($driver->photo_path);

            }

            $validated['photo_path'] = $request->file('photo')->store('drivers', 'public');

        }



        if ($request->hasFile('license_doc')) {

            if ($driver->license_doc) {

                Storage::disk('public')->delete($driver->license_doc);

            }

            $validated['license_doc'] = $request->file('license_doc')->store('driver-docs', 'public');

        }



        if ($request->hasFile('id_doc')) {

            if ($driver->id_doc) {

                Storage::disk('public')->delete($driver->id_doc);

            }

            $validated['id_doc'] = $request->file('id_doc')->store('driver-docs', 'public');

        }



        unset($validated['photo']);



        $driver->update($validated);



        return redirect()

            ->route('drivers.index')

            ->with('success', 'Driver updated successfully.');

    }



    public function destroy(FleetDriver $driver)

    {

        foreach (['photo_path', 'license_doc', 'id_doc'] as $fileField) {

            if ($driver->{$fileField}) {

                Storage::disk('public')->delete($driver->{$fileField});

            }

        }



        $driver->delete();



        return redirect()

            ->route('drivers.index')

            ->with('success', 'Driver deleted successfully.');

    }



    private function validateDriver(Request $request, ?FleetDriver $driver = null): array

    {

        $rules = [

            'name' => 'required|string|max:150',

            'mobile' => 'required|string|max:30',

            'email' => [

                'required',

                'email',

                'max:150',

                Rule::unique('fleet_drivers', 'email')->ignore($driver?->id),

            ],

            'status' => 'required|in:Active,Inactive',

            'age' => 'required|integer|min:18|max:100',

            'date_of_joining' => 'required|date',

            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            'license_number' => 'nullable|string|max:100',

            'license_expiry' => 'nullable|date',

            'license_doc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',

            'id_number' => 'nullable|string|max:100',

            'id_doc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',

            'employment_type' => 'nullable|string|max:100',

            'department' => 'nullable|string|max:100',

            'employee_id' => 'nullable|string|max:100',

            'contract_end_date' => 'nullable|date',

            'salary' => 'nullable|numeric|min:0',

            'payment_type' => 'nullable|string|max:100',

            'bank_name' => 'nullable|string|max:150',

            'bank_account' => 'nullable|string|max:100',

        ];



        $rules['password'] = $driver

            ? 'nullable|string|min:4|max:100'

            : 'required|string|min:4|max:100';



        return $request->validate($rules);

    }



    public function performance(Request $request)
    {
        $dateFrom = $this->performanceFilter($request, 'date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $this->performanceFilter($request, 'date_to', now()->format('Y-m-d'));

        $trips = FleetTrip::query()
            ->with('track')
            ->whereNotNull('fleet_driver_id')
            ->whereDate('start_date', '>=', $dateFrom)
            ->whereDate('start_date', '<=', $dateTo)
            ->get();

        $tripsByDriver = $trips->groupBy('fleet_driver_id');
        $drivers = FleetDriver::query()->orderBy('name')->get();

        $rows = $drivers->map(function (FleetDriver $driver) use ($tripsByDriver) {
            $driverTrips = $tripsByDriver->get($driver->id, collect());
            $totalTrips = $driverTrips->count();
            $completedTrips = $driverTrips->filter(fn (FleetTrip $trip) => strtolower((string) $trip->status) === 'completed')->count();
            $cancelledTrips = $driverTrips->filter(fn (FleetTrip $trip) => strtolower((string) $trip->status) === 'cancelled')->count();
            $ongoingTrips = $driverTrips->filter(fn (FleetTrip $trip) => strtolower((string) $trip->status) === 'ongoing')->count();
            $distanceKm = round($driverTrips->sum(fn (FleetTrip $trip) => $this->tripDistanceKm($trip)), 2);
            $durationMinutes = (int) $driverTrips->sum(fn (FleetTrip $trip) => $this->tripDurationMinutes($trip));
            $revenue = round($driverTrips->sum(fn (FleetTrip $trip) => $trip->totalAmount()), 2);

            return [
                'driver' => $driver,
                'total_trips' => $totalTrips,
                'completed_trips' => $completedTrips,
                'cancelled_trips' => $cancelledTrips,
                'ongoing_trips' => $ongoingTrips,
                'distance_km' => $distanceKm,
                'duration_hours' => round($durationMinutes / 60, 2),
                'revenue' => $revenue,
                'completion_rate' => $totalTrips > 0 ? round(($completedTrips / $totalTrips) * 100, 1) : 0,
                'avg_revenue' => $totalTrips > 0 ? round($revenue / $totalTrips, 2) : 0,
            ];
        })
            ->sortByDesc('total_trips')
            ->values();

        $activeRows = $rows->filter(fn (array $row) => $row['total_trips'] > 0)->values();
        $topDriver = $activeRows->first();

        $summary = [
            'active_drivers' => $activeRows->count(),
            'total_trips' => (int) $rows->sum('total_trips'),
            'completed_trips' => (int) $rows->sum('completed_trips'),
            'total_distance' => round($rows->sum('distance_km'), 2),
            'total_duration_hours' => round($rows->sum('duration_hours'), 2),
            'total_revenue' => round($rows->sum('revenue'), 2),
        ];

        $chartDrivers = $activeRows->take(8);

        return view('fleet.drivers.performance', array_merge($this->sharedViewData(), [
            'activeMenu' => 'driver-performance',
            'openMenu' => 'drivers',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'topDriver' => $topDriver,
            'summary' => $summary,
            'chart' => [
                'labels' => $chartDrivers->map(fn (array $row) => $row['driver']->name)->values()->all(),
                'trip_counts' => $chartDrivers->pluck('total_trips')->all(),
                'revenue' => $chartDrivers->pluck('revenue')->all(),
            ],
        ]));
    }

    private function performanceFilter(Request $request, string $key, string $default = ''): string
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
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

    private function sharedViewData(): array

    {

        return [

            'companyName' => 'One Translines Pvt Ltd',

            'notificationCount' => 11,

        ];

    }

}


