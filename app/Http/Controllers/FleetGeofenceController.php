<?php



namespace App\Http\Controllers;



use App\Models\FleetGeofence;

use App\Models\FleetVehicle;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Http;



class FleetGeofenceController extends Controller

{

    public function index(Request $request)

    {

        $search = trim((string) $request->query('search', ''));



        $geofences = FleetGeofence::query()

            ->when($search !== '', function ($query) use ($search) {

                $query->where(function ($builder) use ($search) {

                    $builder->where('name', 'like', '%' . $search . '%')

                        ->orWhere('description', 'like', '%' . $search . '%')

                        ->orWhere('location_label', 'like', '%' . $search . '%');

                });

            })

            ->latest('id')

            ->get()

            ->map(fn (FleetGeofence $geofence) => $this->managementPayload($geofence))

            ->values();



        return view('fleet.geofences.index', array_merge($this->sharedViewData(), [

            'activeMenu' => 'geofence-manage',

            'openMenu' => 'geofence',

            'geofences' => $geofences,

            'search' => $search,

            'defaultCenter' => [

                'lat' => 13.0415,

                'lng' => 80.2335,

            ],

        ]));

    }



    public function create()

    {

        $geofences = FleetGeofence::query()

            ->latest('id')

            ->get()

            ->map(fn (FleetGeofence $geofence) => $this->mapPayload($geofence))

            ->values();



        return view('fleet.geofences.create', array_merge($this->sharedViewData(), [

            'activeMenu' => 'geofence-create',

            'openMenu' => 'geofence',

            'geofences' => $geofences,

            'defaultLocation' => 'Delhi, India',

            'defaultCenter' => [

                'lat' => 28.613939,

                'lng' => 77.209023,

            ],

        ]));

    }



    public function store(Request $request)

    {

        if (is_string($request->input('geometry'))) {

            $decoded = json_decode($request->input('geometry'), true);

            $request->merge(['geometry' => is_array($decoded) ? $decoded : null]);

        }



        $validated = $request->validate([

            'location_label' => 'required|string|max:255',

            'shape_type' => 'required|in:marker,circle,rectangle,polygon',

            'geometry' => 'required|array',

            'center_lat' => 'nullable|numeric',

            'center_lng' => 'nullable|numeric',

        ]);



        FleetGeofence::create(array_merge($validated, [

            'name' => $validated['location_label'],

            'description' => null,

            'created_by' => 'admin',

            'notify_sms' => false,

            'notify_email' => false,

            'assigned_vehicle_ids' => [],

        ]));



        return redirect()

            ->route('geofences.index')

            ->with('success', 'Geofence saved successfully.');

    }



    public function destroy(FleetGeofence $geofence)

    {

        $geofence->delete();



        return redirect()

            ->route('geofences.index')

            ->with('success', 'Geofence deleted.');

    }



    public function geocode(Request $request)

    {

        $validated = $request->validate([

            'query' => 'required|string|max:255',

        ]);



        $location = $this->geocodeLocation($validated['query']);



        if (! $location) {

            return response()->json([

                'success' => false,

                'message' => 'Location not found.',

            ], 422);

        }



        return response()->json([

            'success' => true,

            'location' => $location,

        ]);

    }



    private function managementPayload(FleetGeofence $geofence): array

    {

        return array_merge($this->mapPayload($geofence), [

            'name' => $geofence->displayName(),

            'description' => $geofence->description,

            'created_by' => $geofence->created_by ?: 'admin',

            'created_date' => optional($geofence->created_at)->format('d M Y') ?: '-',

            'notify_sms' => (bool) $geofence->notify_sms,

            'notify_email' => (bool) $geofence->notify_email,

            'vehicles_label' => $this->vehicleLabel($geofence->assigned_vehicle_ids ?? []),

        ]);

    }



    private function mapPayload(FleetGeofence $geofence): array

    {

        return [

            'id' => $geofence->id,

            'name' => $geofence->displayName(),

            'location_label' => $geofence->location_label,

            'shape_type' => $geofence->shape_type,

            'geometry' => $geofence->geometry,

            'center_lat' => $geofence->center_lat ? (float) $geofence->center_lat : null,

            'center_lng' => $geofence->center_lng ? (float) $geofence->center_lng : null,

        ];

    }



    private function vehicleLabel(array $vehicleIds): string

    {

        if (empty($vehicleIds)) {

            return 'No vehicles assigned';

        }



        $names = FleetVehicle::query()

            ->whereIn('id', $vehicleIds)

            ->orderBy('name')

            ->pluck('name')

            ->filter()

            ->values();



        if ($names->isEmpty()) {

            return 'No vehicles assigned';

        }



        $label = $names->take(2)->implode(', ');



        if ($names->count() > 2) {

            $label .= ', Fleet ...';

        }



        return $label;

    }



    private function geocodeLocation(string $address): ?array

    {

        $address = trim($address);



        if ($address === '') {

            return null;

        }



        $cacheKey = 'fleet_geocode:' . md5(strtolower($address));



        if ($cached = Cache::get($cacheKey)) {

            return $cached;

        }



        try {

            $response = Http::timeout(10)

                ->withHeaders(['User-Agent' => 'OneTranslinesFleet/1.0'])

                ->get('https://nominatim.openstreetmap.org/search', [

                    'q' => $address,

                    'format' => 'json',

                    'limit' => 1,

                ]);



            if (! $response->successful()) {

                return null;

            }



            $result = $response->json()[0] ?? null;



            if (! $result) {

                return null;

            }



            $location = [

                'lat' => (float) $result['lat'],

                'lng' => (float) $result['lon'],

                'label' => $address,

            ];



            Cache::put($cacheKey, $location, now()->addDays(7));



            return $location;

        } catch (\Throwable $e) {

            return null;

        }

    }



    private function sharedViewData(): array

    {

        return [

            'companyName' => 'One Translines Pvt Ltd',

            'notificationCount' => 11,

        ];

    }

}


