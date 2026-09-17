<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrCharge extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'hr_charges';

    protected $fillable = [
        'company_id',
        'category',
        'taxable',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function employeeCharges()
    {
        return $this->hasMany(HrEmployeeCharge::class, 'charge_id');
    }
}
