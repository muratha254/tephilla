<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackagingSetupItem extends Model
{
    protected $fillable = [
        'packaging_setup_id', 'product_id', 'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function setup()
    {
        return $this->belongsTo(PackagingSetup::class, 'packaging_setup_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
