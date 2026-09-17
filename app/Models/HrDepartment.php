<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrDepartment extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'hr_departments';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function displayCode(): string
    {
        return $this->code !== null && $this->code !== ''
            ? (string) $this->code
            : (string) $this->id;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function designations()
    {
        return $this->hasMany(HrDesignation::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(HrEmployee::class, 'department_id');
    }
}
