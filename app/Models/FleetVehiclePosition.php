<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetVehiclePosition extends Model
{
    protected $fillable = [
        'fleet_vehicle_id',
        'fleet_trip_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'tracking_status',
        'last_seen_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'speed_kmh' => 'decimal:2',
        'last_seen_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function trip()
    {
        return $this->belongsTo(FleetTrip::class, 'fleet_trip_id');
    }

    public function statusLabel(): string
    {
        return match (strtolower((string) $this->tracking_status)) {
            'moving' => 'Moving',
            'offline' => 'Offline',
            default => 'Idle',
        };
    }

    public function statusClass(): string
    {
        return match (strtolower((string) $this->tracking_status)) {
            'moving' => 'is-moving',
            'offline' => 'is-offline',
            default => 'is-idle',
        };
    }
}
