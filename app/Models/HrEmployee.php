<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployee extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    protected $table = 'hr_employees';

    protected $fillable = [
        'company_id', 'branch_id', 'department_id', 'designation_id', 'category_id', 'role_id',
        'employee_code', 'payroll_number', 'first_name', 'middle_name', 'last_name', 'name',
        'joining_date', 'email', 'phone', 'alt_phone', 'national_id', 'kra_pin', 'shif_no', 'nssf_no',
        'gender', 'marital_status', 'payment_period', 'basic_salary', 'gross_salary',
        'advance_salary_limit', 'leave_counts', 'county', 'postcode', 'home_address',
        'bank_account_name', 'bank_account_number', 'bank_name', 'bank_branch',
        'apply_paye', 'apply_shif', 'apply_nssf', 'apply_housing_levy', 'status',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'basic_salary' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'advance_salary_limit' => 'decimal:2',
        'apply_paye' => 'boolean',
        'apply_shif' => 'boolean',
        'apply_nssf' => 'boolean',
        'apply_housing_levy' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(HrDesignation::class, 'designation_id');
    }

    public function category()
    {
        return $this->belongsTo(HrEmployeeCategory::class, 'category_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function displayCode(): string
    {
        if ($this->employee_code) {
            return $this->employee_code;
        }

        return 'E' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function fullName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }

    public static function composeName(?string $first, ?string $middle, ?string $last): string
    {
        return trim(implode(' ', array_filter([$first, $middle, $last])));
    }
}
