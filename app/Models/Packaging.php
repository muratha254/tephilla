<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Packaging extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'product_id', 'user_id',
        'packaging_date', 'qty_packaged', 'grand_total',
    ];

    protected $casts = [
        'packaging_date' => 'date',
        'qty_packaged' => 'decimal:4',
        'grand_total' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
    {
        return $this->hasMany(PackagingItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
