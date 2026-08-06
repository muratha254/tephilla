<?php

namespace App\Http\Controllers;

use App\Models\FleetDriver;
use App\Models\FleetTrip;
use App\Models\FleetVehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FleetSmartDispatchController extends Controller
{
    public function index()
    {
        $bookings = FleetTrip::query()
            ->whereIn('status', ['Booked', 'pending', 'Pending'])
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();

        $drivers = FleetDriver::query()->where('status', 'Active')->orderBy('name')->get();
        $vehicles = FleetVehicle::query()->where('status', 'Active')->orderBy('name')->get();

        $bookingRows = $bookings->map(function (FleetTrip $trip) {
            return [
                'id' => $trip->id,
                'code' => $trip->displayTripCode(),
                'time' => $this->formatBookingTime($trip),
                'location' => $trip->routeLocationShort($trip->pickup_location),
                'pickup' => $trip->pickup_location,
            ];
        })->values();

        return view('fleet.trips.smart_dispatch', [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
            'activeMenu' => 'trip-dispatch',
            'openMenu' => 'trips',
            'bookings' => $bookingRows,
            'drivers' => $drivers,
            'vehicles' => $vehicles,
        ]);
    }

    public function optimize(Request $request)
    {
        $validated = $request->validate([
            'trip_ids' => 'required|array|min:1',
            'trip_ids.*' => 'integer|exists:fleet_trips,id',
        ]);

        $trips = FleetTrip::query()
            ->whereIn('id', $validated['trip_ids'])
            ->get()
            ->keyBy('id');

        $stops = [];

        foreach ($validated['trip_ids'] as $tripId) {
            $trip = $trips->get($tripId);

            if (! $trip) {
                continue;
            }

            $location = $this->geocodeLocation($trip->pickup_location);

            if (! $location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not locate pickup for ' . $trip->displayTripCode() . '.',
                ], 422);
            }

            $stops[] = array_merge($location, [
                'trip_id' => $trip->id,
                'code' => $trip->displayTripCode(),
                'location' => $trip->routeLocationShort($trip->pickup_location),
            ]);
        }

        if (count($stops) === 1) {
            return response()->json([
                'success' => true,
                'order' => [$stops[0]['trip_id']],
                'stops' => $stops,
                'route' => [
                    'points' => [[ 'lat' => $stops[0]['lat'], 'lng' => $stops[0]['lng'] ]],
                    'distance_km' => 0,
                    'duration_minutes' => 0,
                ],
            ]);
        }

        $optimized = $this->optimizeStopOrder($stops);

        if (! $optimized) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to optimize route for selected bookings.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'order' => collect($optimized['stops'])->pluck('trip_id')->values()->all(),
            'stops' => $optimized['stops'],
            'route' => [
                'points' => $optimized['points'],
                'distance_km' => $optimized['distance_km'],
                'duration_minutes' => $optimized['duration_minutes'],
            ],
        ]);
    }

    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'trip_ids' => 'required|array|min:1',
            'trip_ids.*' => 'integer|exists:fleet_trips,id',
            'fleet_driver_id' => 'required|exists:fleet_drivers,id',
            'fleet_vehicle_id' => 'required|exists:fleet_vehicles,id',
        ]);

        FleetTrip::query()
            ->whereIn('id', $validated['trip_ids'])
            ->update([
                'fleet_driver_id' => $validated['fleet_driver_id'],
                'fleet_vehicle_id' => $validated['fleet_vehicle_id'],
                'status' => 'ongoing',
            ]);

        return response()->json([
            'success' => true,
            'message' => count($validated['trip_ids']) . ' booking(s) dispatched successfully.',
            'redirect' => route('trips.index', ['tab' => 'ongoing']),
        ]);
    }

    public function mapMarkers(Request $request)
    {
        $validated = $request->validate([
            'trip_ids' => 'nullable|array',
            'trip_ids.*' => 'integer|exists:fleet_trips,id',
        ]);

        $query = FleetTrip::query()->whereIn('status', ['Booked', 'pending', 'Pending']);

        if (! empty($validated['trip_ids'])) {
            $query->whereIn('id', $validated['trip_ids']);
        }

        $markers = [];

        foreach ($query->get() as $trip) {
            $location = $this->geocodeLocation($trip->pickup_location);

            if (! $location) {
                continue;
            }

            $markers[] = array_merge($location, [
                'trip_id' => $trip->id,
                'code' => $trip->displayTripCode(),
                'time' => $this->formatBookingTime($trip),
                'location' => $trip->routeLocationShort($trip->pickup_location),
            ]);
        }

        return response()->json([
            'success' => true,
            'markers' => $markers,
        ]);
    }

    private function formatBookingTime(FleetTrip $trip): string
    {
        if (! $trip->start_time) {
            return '--:--';
        }

        $value = substr((string) $trip->start_time, 0, 8);

        try {
            return \Carbon\Carbon::createFromFormat(strlen($value) > 5 ? 'H:i:s' : 'H:i', $value)->format('h:i A');
        } catch (\Exception $e) {
            return substr((string) $trip->start_time, 0, 5);
        }
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

    private function optimizeStopOrder(array $stops): ?array
    {
        try {
            $coords = collect($stops)
                ->map(fn ($stop) => $stop['lng'] . ',' . $stop['lat'])
                ->implode(';');

            $response = Http::timeout(20)->get(
                'https://router.project-osrm.org/trip/v1/driving/' . $coords,
                [
                    'source' => 'first',
                    'roundtrip' => 'false',
                    'geometries' => 'geojson',
                    'overview' => 'full',
                ]
            );

            if (! $response->successful()) {
                return $this->fallbackStopOrder($stops);
            }

            $payload = $response->json();
            $trip = $payload['trips'][0] ?? null;
            $waypoints = $payload['waypoints'] ?? [];

            if (! $trip || empty($waypoints)) {
                return $this->fallbackStopOrder($stops);
            }

            $orderedStops = collect($waypoints)
                ->map(function ($waypoint, $inputIndex) use ($stops) {
                    return [
                        'order' => (int) ($waypoint['waypoint_index'] ?? $inputIndex),
                        'stop' => $stops[$inputIndex] ?? null,
                    ];
                })
                ->filter(fn ($item) => $item['stop'] !== null)
                ->sortBy('order')
                ->pluck('stop')
                ->values()
                ->all();

            $points = collect($trip['geometry']['coordinates'] ?? [])
                ->map(fn ($point) => ['lat' => (float) $point[1], 'lng' => (float) $point[0]])
                ->values()
                ->all();

            return [
                'stops' => $orderedStops,
                'points' => $points,
                'distance_km' => round(((float) ($trip['distance'] ?? 0)) / 1000, 1),
                'duration_minutes' => round(((float) ($trip['duration'] ?? 0)) / 60),
            ];
        } catch (\Throwable $e) {
            return $this->fallbackStopOrder($stops);
        }
    }

    private function fallbackStopOrder(array $stops): ?array
    {
        if (empty($stops)) {
            return null;
        }

        $points = collect($stops)->map(fn ($stop) => ['lat' => $stop['lat'], 'lng' => $stop['lng']])->all();

        return [
            'stops' => $stops,
            'points' => $points,
            'distance_km' => 0,
            'duration_minutes' => 0,
        ];
    }
}
