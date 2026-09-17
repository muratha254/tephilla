<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id', 'branch_id', 'supplier_id', 'user_id', 'number',
        'reference_no', 'cu_number', 'order_date', 'expected_date', 'due_date',
        'status', 'subtotal', 'discount_amount', 'tax_amount', 'round_off',
        'expense_amount', 'total', 'paid_amount', 'notes', 'attachment_path',
        'expenses_json',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'round_off' => 'decimal:2',
        'expense_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'expenses_json' => 'array',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function isEditable(): bool
    {
        if (in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_CANCELLED], true)) {
            return false;
        }

        if ($this->relationLoaded('items')) {
            return $this->items->every(function (PurchaseOrderItem $item) {
                return (float) $item->quantity_received <= 0.0001;
            });
        }

        return ! $this->items()->where('quantity_received', '>', 0)->exists();
    }

    public function paymentStatus(): string
    {
        $total = (float) $this->total;
        $paid = (float) $this->paid_amount;

        if ($paid <= 0) {
            return 'unpaid';
        }

        if ($paid + 0.009 >= $total) {
            return 'paid';
        }

        return 'partial';
    }

    public function paymentStatusLabel(): string
    {
        return [
            'paid' => 'Paid',
            'partial' => 'Partial',
            'unpaid' => 'Unpaid',
        ][$this->paymentStatus()] ?? 'Unpaid';
    }
}
