<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class HrPayrollItem extends Model
{
    use BelongsToCompany;

    protected $table = 'hr_payroll_items';

    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'employee_id',
        'basic_salary',
        'allowances',
        'deductions',
        'gross_pay',
        'net_pay',
        'breakdown_json',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'deductions' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'breakdown_json' => 'array',
    ];

    public function payrollRun()
    {
        return $this->belongsTo(HrPayrollRun::class, 'payroll_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function payments()
    {
        return $this->hasMany(HrSalaryPayment::class, 'payroll_item_id');
    }
}
