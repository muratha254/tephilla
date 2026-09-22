<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionHistory extends Model
{
    protected $table = 'subscription_history';

    protected $fillable = [
        'company_id',
        'subscription_id',
        'actor_id',
        'action',
        'previous_plan_id',
        'new_plan_id',
        'previous_expires_at',
        'new_expires_at',
        'previous_status',
        'new_status',
        'notes',
        'ip_address',
    ];

    protected $casts = [
        'previous_expires_at' => 'date',
        'new_expires_at' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function previousPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'previous_plan_id');
    }

    public function newPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'new_plan_id');
    }
}
