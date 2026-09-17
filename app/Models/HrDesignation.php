<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrDesignation extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'hr_designations';

    protected $fillable = [
        'company_id',
        'department_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(HrEmployee::class, 'designation_id');
    }
}
