<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceivedItem extends Model
{
    use HasFactory;

    protected $table = 'goods_received_items';

    protected $fillable = [
        'goods_received_id',
        'batch_item_id',
        'produk_id',
        'quantity',
        'unit_price',
    ];

    public function receipt()
    {
        return $this->belongsTo(GoodsReceived::class, 'goods_received_id');
    }

    public function batchItem()
    {
        return $this->belongsTo(PurchaseOrderBatchItem::class, 'batch_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Produk::class, 'produk_id', 'id_produk');
    }
}

