<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ProductBranchStock extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;

    protected $table = 'product_branch_stock';

    protected $fillable = [
        'company_id',
        'branch_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'average_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'average_cost' => 'decimal:4',
        'product_variant_id' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
