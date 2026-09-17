<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    use BelongsToCompany;

    public const TYPE_EARN = 'earn';
    public const TYPE_REVERSE = 'reverse';
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_ADJUST = 'adjust';

    protected $fillable = [
        'company_id', 'customer_id', 'user_id', 'type', 'points', 'balance_after',
        'source_type', 'source_id', 'reference', 'notes',
    ];

    protected $casts = [
        'points' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
