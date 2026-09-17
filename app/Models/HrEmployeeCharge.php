<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployeeCharge extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    protected $table = 'hr_employee_charges';

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'charge_id',
        'user_id',
        'amount',
        'month_year',
        'recorded_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'recorded_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function charge()
    {
        return $this->belongsTo(HrCharge::class, 'charge_id');
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
