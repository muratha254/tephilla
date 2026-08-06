<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceived extends Model
{
    use HasFactory;

    protected $table = 'goods_received';

    protected $fillable = [
        'batch_id',
        'reference_number',
        'received_date',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'received_date' => 'date',
    ];

    public function batch()
    {
        return $this->belongsTo(PurchaseOrderBatch::class, 'batch_id');
    }

    public function items()
    {
        return $this->hasMany(GoodsReceivedItem::class, 'goods_received_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

