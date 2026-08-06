<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'order_date',
        'reference_number',
        'notes',
        'status',
        'closed_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id_supplier');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderBatchItem::class, 'batch_id');
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceived::class, 'batch_id');
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->items->every(function ($item) {
            return (int) $item->received_quantity >= (int) $item->quantity;
        });
    }

    public static function generateNumber(): string
    {
        do {
            $sequence = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $number = sprintf('PO-%s-%s', now()->format('Ymd'), $sequence);
        } while (self::where('po_number', $number)->exists());

        return $number;
    }
}

