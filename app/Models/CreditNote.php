<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'company_id', 'branch_id', 'sale_id', 'customer_id', 'user_id',
        'number', 'credit_date', 'status', 'reason', 'notes',
        'subtotal', 'discount_amount', 'tax_amount', 'total',
        'restore_stock', 'stock_restored', 'accounting_posted', 'loyalty_adjusted',
        'voided_at', 'voided_by', 'void_reason',
    ];

    protected $casts = [
        'credit_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'restore_stock' => 'boolean',
        'stock_restored' => 'boolean',
        'accounting_posted' => 'boolean',
        'loyalty_adjusted' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

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
        return $this->hasMany(CreditNoteItem::class);
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isPosted(): bool
    {
        return in_array($this->status, [self::STATUS_POSTED, self::STATUS_COMPLETED], true);
    }
}
