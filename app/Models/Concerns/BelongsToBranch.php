<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToBranch
{
    public static function bootBelongsToBranch()
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (! app()->bound('currentBranchId')) {
                return;
            }

            $branchId = app('currentBranchId');
            if (! $branchId) {
                return;
            }

            $table = $builder->getModel()->getTable();
            $builder->where(function (Builder $query) use ($table, $branchId) {
                $query->where($table . '.branch_id', $branchId)
                    ->orWhereNull($table . '.branch_id');
            });
        });

        static::creating(function ($model) {
            if (! $model->branch_id && app()->bound('currentBranchId') && app('currentBranchId')) {
                $model->branch_id = app('currentBranchId');
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->withoutGlobalScope('branch')->where($this->getTable() . '.branch_id', $branchId);
    }

    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}
