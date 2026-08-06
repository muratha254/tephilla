<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'employer_number',
        'id_number',
        'kra_pin',
        'nssf_number',
        'email',
        'phone',
        'address',
        'date_of_birth',
        'date_of_employment',
        'status',
        'gross_salary',
        'advance_salary',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_of_employment' => 'date',
        'gross_salary' => 'decimal:2',
        'advance_salary' => 'decimal:2',
    ];

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function advanceSalaryPayments()
    {
        return $this->hasMany(AdvanceSalaryPayment::class);
    }

    public function isActive()
    {
        return $this->status === 'active';
    }
}
