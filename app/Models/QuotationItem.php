<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'quotation_id', 'product_id', 'product_variant_id',
        'description', 'size', 'color', 'quantity', 'unit_price',
        'discount_amount', 'tax_rate', 'tax_amount', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'product_variant_id' => 'integer',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
