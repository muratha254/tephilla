<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturn extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'sale_id', 'customer_id', 'user_id',
        'number', 'return_date', 'status', 'refund_method', 'refund_amount',
        'restore_stock', 'notes',
    ];

    protected $casts = [
        'return_date' => 'datetime',
        'refund_amount' => 'decimal:2',
        'restore_stock' => 'boolean',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
