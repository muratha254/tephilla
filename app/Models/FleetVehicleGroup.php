<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetVehicleGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    public function vehicleCount(): int
    {
        return FleetVehicle::query()
            ->where('vehicle_group', $this->name)
            ->count();
    }
}
