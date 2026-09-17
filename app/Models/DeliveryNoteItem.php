<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class DeliveryNoteItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'delivery_note_id', 'sale_item_id', 'product_id',
        'description', 'quantity', 'size', 'color',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
