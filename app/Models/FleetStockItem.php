<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetStockItem extends Model
{
    protected $fillable = [
        'name',
        'description',
        'quantity',
        'unit_price',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function movements()
    {
        return $this->hasMany(FleetStockMovement::class)->latest('id');
    }

    public function assetValue(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }

    public function formattedAssetValue(): string
    {
        return number_format($this->assetValue(), 2);
    }

    public function formattedUnitPrice(): string
    {
        return number_format((float) $this->unit_price, 0);
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }
}
