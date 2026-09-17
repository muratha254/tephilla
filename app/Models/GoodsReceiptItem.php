<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'goods_receipt_id', 'purchase_order_item_id', 'product_id',
        'product_variant_id', 'quantity_received', 'unit_cost',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'product_variant_id' => 'integer',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
