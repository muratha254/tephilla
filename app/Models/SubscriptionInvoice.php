<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'subscription_id',
        'issued_by',
        'invoice_number',
        'status',
        'amount',
        'currency',
        'due_date',
        'sent_at',
        'sent_to_email',
        'paid_at',
        'plan_name',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'paid_at' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(?Carbon $now = null): bool
    {
        if ($this->isPaid() || $this->status === self::STATUS_CANCELLED) {
            return false;
        }

        $now = ($now ?: now())->startOfDay();

        return $this->due_date && $this->due_date->copy()->startOfDay()->lt($now);
    }

    public function displayStatus(): string
    {
        if ($this->isOverdue()) {
            return 'Overdue';
        }

        $map = [
            self::STATUS_SENT => 'Sent',
            self::STATUS_PAID => 'Paid',
            self::STATUS_CANCELLED => 'Cancelled',
        ];

        return $map[$this->status] ?? ucfirst($this->status);
    }
}
