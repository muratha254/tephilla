<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrSalaryPayment extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    protected $table = 'hr_salary_payments';

    public const STATUS_PAID = 'paid';
    public const STATUS_PENDING = 'pending';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'payroll_run_id',
        'payroll_item_id',
        'user_id',
        'number',
        'payment_date',
        'method',
        'amount',
        'reference',
        'status',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function payrollRun()
    {
        return $this->belongsTo(HrPayrollRun::class, 'payroll_run_id');
    }

    public function payrollItem()
    {
        return $this->belongsTo(HrPayrollItem::class, 'payroll_item_id');
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
