<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class HrAttendance extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;

    protected $table = 'hr_attendances';

    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LATE = 'late';
    public const STATUS_LEAVE = 'leave';
    public const STATUS_HALF_DAY = 'half_day';

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'user_id',
        'attendance_date',
        'clock_in',
        'clock_out',
        'status',
        'hours_worked',
        'notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'hours_worked' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
