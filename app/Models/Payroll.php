<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'payroll_date',
        'type',
        'status',
        'gross_salary',
        'employer_pension',
        'employee_pension',
        'other_additions',
        'other_deductions',
        'advance_salary',
        'paye',
        'nhif',
        'nssf_employee',
        'nssf_employer',
        'net_salary',
        'employer_contributions',
        'notes',
    ];

    protected $casts = [
        'payroll_date' => 'date',
        'gross_salary' => 'decimal:2',
        'employer_pension' => 'decimal:2',
        'employee_pension' => 'decimal:2',
        'other_additions' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'advance_salary' => 'decimal:2',
        'paye' => 'decimal:2',
        'nhif' => 'decimal:2',
        'nssf_employee' => 'decimal:2',
        'nssf_employer' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'employer_contributions' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function advanceSalaryPayments()
    {
        return $this->hasMany(AdvanceSalaryPayment::class);
    }
}
