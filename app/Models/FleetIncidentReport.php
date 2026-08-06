<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetIncidentReport extends Model
{
    protected $fillable = [
        'fleet_vehicle_id',
        'fleet_maintenance_id',
        'reported_by',
        'description',
        'incident_date',
        'status',
    ];

    protected $casts = [
        'incident_date' => 'date',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function maintenance()
    {
        return $this->belongsTo(FleetMaintenance::class, 'fleet_maintenance_id');
    }

    public function formattedDate(): string
    {
        return $this->incident_date ? $this->incident_date->format('d M Y') : '-';
    }

    public function descriptionLines(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim((string) $this->description)))
            ->filter(fn ($line) => trim($line) !== '')
            ->values()
            ->all();
    }

    public function statusBadgeClass(): string
    {
        return match (strtolower((string) $this->status)) {
            'converted' => 'fleet-incident-status-converted',
            'closed' => 'fleet-incident-status-closed',
            default => 'fleet-incident-status-open',
        };
    }

    public function canConvert(): bool
    {
        return strtolower((string) $this->status) === 'open';
    }
}
