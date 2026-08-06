<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetQuotation extends Model
{
    public const STATUSES = ['Draft', 'Sent', 'Accepted', 'Rejected', 'Converted'];

    protected $fillable = [
        'quotation_number',
        'fleet_customer_id',
        'fleet_trip_id',
        'trip_reference',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(FleetCustomer::class, 'fleet_customer_id');
    }

    public function trip()
    {
        return $this->belongsTo(FleetTrip::class, 'fleet_trip_id');
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'Sent' => 'fleet-customer-status-sent',
            'Accepted' => 'fleet-customer-status-accepted',
            'Rejected' => 'fleet-customer-status-rejected',
            'Converted' => 'fleet-customer-status-converted',
            default => 'fleet-customer-status-draft',
        };
    }

    public function tripReferenceLabel(): string
    {
        if ($this->trip) {
            return $this->trip->tripDisplayName();
        }

        return $this->trip_reference ?: '-';
    }
}
