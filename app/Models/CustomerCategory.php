<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CustomerCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'discount_percent', 'description', 'is_active',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'category_id');
    }
}
