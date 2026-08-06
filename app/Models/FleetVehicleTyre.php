<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetVehicleTyre extends Model
{
    protected $fillable = [
        'fleet_vehicle_id',
        'position',
        'serial_number',
        'brand_model',
        'install_date',
        'install_odometer',
        'cost',
    ];

    protected $casts = [
        'install_date' => 'date',
        'install_odometer' => 'integer',
        'cost' => 'decimal:2',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function formattedInstallDate(): string
    {
        return $this->install_date ? $this->install_date->format('d M Y') : '-';
    }

    public function formattedOdometer(): string
    {
        if ($this->install_odometer === null) {
            return '-';
        }

        return number_format($this->install_odometer) . ' km';
    }

    public function formattedCost(): string
    {
        if ($this->cost === null) {
            return '-';
        }

        return format_kes($this->cost);
    }
}
