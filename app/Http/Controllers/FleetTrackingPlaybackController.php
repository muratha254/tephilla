<?php

namespace App\Http\Controllers;

use App\Models\FleetTrip;
use App\Models\FleetVehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FleetTrackingPlaybackController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = FleetVehicle::query()
            ->orderBy('name')
            ->orderBy('registration_number')
            ->get();

        $selectedVehicleId = (int) $request->query('vehicle_id', $vehicles->first()?->id ?? 0);
        $dateFrom = $request->query('date_from', '2025-12-30');
        $dateTo = $request->query('date_to', '2026-01-14');

        $trips = $this->tripsQuery($selectedVehicleId, $dateFrom, $dateTo)
            ->with('track')
            ->get()
            ->map(fn (FleetTrip $trip) => $this->tripPayload($trip))
            ->values();

        return view('fleet.tracking.playback', array_merge($this->sharedViewData(), [
            'activeMenu' => 'tracking-history',
            'openMenu' => 'tracking',
            'vehicles' => $vehicles,
            'selectedVehicleId' => $selectedVehicleId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'trips' => $trips,
        ]));
    }

    public function trips(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|integer|exists:fleet_vehicles,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $trips = $this->tripsQuery(
            (int) $validated['vehicle_id'],
            $validated['date_from'],
            $validated['date_to']
        )
            ->with('track')
            ->get()
            ->map(fn (FleetTrip $trip) => $this->tripPayload($trip))
            ->values();

        return response()->json([
            'success' => true,
            'trips' => $trips,
        ]);
    }

    public function route(FleetTrip $trip)
    {
        $trip->load('track');

        if ($trip->track && ! empty($trip->track->route_points)) {
            return response()->json([
                'success' => true,
                'trip' => $this->tripPayload($trip),
                'route' => $this->routePayload($trip, $trip->track->route_points),
            ]);
        }

        $route = $this->buildRouteFromLocations($trip);

        if (! $route) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to build route for this trip.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'trip' => $this->tripPayload($trip),
            'route' => $route,
        ]);
    }

    private function tripsQuery(int $vehicleId, string $dateFrom, string $dateTo)
    {
        return FleetTrip::query()
            ->where('fleet_vehicle_id', $vehicleId)
            ->whereDate('start_date', '>=', $dateFrom)
            ->whereDate('start_date', '<=', $dateTo)
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    private function tripPayload(FleetTrip $trip): array
    {
        $track = $trip->relationLoaded('track') ? $trip->track : $trip->track()->first();

        return [
            'id' => $trip->id,
            'number' => $trip->id,
            'code' => $trip->displayTripCode(),
            'pickup' => $trip->routeLocationShort($trip->pickup_location),
            'drop' => $trip->routeLocationShort($trip->drop_location),
            'pickup_full' => $trip->pickup_location,
            'drop_full' => $trip->drop_location,
            'start_date' => optional($trip->start_date)->format('Y-m-d'),
            'distance_km' => $track ? (float) $track->distance_km : null,
            'distance_label' => $track ? number_format((float) $track->distance_km, 2) . ' km' : '-',
            'duration_label' => $track ? $track->formattedDuration() : '-',
            'route_url' => route('tracking.playback.route', $trip),
        ];
    }

    private function routePayload(FleetTrip $trip, array $points): array
    {
        $track = $trip->track;

        return [
            'points' => $points,
            'distance_km' => $track ? (float) $track->distance_km : $this->estimateDistanceKm($points),
            'duration_minutes' => $track ? (int) $track->duration_minutes : 0,
            'start' => $points[0] ?? null,
            'end' => $points[count($points) - 1] ?? null,
            'pickup' => $trip->routeLocationShort($trip->pickup_location),
            'drop' => $trip->routeLocationShort($trip->drop_location),
        ];
    }

    private function buildRouteFromLocations(FleetTrip $trip): ?array
    {
        $pickup = $this->geocodeLocation($trip->pickup_location);
        $drop = $this->geocodeLocation($trip->drop_location);

        if (! $pickup || ! $drop) {
            return null;
        }

        try {
            $response = Http::timeout(20)->get(
                'https://router.project-osrm.org/route/v1/driving/' . $pickup['lng'] . ',' . $pickup['lat'] . ';' . $drop['lng'] . ',' . $drop['lat'],
                [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                ]
            );

            if ($response->successful()) {
                $route = $response->json('routes.0');

                if ($route) {
                    $points = collect($route['geometry']['coordinates'] ?? [])
                        ->map(fn ($point) => ['lat' => (float) $point[1], 'lng' => (float) $point[0]])
                        ->values()
                        ->all();

                    if (! empty($points)) {
                        return [
                            'points' => $points,
                            'distance_km' => round(((float) ($route['distance'] ?? 0)) / 1000, 2),
                            'duration_minutes' => (int) round(((float) ($route['duration'] ?? 0)) / 60),
                            'start' => $points[0],
                            'end' => $points[count($points) - 1],
                            'pickup' => $trip->routeLocationShort($trip->pickup_location),
                            'drop' => $trip->routeLocationShort($trip->drop_location),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall back to interpolated route below.
        }

        $points = $this->interpolatePoints(
            (float) $pickup['lat'],
            (float) $pickup['lng'],
            (float) $drop['lat'],
            (float) $drop['lng']
        );

        return [
            'points' => $points,
            'distance_km' => $this->estimateDistanceKm($points),
            'duration_minutes' => 0,
            'start' => $points[0],
            'end' => $points[count($points) - 1],
            'pickup' => $trip->routeLocationShort($trip->pickup_location),
            'drop' => $trip->routeLocationShort($trip->drop_location),
        ];
    }

    private function interpolatePoints(float $startLat, float $startLng, float $endLat, float $endLng, int $steps = 40): array
    {
        $points = [];

        for ($i = 0; $i <= $steps; $i++) {
            $t = $steps === 0 ? 0 : $i / $steps;
            $points[] = [
                'lat' => round($startLat + ($endLat - $startLat) * $t, 6),
                'lng' => round($startLng + ($endLng - $startLng) * $t, 6),
            ];
        }

        return $points;
    }

    private function estimateDistanceKm(array $points): float
    {
        if (count($points) < 2) {
            return 0;
        }

        $distance = 0;

        for ($i = 1; $i < count($points); $i++) {
            $distance += $this->haversineKm(
                (float) $points[$i - 1]['lat'],
                (float) $points[$i - 1]['lng'],
                (float) $points[$i]['lat'],
                (float) $points[$i]['lng']
            );
        }

        return round($distance, 2);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latFrom = deg2rad($lat1);
        $latTo = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
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
