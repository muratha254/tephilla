<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoneyEntry extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    public const TYPE_PAYMENT = 'payment';
    public const TYPE_RECEIVE = 'receive';
    public const TYPE_OPEN_BAL = 'open_bal';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_REFUND = 'refund';
    public const TYPE_UNPAID_CHEQUE = 'unpaid_cheque';
    public const TYPE_CHEQUE_FINE = 'cheque_fine';

    protected $fillable = [
        'company_id', 'branch_id', 'user_id', 'ledger_account_id', 'counterpart_account_id',
        'type', 'number', 'voucher_no', 'pay_mode', 'payment_date', 'description',
        'amount_in', 'amount_out', 'transfer_group', 'supplier_id', 'customer_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount_in' => 'decimal:2',
        'amount_out' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function counterpart()
    {
        return $this->belongsTo(LedgerAccount::class, 'counterpart_account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
