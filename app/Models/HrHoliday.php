<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrHoliday extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'hr_holidays';

    protected $fillable = [
        'company_id', 'name', 'holiday_date', 'description', 'is_active',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_active' => 'boolean',
    ];
}
