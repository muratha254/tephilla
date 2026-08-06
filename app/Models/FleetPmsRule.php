<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FleetPmsRule extends Model
{
    protected $fillable = [
        'fleet_vehicle_id',
        'service_name',
        'interval_km',
        'interval_days',
        'last_service_date',
    ];

    protected $casts = [
        'interval_km' => 'integer',
        'interval_days' => 'integer',
        'last_service_date' => 'date',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function formattedInterval(): string
    {
        $parts = [];

        if ($this->interval_km) {
            $parts[] = number_format($this->interval_km) . ' KM';
        }

        if ($this->interval_days) {
            $parts[] = $this->interval_days . ' Days';
        }

        return $parts ? implode(', ', $parts) : '-';
    }

    public function formattedLastService(): string
    {
        return $this->last_service_date ? $this->last_service_date->format('d M Y') : '-';
    }

    public function isDue(): bool
    {
        if ($this->interval_days) {
            if (! $this->last_service_date) {
                return true;
            }

            return $this->last_service_date->copy()->addDays($this->interval_days)->lte(now()->startOfDay());
        }

        return false;
    }

    public function dueLabel(): string
    {
        return $this->isDue() ? 'Due' : 'OK';
    }

    public function nextDueDate(): ?Carbon
    {
        if (! $this->interval_days || ! $this->last_service_date) {
            return null;
        }

        return $this->last_service_date->copy()->addDays($this->interval_days);
    }
}
