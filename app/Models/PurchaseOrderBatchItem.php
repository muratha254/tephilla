<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'produk_id',
        'supplier_id',
        'quantity',
        'received_quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'received_quantity' => 'integer',
    ];

    public function batch()
    {
        return $this->belongsTo(PurchaseOrderBatch::class, 'batch_id');
    }

    public function product()
    {
        return $this->belongsTo(Produk::class, 'produk_id', 'id_produk');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id_supplier');
    }

    public function getRemainingAttribute(): int
    {
        return max(0, (int) $this->quantity - (int) $this->received_quantity);
    }
}

