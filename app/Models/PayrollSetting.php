<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'paye_band1_min', 'paye_band1_max', 'paye_band1_rate',
        'paye_band2_min', 'paye_band2_max', 'paye_band2_rate',
        'paye_band3_min', 'paye_band3_rate',
        'personal_relief',
        'nssf_tier1_limit', 'nssf_tier2_limit', 'nssf_rate',
        'nhif_bands',
    ];

    protected $casts = [
        'paye_band1_min' => 'decimal:2',
        'paye_band1_max' => 'decimal:2',
        'paye_band1_rate' => 'decimal:2',
        'paye_band2_min' => 'decimal:2',
        'paye_band2_max' => 'decimal:2',
        'paye_band2_rate' => 'decimal:2',
        'paye_band3_min' => 'decimal:2',
        'paye_band3_rate' => 'decimal:2',
        'personal_relief' => 'decimal:2',
        'nssf_tier1_limit' => 'decimal:2',
        'nssf_tier2_limit' => 'decimal:2',
        'nssf_rate' => 'decimal:2',
        'nhif_bands' => 'array',
    ];

    public static function getSettings()
    {
        return static::first() ?? static::create([]);
    }
}
