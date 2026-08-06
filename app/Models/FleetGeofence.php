<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetGeofence extends Model
{
    protected $fillable = [
        'name',
        'description',
        'location_label',
        'shape_type',
        'geometry',
        'center_lat',
        'center_lng',
        'created_by',
        'notify_sms',
        'notify_email',
        'assigned_vehicle_ids',
    ];

    protected $casts = [
        'geometry' => 'array',
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
        'notify_sms' => 'boolean',
        'notify_email' => 'boolean',
        'assigned_vehicle_ids' => 'array',
    ];

    public function displayName(): string
    {
        return $this->name ?: $this->location_label;
    }
}
