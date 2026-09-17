<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    protected $fillable = [
        'bom_id', 'product_id', 'unit_price', 'quantity', 'sub_total', 'description',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:4',
        'sub_total' => 'decimal:2',
    ];

    public function bom()
    {
        return $this->belongsTo(Bom::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
