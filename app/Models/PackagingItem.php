<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackagingItem extends Model
{
    protected $fillable = [
        'packaging_id', 'product_id', 'unit_control', 'qty_to_package', 'stock_packaged',
    ];

    protected $casts = [
        'qty_to_package' => 'decimal:4',
        'stock_packaged' => 'decimal:4',
    ];

    public function packaging()
    {
        return $this->belongsTo(Packaging::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
