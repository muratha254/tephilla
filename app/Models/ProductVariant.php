<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'product_id',
        'sku',
        'barcode',
        'size',
        'color',
        'purchase_price',
        'selling_price',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function displayName(): string
    {
        $parts = array_filter([$this->size, $this->color]);

        return $parts ? $this->product->name . ' (' . implode(' / ', $parts) . ')' : $this->product->name;
    }
}
