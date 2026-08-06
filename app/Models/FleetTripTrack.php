<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetTripTrack extends Model
{
    protected $fillable = [
        'fleet_trip_id',
        'route_points',
        'distance_km',
        'duration_minutes',
    ];

    protected $casts = [
        'route_points' => 'array',
        'distance_km' => 'decimal:2',
        'duration_minutes' => 'integer',
    ];

    public function trip()
    {
        return $this->belongsTo(FleetTrip::class, 'fleet_trip_id');
    }

    public function formattedDuration(): string
    {
        $minutes = max(0, (int) $this->duration_minutes);

        if ($minutes === 0) {
            return '0m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours > 0 && $remaining > 0) {
            return $hours . 'h ' . $remaining . 'm';
        }

        if ($hours > 0) {
            return $hours . 'h';
        }

        return $remaining . 'm';
    }
}
