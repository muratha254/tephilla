<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'phone',
        'mobile',
        'email',
        'address',
        'tax_number',
        'country',
        'state',
        'postcode',
        'opening_balance',
        'apply_withholding',
        'migration_account',
        'till_number',
        'paybill_number',
        'mpesa_account_name',
        'bank_account_name',
        'bank_account_number',
        'bank_name',
        'bank_branch',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'apply_withholding' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function currentBalance(): float
    {
        $outstanding = (float) $this->purchaseOrders()
            ->whereNotIn('status', [
                PurchaseOrder::STATUS_CANCELLED,
                PurchaseOrder::STATUS_DRAFT,
            ])
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as bal')
            ->value('bal');

        return round((float) $this->opening_balance + $outstanding, 2);
    }
}
