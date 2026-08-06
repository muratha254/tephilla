<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetFuelVendor extends Model
{
    protected $fillable = [
        'name',
    ];

    public function fuelRefills()
    {
        return $this->hasMany(FleetVehicleFuelRefill::class, 'fleet_fuel_vendor_id');
    }
}
