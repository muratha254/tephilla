<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const DEFAULT_TERMS = "TERMS AND CONDITIONS.\n1. The above quotation is based on the roof estimates as per drawings provided.\n2. The quotation herein is valid for 30 (Thirty) days from the date of issue.\n3. Terms of sale; 100 % payment before delivery.\n4. Prices may change without prior notice unless paid for, wholly or partially.\n5. Labour and Supply contracts are to be treated as separate items\n6. This quotation is subjected to 5 - 10 % wastage depending on the roof design";

    protected $fillable = [
        'company_id', 'branch_id', 'customer_id', 'user_id', 'number',
        'quote_date', 'valid_until', 'status', 'converted_sale_id',
        'subtotal', 'discount_amount', 'tax_amount', 'total', 'notes', 'terms',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function convertedSale()
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
    }
}
