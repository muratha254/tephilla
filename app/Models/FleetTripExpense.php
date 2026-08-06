<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetTripExpense extends Model
{
    protected $fillable = [
        'fleet_trip_id',
        'expense_date',
        'category',
        'description',
        'amount',
        'payment_method',
        'reference_no',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function trip()
    {
        return $this->belongsTo(FleetTrip::class, 'fleet_trip_id');
    }

    public function formattedDate(): string
    {
        return $this->expense_date ? $this->expense_date->format('d M Y') : '-';
    }
}
