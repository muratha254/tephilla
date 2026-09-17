<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;

    protected $fillable = [
        'company_id',
        'product_id',
        'branch_id',
        'batch_date',
        'expiry_date',
        'cost_price',
        'retail_price',
        'wholesale_price',
        'promo_price',
        'stocked_qty',
        'balance_qty',
        'is_active',
    ];

    protected $casts = [
        'batch_date' => 'date',
        'expiry_date' => 'date',
        'cost_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'promo_price' => 'decimal:2',
        'stocked_qty' => 'decimal:4',
        'balance_qty' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
