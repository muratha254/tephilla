<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    public const TYPE_POS = 'pos';
    public const TYPE_INVOICE = 'invoice';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_HELD = 'held';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_RETURNED = 'returned';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'company_id', 'branch_id', 'customer_id', 'user_id', 'quotation_id',
        'document_type', 'number', 'invoice_number', 'receipt_number',
        'sale_date', 'due_date', 'status', 'payment_status',
        'subtotal', 'discount_percent', 'discount_amount', 'tax_amount',
        'total', 'paid_amount', 'balance', 'notes',
        'held_at', 'voided_at', 'voided_by', 'void_reason',
        'void_refund', 'void_flagged',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'held_at' => 'datetime',
        'voided_at' => 'datetime',
        'void_refund' => 'boolean',
        'void_flagged' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function deliveryNotes()
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function paymentPlan()
    {
        return $this->hasOne(SalePaymentPlan::class);
    }

    public function isInvoice(): bool
    {
        return $this->document_type === self::TYPE_INVOICE || filled($this->invoice_number);
    }

    public function documentNumber(): string
    {
        return (string) ($this->invoice_number ?: $this->receipt_number ?: $this->number);
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_COMPLETED => 'Final',
            self::STATUS_VOIDED => 'Cancelled',
            self::STATUS_RETURNED => 'Returned',
            self::STATUS_HELD => 'Held',
            self::STATUS_DRAFT => 'Draft',
        ][$this->status] ?? ucfirst((string) $this->status);
    }

    public function paymentStatusLabel(): string
    {
        return [
            self::PAYMENT_PAID => 'Paid',
            self::PAYMENT_PARTIAL => 'Partial',
            self::PAYMENT_UNPAID => 'Unpaid',
        ][$this->payment_status] ?? ucfirst((string) $this->payment_status);
    }

    public function customerDisplayName(): string
    {
        $customer = $this->customer;
        if (! $customer || $customer->is_walk_in) {
            return 'WALK-IN';
        }

        return (string) $customer->name;
    }

    public function remainingBalance(): float
    {
        return round((float) $this->balance, 2);
    }
}
