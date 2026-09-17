<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'code', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function brandCode(): string
    {
        if ($this->attributes['code'] ?? null) {
            return $this->attributes['code'];
        }

        return 'BR' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
