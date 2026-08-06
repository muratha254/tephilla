<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetMaintenance extends Model
{
    protected $fillable = [
        'fleet_vehicle_id',
        'fleet_vehicle_vendor_id',
        'status',
        'start_date',
        'end_date',
        'service_details',
        'total_cost',
        'mechanic',
        'priority',
        'checklist',
        'receipt_path',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_cost' => 'decimal:2',
        'checklist' => 'array',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function vendor()
    {
        return $this->belongsTo(FleetVehicleVendor::class, 'fleet_vehicle_vendor_id');
    }

    public function statusLabel(): string
    {
        return ucfirst(strtolower((string) $this->status));
    }

    public function statusDisplayLabel(): string
    {
        if (strtolower((string) $this->status) === 'ongoing') {
            return 'In Progress';
        }

        return $this->statusLabel();
    }

    public function cardSummary(): string
    {
        $details = trim((string) $this->service_details);

        if (strlen($details) <= 90) {
            return $details;
        }

        return substr($details, 0, 87) . '...';
    }

    public function formattedDateRange(): string
    {
        $start = $this->start_date ? $this->start_date->format('d M Y') : '-';
        $end = $this->end_date ? $this->end_date->format('d M Y') : '-';

        return $start . ' - ' . $end;
    }

    public function checklistProgress(): array
    {
        $items = collect($this->checklist ?? []);
        $total = $items->count();
        $done = $items->where('done', true)->count();

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }
}
