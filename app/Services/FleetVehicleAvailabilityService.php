<?php

namespace App\Services;

use App\Models\FleetMaintenance;
use App\Models\FleetTrip;
use App\Models\FleetVehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FleetVehicleAvailabilityService
{
    public function blockingTripStatuses(): array
    {
        return ['Booked', 'pending', 'Pending', 'ongoing', 'Ongoing'];
    }

    public function isVehicleAvailableOnDate(FleetVehicle $vehicle, Carbon $date, ?int $ignoreTripId = null): bool
    {
        if (strtolower((string) $vehicle->status) !== 'active') {
            return false;
        }

        if ($this->hasBlockingMaintenanceOnDate($vehicle->id, $date)) {
            return false;
        }

        return ! $this->hasBlockingTripOnDate($vehicle->id, $date, $ignoreTripId);
    }

    public function isVehicleAvailableForRange(
        FleetVehicle $vehicle,
        Carbon $startDate,
        Carbon $endDate,
        ?int $ignoreTripId = null
    ): bool {
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if (! $this->isVehicleAvailableOnDate($vehicle, $cursor, $ignoreTripId)) {
                return false;
            }
            $cursor->addDay();
        }

        return true;
    }

    public function availableVehiclesForDate(Carbon $date, ?int $ignoreTripId = null): Collection
    {
        return FleetVehicle::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get()
            ->filter(fn (FleetVehicle $vehicle) => $this->isVehicleAvailableOnDate($vehicle, $date, $ignoreTripId))
            ->values();
    }

    public function dailySnapshot(Carbon $date, ?int $ignoreTripId = null): array
    {
        $activeVehicles = FleetVehicle::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $available = $activeVehicles->filter(
            fn (FleetVehicle $vehicle) => $this->isVehicleAvailableOnDate($vehicle, $date, $ignoreTripId)
        )->values();

        $booked = $activeVehicles->reject(
            fn (FleetVehicle $vehicle) => $this->isVehicleAvailableOnDate($vehicle, $date, $ignoreTripId)
        )->values();

        return [
            'total' => $activeVehicles->count(),
            'available_count' => $available->count(),
            'booked_count' => $booked->count(),
            'available_vehicles' => $available->map(fn (FleetVehicle $vehicle) => $this->vehicleSummary($vehicle))->all(),
            'booked_vehicles' => $booked->map(fn (FleetVehicle $vehicle) => $this->vehicleSummary($vehicle))->all(),
        ];
    }

    public function currentAvailability(FleetVehicle $vehicle): array
    {
        $today = now()->startOfDay();
        $status = strtolower((string) $vehicle->status);

        if ($status === 'inactive') {
            return ['label' => 'Inactive', 'class' => 'is-inactive', 'hint' => 'Vehicle is inactive'];
        }

        if ($status === 'maintenance') {
            return ['label' => 'Maintenance', 'class' => 'is-maintenance', 'hint' => 'Marked under maintenance'];
        }

        $ongoingTrip = $this->blockingTripsQuery($vehicle->id)
            ->whereIn('status', ['ongoing', 'Ongoing'])
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereDate('end_date', '>=', $today)
                    ->orWhereNull('end_date');
            })
            ->first();

        if ($ongoingTrip) {
            return [
                'label' => 'In Trip',
                'class' => 'is-booked',
                'hint' => 'Currently on trip ' . $ongoingTrip->displayTripCode(),
            ];
        }

        if ($this->hasBlockingMaintenanceOnDate($vehicle->id, $today)) {
            return ['label' => 'Maintenance', 'class' => 'is-maintenance', 'hint' => 'Scheduled maintenance today'];
        }

        $bookedTrip = $this->blockingTripsQuery($vehicle->id)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereDate('end_date', '>=', $today)
                    ->orWhere(function ($inner) use ($today) {
                        $inner->whereNull('end_date')->whereDate('start_date', $today);
                    });
            })
            ->first();

        if ($bookedTrip) {
            return [
                'label' => 'Booked',
                'class' => 'is-booked',
                'hint' => 'Booked for trip ' . $bookedTrip->displayTripCode(),
            ];
        }

        return ['label' => 'Available', 'class' => 'is-available', 'hint' => 'Ready for next booking'];
    }

    public function assertVehicleAvailableForTrip(
        int $vehicleId,
        string $startDate,
        ?string $endDate = null,
        ?int $ignoreTripId = null
    ): void {
        $vehicle = FleetVehicle::query()->findOrFail($vehicleId);
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate ?: $startDate)->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        if (! $this->isVehicleAvailableForRange($vehicle, $start, $end, $ignoreTripId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fleet_vehicle_id' => 'This vehicle is not available for the selected date range.',
            ]);
        }
    }

    private function hasBlockingTripOnDate(int $vehicleId, Carbon $date, ?int $ignoreTripId = null): bool
    {
        return $this->blockingTripsQuery($vehicleId, $ignoreTripId)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereDate('end_date', '>=', $date->toDateString())
                    ->orWhere(function ($inner) use ($date) {
                        $inner->whereNull('end_date')
                            ->whereDate('start_date', $date->toDateString());
                    });
            })
            ->exists();
    }

    private function hasBlockingMaintenanceOnDate(int $vehicleId, Carbon $date): bool
    {
        return FleetMaintenance::query()
            ->where('fleet_vehicle_id', $vehicleId)
            ->whereIn('status', ['Planned', 'Ongoing'])
            ->whereDate('start_date', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereDate('end_date', '>=', $date->toDateString())
                    ->orWhereNull('end_date');
            })
            ->exists();
    }

    private function blockingTripsQuery(int $vehicleId, ?int $ignoreTripId = null)
    {
        return FleetTrip::query()
            ->where('fleet_vehicle_id', $vehicleId)
            ->whereIn('status', $this->blockingTripStatuses())
            ->when($ignoreTripId, fn ($query) => $query->where('id', '!=', $ignoreTripId));
    }

    private function vehicleSummary(FleetVehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'name' => $vehicle->displayName(),
            'registration' => $vehicle->registration_number,
        ];
    }
}
