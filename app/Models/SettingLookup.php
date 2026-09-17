<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SettingLookup extends Model
{
    use BelongsToCompany;

    public const TYPE_SALUTATION = 'salutation';
    public const TYPE_PROGRESS = 'progress_status';
    public const TYPE_CURRENCY = 'currency';
    public const TYPE_COUNTY = 'place_county';
    public const TYPE_CITY = 'place_city';

    protected $fillable = [
        'company_id', 'type', 'name', 'code', 'symbol', 'rate', 'parent_id',
        'sort_order', 'is_active', 'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'rate' => 'decimal:6',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
