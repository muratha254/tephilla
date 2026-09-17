<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'short_name', 'description', 'base_unit_id', 'multiplier', 'is_active',
    ];

    protected $casts = [
        'multiplier' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function baseUnit()
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
