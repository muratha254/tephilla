<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    public const TYPE_DIRECT = 'direct';
    public const TYPE_BILL = 'bill';

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';

    public const VAT_EXEMPT = 'exempt';
    public const VAT_EXCLUSIVE = 'exclusive';
    public const VAT_INCLUSIVE = 'inclusive';

    protected $fillable = [
        'company_id', 'branch_id', 'expense_category_id', 'user_id', 'vendor_id',
        'entry_type', 'number', 'voucher_no', 'description', 'amount', 'paid_amount',
        'expense_date', 'payment_method', 'paying_account', 'vat_type', 'vat_amount',
        'status', 'attachment_path', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }

    public function balance(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID || $this->balance() <= 0;
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_PAID => 'Paid',
        ][$this->status] ?? ucfirst((string) $this->status);
    }

    public function payModeLabel(): string
    {
        $accounts = config('sellix.payment_methods', []);

        return $accounts[$this->paying_account] ?? $accounts[$this->payment_method] ?? ($this->paying_account ?: $this->payment_method ?: '-');
    }
}
