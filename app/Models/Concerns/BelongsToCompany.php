<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    public static function bootBelongsToCompany()
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (! app()->bound('currentCompanyId')) {
                return;
            }

            $companyId = app('currentCompanyId');
            if (! $companyId) {
                return;
            }

            $builder->where($builder->getModel()->getTable() . '.company_id', $companyId);
        });

        static::creating(function ($model) {
            if (! $model->company_id && app()->bound('currentCompanyId') && app('currentCompanyId')) {
                $model->company_id = app('currentCompanyId');
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, $companyId): Builder
    {
        return $query->withoutGlobalScope('company')->where($this->getTable() . '.company_id', $companyId);
    }
}
