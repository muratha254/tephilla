<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetStockMovement extends Model
{
    protected $fillable = [
        'fleet_stock_item_id',
        'type',
        'quantity_change',
        'quantity_after',
        'unit_price',
        'purchased_from',
        'payment_status',
        'description',
        'notes',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'quantity_after' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(FleetStockItem::class, 'fleet_stock_item_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'purchase' => 'Purchase',
            'adjustment' => 'Adjustment',
            'usage' => 'Usage',
            default => ucfirst($this->type),
        };
    }

    public function formattedDate(): string
    {
        return $this->created_at ? $this->created_at->format('d M Y, H:i') : '-';
    }
}
