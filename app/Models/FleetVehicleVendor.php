<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetVehicleVendor extends Model
{
    protected $fillable = [
        'company',
        'contact_person',
        'mobile',
        'contract_date',
        'contract_doc',
        'address',
        'is_active',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'is_active' => 'boolean',
    ];
}
