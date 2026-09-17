<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SalePaymentPlanItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'sale_payment_plan_id', 'sale_id', 'due_date', 'amount', 'paid_amount', 'status', 'sort_order',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function remainingAmount(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->paid_amount), 2);
    }

    public function plan()
    {
        return $this->belongsTo(SalePaymentPlan::class, 'sale_payment_plan_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
