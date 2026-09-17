<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id', 'from_branch_id', 'to_branch_id', 'user_id',
        'number', 'transfer_date', 'status', 'notes', 'completed_at', 'cancelled_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('branch_transfer', function (Builder $builder) {
            if (! app()->bound('currentBranchId') || ! app('currentBranchId')) {
                return;
            }

            $branchId = (int) app('currentBranchId');
            $builder->where(function (Builder $query) use ($branchId) {
                $query->where('from_branch_id', $branchId)
                    ->orWhere('to_branch_id', $branchId);
            });
        });
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
