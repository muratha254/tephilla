<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\PurchaseOrder;
use App\Models\Produk;

class PurchaseOrderReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'produk_id',
        'received_quantity',
        'received_date',
        'notes',
    ];

    protected $casts = [
        'received_quantity' => 'integer',
        'received_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Produk::class, 'produk_id', 'id_produk');
    }
}
