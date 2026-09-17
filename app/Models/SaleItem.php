<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'sale_id', 'product_id', 'product_variant_id',
        'name', 'sku', 'size', 'color', 'quantity', 'unit_price', 'cost_price',
        'discount_amount', 'tax_rate', 'tax_amount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:4',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'product_variant_id' => 'integer',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function returnItems()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
