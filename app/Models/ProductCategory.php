<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'parent_id', 'name', 'code', 'description', 'show_on_pos', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'show_on_pos' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function categoryCode(): string
    {
        if ($this->attributes['code'] ?? null) {
            return $this->attributes['code'];
        }

        return 'CAT_' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
