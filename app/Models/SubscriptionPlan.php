<?php

namespace App\Models;

use App\Support\SubscriptionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_period',
        'duration_days',
        'max_users',
        'max_branches',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'max_users' => 'integer',
        'max_branches' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (self $plan) {
            if (! $plan->slug) {
                $plan->slug = Str::slug($plan->name) ?: 'plan-' . Str::lower(Str::random(6));
            }
        });
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function periodLabel(): string
    {
        $labels = SubscriptionCatalog::periods();

        return $labels[$this->billing_period] ?? ucfirst((string) $this->billing_period);
    }

    public function featureList(): array
    {
        return array_values(array_filter((array) $this->features));
    }

    public function allowsFeature(string $feature): bool
    {
        return in_array($feature, $this->featureList(), true);
    }

    public function maxUsersLabel(): string
    {
        return $this->max_users ? (string) $this->max_users : 'Unlimited';
    }

    public function maxBranchesLabel(): string
    {
        return $this->max_branches ? (string) $this->max_branches : 'Unlimited';
    }
}
