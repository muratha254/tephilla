<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToCompany;
    use BelongsToBranch;
    use SoftDeletes;

    public const TYPE_WALK_IN = 'walk_in';
    public const TYPE_REGULAR = 'regular';

    protected $fillable = [
        'company_id',
        'branch_id',
        'category_id',
        'assigned_to',
        'type',
        'name',
        'phone',
        'mobile',
        'email',
        'national_id',
        'address',
        'county',
        'estate',
        'postcode',
        'tax_number',
        'opening_balance',
        'credit_limit',
        'loyalty_points',
        'shop_image_path',
        'migration_account',
        'notes',
        'is_walk_in',
        'is_active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'loyalty_points' => 'decimal:2',
        'is_walk_in' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(CustomerCategory::class, 'category_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function creditAmount(): float
    {
        $due = $this->credit_sales ?? $this->sales()
            ->where('status', Sale::STATUS_COMPLETED)
            ->sum('balance');

        return round((float) $this->opening_balance + (float) $due, 2);
    }
}
