<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetReminder extends Model
{
    protected $fillable = [
        'fleet_vehicle_id',
        'due_date',
        'services',
        'notes',
        'is_completed',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_completed' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function badgeDate(): string
    {
        return $this->due_date ? $this->due_date->format('Y-m-d') : '-';
    }

    public function servicesSummary(int $limit = 60): string
    {
        $text = trim((string) $this->services);

        if ($text === '') {
            return '-';
        }

        return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
    }

    public function servicesList(): array
    {
        if (! $this->services) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->services))));
    }

    public function daysUntilDue(): int
    {
        if (! $this->due_date) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false);
    }

    public function statusLabel(): string
    {
        if ($this->is_completed) {
            return 'Completed';
        }

        $days = $this->daysUntilDue();

        if ($days < 0) {
            return 'Overdue';
        }

        if ($days === 0) {
            return 'Due Today';
        }

        if ($days === 1) {
            return 'In 1 days';
        }

        return 'In ' . $days . ' days';
    }

    public function statusClass(): string
    {
        if ($this->is_completed) {
            return 'is-completed';
        }

        return $this->daysUntilDue() < 0 ? 'is-overdue' : 'is-upcoming';
    }
}
