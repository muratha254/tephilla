<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public static function defaultNames(): array
    {
        return ['Transport', 'Utilities', 'Rent', 'Office Supplies', 'Salaries', 'Other'];
    }
}
