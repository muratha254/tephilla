<?php

namespace App\Http\Controllers;

use App\Models\FleetVehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FleetLiveTrackingController extends Controller
{
    public function index()
    {
        $payload = $this->buildPayload();

        return view('fleet.tracking.live', array_merge($this->sharedViewData(), [
            'activeMenu' => 'tracking-live',
            'openMenu' => 'tracking',
            'stats' => $payload['stats'],
            'vehicles' => $payload['all_vehicles'],
        ]));
    }

    public function feed(Request $request)
    {
        $vehicleId = $request->query('vehicle_id');

        return response()->json(array_merge([
            'success' => true,
            'refreshed_at' => now()->toIso8601String(),
        ], $this->buildPayload($vehicleId ? (int) $vehicleId : null)));
    }

    private function buildPayload(?int $vehicleFilterId = null): array
    {
        $allVehicles = FleetVehicle::query()
            ->with(['position.trip', 'position.trip.driver'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->orderBy('registration_number')
            ->get()
            ->map(fn (FleetVehicle $vehicle) => $this->vehiclePayload($vehicle))
            ->filter(fn (array $vehicle) => $vehicle['lat'] !== null && $vehicle['lng'] !== null)
            ->values();

        $stats = [
            'total' => $allVehicles->count(),
            'moving' => $allVehicles->where('status', 'moving')->count(),
            'idle' => $allVehicles->where('status', 'idle')->count(),
            'offline' => $allVehicles->where('status', 'offline')->count(),
        ];

        $vehicles = $vehicleFilterId
            ? $allVehicles->where('id', $vehicleFilterId)->values()
            : $allVehicles;

        return [
            'stats' => $stats,
            'vehicles' => $vehicles->values()->all(),
            'all_vehicles' => $allVehicles->values()->all(),
        ];
    }

    private function vehiclePayload(FleetVehicle $vehicle): array
    {
        $position = $vehicle->position;
        $trip = $position?->trip;

        if (! $trip) {
            $trip = $vehicle->trips()
                ->with('driver')
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();
        } elseif (! $trip->relationLoaded('driver')) {
            $trip->load('driver');
        }

        $pickup = $trip ? $trip->routeLocationShort($trip->pickup_location) : '-';
        $drop = $trip ? $trip->routeLocationShort($trip->drop_location) : '-';
        $driverName = $trip?->driver?->name ?: ($vehicle->driver ?: 'Unassigned');

        return [
            'id' => $vehicle->id,
            'name' => $vehicle->displayName(),
            'registration' => $vehicle->registration_number,
            'type' => $vehicle->type,
            'image_url' => $vehicle->image_path ? asset('storage/' . $vehicle->image_path) : null,
            'status' => strtolower((string) ($position?->tracking_status ?: 'idle')),
            'status_label' => $position ? $position->statusLabel() : 'Idle',
            'status_class' => $position ? $position->statusClass() : 'is-idle',
            'speed' => $position ? (float) $position->speed_kmh : 0,
            'speed_label' => number_format($position ? (float) $position->speed_kmh : 0, 0) . ' km/h',
            'last_seen' => $this->formatLastSeen($position?->last_seen_at),
            'lat' => $position ? (float) $position->latitude : null,
            'lng' => $position ? (float) $position->longitude : null,
            'trip_code' => $trip ? $trip->displayTripCode() : '-',
            'trip_route' => ($pickup !== '-' && $drop !== '-') ? ($pickup . ' -> ' . $drop) : '-',
            'trip_pickup' => $pickup,
            'trip_drop' => $drop,
            'driver' => $driverName,
        ];
    }

    private function formatLastSeen(?Carbon $timestamp): string
    {
        if (! $timestamp) {
            return '-';
        }

        return $timestamp->diffForHumans(now(), Carbon::DIFF_RELATIVE_TO_NOW, true);
    }

    private function sharedViewData(): array
    {
        return [
            'companyName' => 'One Translines Pvt Ltd',
            'notificationCount' => 11,
        ];
    }
}
