<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetVehicle extends Model
{
    protected $fillable = [
        'registration_number',
        'name',
        'type',
        'color',
        'status',
        'fuel_type',
        'fuel_efficiency',
        'opening_fuel',
        'current_fuel',
        'fuel_capacity',
        'vehicle_group',
        'driver',
        'model',
        'manufacturer',
        'image_path',
        'chassis_number',
        'engine_number',
        'registration_date',
        'registration_expiry',
        'insurance_provider',
        'insurance_policy_no',
        'insurance_expiry',
        'fitness_certificate_no',
        'fitness_expiry',
        'owner_type',
        'purchase_date',
        'purchase_price',
        'lease_start',
        'lease_end',
        'gps_device_id',
        'gps_imei',
        'gps_sim_number',
        'gps_enabled',
    ];

    protected $casts = [
        'fuel_efficiency' => 'decimal:2',
        'opening_fuel' => 'decimal:2',
        'current_fuel' => 'decimal:2',
        'fuel_capacity' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'registration_date' => 'date',
        'registration_expiry' => 'date',
        'insurance_expiry' => 'date',
        'fitness_expiry' => 'date',
        'purchase_date' => 'date',
        'lease_start' => 'date',
        'lease_end' => 'date',
        'gps_enabled' => 'boolean',
    ];

    public function displayName(): string
    {
        return $this->name ?: ('Fleet ' . strtoupper($this->type) . ' ' . $this->id);
    }

    public function isLowFuel(): bool
    {
        if ($this->current_fuel === null || $this->fuel_capacity <= 0) {
            return false;
        }

        return $this->current_fuel <= ($this->fuel_capacity * 0.15);
    }

    public function trips()
    {
        return $this->hasMany(FleetTrip::class, 'fleet_vehicle_id');
    }

    public function position()
    {
        return $this->hasOne(FleetVehiclePosition::class, 'fleet_vehicle_id');
    }

    public function fuelRefills()
    {
        return $this->hasMany(FleetVehicleFuelRefill::class, 'fleet_vehicle_id')
            ->orderByDesc('refill_date')
            ->orderByDesc('id');
    }

    public function maintenances()
    {
        return $this->hasMany(FleetMaintenance::class, 'fleet_vehicle_id')
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    public function fuelBalance(): float
    {
        return round((float) ($this->current_fuel ?? 0), 2);
    }

    public function fuelBalancePercent(): int
    {
        if (! $this->fuel_capacity || $this->fuel_capacity <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->fuelBalance() / (float) $this->fuel_capacity) * 100));
    }

    public function openingFuelBalance(): float
    {
        return round((float) ($this->opening_fuel ?? 0), 2);
    }

    public function refueledAmount(): float
    {
        if ($this->relationLoaded('fuelRefills')) {
            return round((float) $this->fuelRefills->sum('liters'), 2);
        }

        return round((float) $this->fuelRefills()->sum('liters'), 2);
    }

    public function availableFuelCapacity(): float
    {
        $capacity = (float) ($this->fuel_capacity ?? 0);
        $current = $this->fuelBalance();

        return max(0, round($capacity - $current, 2));
    }

    public function consumedFuel(): float
    {
        return max(0, round($this->openingFuelBalance() + $this->refueledAmount() - $this->fuelBalance(), 2));
    }

    public function typeBadge(): string
    {
        return strtoupper((string) $this->type);
    }

    public function ownerBadgeClass(): string
    {
        $owner = strtolower((string) $this->owner_type);

        if ($owner === 'vendor') {
            return 'fleet-vdetail-owner-vendor';
        }

        if ($owner === 'leased') {
            return 'fleet-vdetail-owner-leased';
        }

        return 'fleet-vdetail-owner-owned';
    }

    public function statusBadgeClass(): string
    {
        $status = strtolower((string) $this->status);

        if ($status === 'inactive') {
            return 'fleet-vdetail-status-inactive';
        }

        if ($status === 'maintenance') {
            return 'fleet-vdetail-status-maintenance';
        }

        return 'fleet-vdetail-status-active';
    }

    public function documentItems(): array
    {
        $items = [];

        if ($this->registration_expiry) {
            $items[] = [
                'label' => 'Registration',
                'reference' => $this->registration_number,
                'expiry' => $this->registration_expiry->format('d M Y'),
            ];
        }

        if ($this->insurance_policy_no || $this->insurance_expiry) {
            $items[] = [
                'label' => 'Insurance',
                'reference' => $this->insurance_policy_no ?: $this->insurance_provider,
                'expiry' => optional($this->insurance_expiry)->format('d M Y') ?: '-',
            ];
        }

        if ($this->fitness_certificate_no || $this->fitness_expiry) {
            $items[] = [
                'label' => 'Fitness Certificate',
                'reference' => $this->fitness_certificate_no ?: '-',
                'expiry' => optional($this->fitness_expiry)->format('d M Y') ?: '-',
            ];
        }

        return $items;
    }
}
