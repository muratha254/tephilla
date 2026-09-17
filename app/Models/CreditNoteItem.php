<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CreditNoteItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'credit_note_id', 'sale_item_id', 'product_id', 'product_variant_id',
        'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'line_total', 'restore_stock',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'restore_stock' => 'boolean',
    ];

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
