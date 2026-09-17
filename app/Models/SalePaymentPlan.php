<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class SalePaymentPlan extends Model
{
    use BelongsToCompany;

    public const TYPE_REGULAR = 'regular';
    public const TYPE_IRREGULAR = 'irregular';

    protected $fillable = [
        'company_id', 'sale_id', 'user_id', 'type', 'installment_amount', 'due_date',
    ];

    protected $casts = [
        'installment_amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(SalePaymentPlanItem::class)->orderBy('sort_order')->orderBy('due_date');
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_IRREGULAR ? 'IRREGULAR PLAN' : 'REGULAR PLAN';
    }
}
