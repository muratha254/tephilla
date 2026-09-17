<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrLeaveAssignment extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'hr_leave_assignments';

    protected $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'year',
        'days_assigned', 'days_used', 'user_id',
    ];

    protected $casts = [
        'days_assigned' => 'decimal:2',
        'days_used' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(HrLeaveType::class, 'leave_type_id');
    }

    public function remainingDays(): float
    {
        return max(0, (float) $this->days_assigned - (float) $this->days_used);
    }
}
