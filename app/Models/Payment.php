<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    public const METHOD_CASH = 'cash';
    public const METHOD_MPESA = 'mpesa';
    public const METHOD_CARD = 'card';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_COMPLEMENTARY = 'complementary';
    public const METHOD_ADVANCE = 'advance';
    public const METHOD_OTHER = 'other';

    protected $fillable = [
        'company_id', 'branch_id', 'user_id', 'customer_id', 'supplier_id',
        'number', 'payable_type', 'payable_id', 'method', 'amount',
        'reference', 'paid_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function payable()
    {
        return $this->morphTo();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
