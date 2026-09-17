<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'sale_return_id', 'sale_item_id', 'product_id',
        'product_variant_id', 'quantity', 'unit_price', 'line_total', 'condition',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'product_variant_id' => 'integer',
    ];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
