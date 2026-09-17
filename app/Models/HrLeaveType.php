<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrLeaveType extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'hr_leave_types';

    protected $fillable = [
        'company_id', 'name', 'days_allowed', 'is_paid', 'description', 'is_active',
    ];

    protected $casts = [
        'days_allowed' => 'decimal:2',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
    ];
}
