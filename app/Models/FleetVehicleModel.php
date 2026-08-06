<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetVehicleModel extends Model
{
    protected $fillable = [
        'name',
        'fleet_vehicle_manufacturer_id',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(FleetVehicleManufacturer::class, 'fleet_vehicle_manufacturer_id');
    }
}
