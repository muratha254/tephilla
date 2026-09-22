<?php

namespace App\Models;

use App\Support\SubscriptionCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'company_id',
        'subscription_plan_id',
        'status',
        'starts_at',
        'expires_at',
        'trial_ends_at',
        'suspended_at',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'expires_at' => 'date',
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function history()
    {
        return $this->hasMany(SubscriptionHistory::class);
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function daysRemaining(?Carbon $now = null): int
    {
        $now = ($now ?: now())->startOfDay();
        $expires = optional($this->expires_at)->copy()->startOfDay();
        if (! $expires) {
            return 0;
        }

        return (int) $now->diffInDays($expires, false);
    }

    public function isPastDue(?Carbon $now = null): bool
    {
        return $this->daysRemaining($now) < 0;
    }

    public function isExpiringSoon(?Carbon $now = null): bool
    {
        $days = $this->daysRemaining($now);
        $window = (int) config('sellix.subscription_expiring_days', 7);

        return $days >= 0 && $days <= $window;
    }

    /**
     * Stored status plus date-derived overlay. Manual suspend/cancel/deactivate win.
     */
    public function effectiveStatus(?Carbon $now = null): string
    {
        $stored = (string) $this->status;

        if (in_array($stored, [
            SubscriptionCatalog::STATUS_PENDING_APPROVAL,
            SubscriptionCatalog::STATUS_REJECTED,
            SubscriptionCatalog::STATUS_SUSPENDED,
            SubscriptionCatalog::STATUS_CANCELLED,
            SubscriptionCatalog::STATUS_DEACTIVATED,
        ], true)) {
            return $stored;
        }

        if ($this->isPastDue($now)) {
            return SubscriptionCatalog::STATUS_EXPIRED;
        }

        if ($stored === SubscriptionCatalog::STATUS_TRIAL) {
            return $this->isExpiringSoon($now)
                ? SubscriptionCatalog::STATUS_EXPIRING_SOON
                : SubscriptionCatalog::STATUS_TRIAL;
        }

        if ($this->isExpiringSoon($now)) {
            return SubscriptionCatalog::STATUS_EXPIRING_SOON;
        }

        return SubscriptionCatalog::STATUS_ACTIVE;
    }

    public function allowsAccess(?Carbon $now = null): bool
    {
        return in_array($this->effectiveStatus($now), [
            SubscriptionCatalog::STATUS_TRIAL,
            SubscriptionCatalog::STATUS_ACTIVE,
            SubscriptionCatalog::STATUS_EXPIRING_SOON,
        ], true);
    }

    public function statusLabel(?Carbon $now = null): string
    {
        $labels = SubscriptionCatalog::statuses();
        $status = $this->effectiveStatus($now);

        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
