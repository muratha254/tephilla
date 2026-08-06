<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvanceSalaryPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'payroll_id',
        'amount',
        'payment_date',
        'payment_method',
        'status',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
