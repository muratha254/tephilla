<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetCustomer extends Model
{
    protected $fillable = [
        'name',
        'mobile',
        'whatsapp',
        'whatsapp_same_as_mobile',
        'email',
        'password',
        'address',
        'whatsapp_notifications',
        'status',
        'outstanding_payment',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'whatsapp_same_as_mobile' => 'boolean',
        'whatsapp_notifications' => 'boolean',
        'outstanding_payment' => 'decimal:2',
    ];

    public function trips(): HasMany
    {
        return $this->hasMany(FleetTrip::class, 'fleet_customer_id')
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FleetTripPayment::class, 'fleet_customer_id')
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(FleetQuotation::class, 'fleet_customer_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function financialSummary(): array
    {
        $trips = $this->relationLoaded('trips')
            ? $this->trips
            : $this->trips()->with('payments')->get();

        $totalInvoiced = 0.0;
        $totalPaid = 0.0;

        foreach ($trips as $trip) {
            $totalInvoiced += $trip->totalAmount();
            $totalPaid += $trip->paidAmount();
        }

        $totalInvoiced = round($totalInvoiced, 2);
        $totalPaid = round($totalPaid, 2);
        $outstanding = max(0, round($totalInvoiced - $totalPaid, 2));

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'outstanding_balance' => $outstanding,
            'payment_status' => $this->resolvePaymentStatus($totalInvoiced, $totalPaid, $outstanding),
        ];
    }

    public function paymentStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'Paid' => 'fleet-customer-payment-paid',
            'Partially Paid' => 'fleet-customer-payment-partial',
            default => 'fleet-customer-payment-unpaid',
        };
    }

    private function resolvePaymentStatus(float $totalInvoiced, float $totalPaid, float $outstanding): string
    {
        if ($totalInvoiced <= 0) {
            return 'Unpaid';
        }

        if ($outstanding <= 0) {
            return 'Paid';
        }

        if ($totalPaid > 0) {
            return 'Partially Paid';
        }

        return 'Unpaid';
    }
}
