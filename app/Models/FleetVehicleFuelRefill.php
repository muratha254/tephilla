<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetVehicleFuelRefill extends Model
{
    protected $fillable = [
        'fleet_vehicle_id',
        'fleet_driver_id',
        'fuel_type',
        'source',
        'fleet_fuel_vendor_id',
        'fleet_stock_item_id',
        'refill_date',
        'odometer',
        'liters',
        'cost',
        'payment_method',
        'reference_no',
        'notes',
        'receipt_path',
    ];

    protected $casts = [
        'refill_date' => 'date',
        'odometer' => 'integer',
        'liters' => 'decimal:2',
        'cost' => 'decimal:2',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(FleetDriver::class, 'fleet_driver_id');
    }

    public function fuelVendor()
    {
        return $this->belongsTo(FleetFuelVendor::class, 'fleet_fuel_vendor_id');
    }

    public function stockItem()
    {
        return $this->belongsTo(FleetStockItem::class, 'fleet_stock_item_id');
    }

    public function formattedDate(): string
    {
        return $this->refill_date ? $this->refill_date->format('d M Y') : '-';
    }

    public function badgeDate(): string
    {
        return $this->refill_date ? $this->refill_date->format('Y-m-d') : '-';
    }

    public function formattedCost(): string
    {
        if ($this->cost === null) {
            return '-';
        }

        return format_kes($this->cost, 0);
    }

    public function formattedLiters(): string
    {
        return number_format((float) $this->liters, 0);
    }

    public function driverLabel(): string
    {
        return optional($this->driver)->name ?: '-';
    }
}
