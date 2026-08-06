<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetTripPayment extends Model
{
    protected $fillable = [
        'fleet_trip_id',
        'fleet_customer_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_no',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function trip()
    {
        return $this->belongsTo(FleetTrip::class, 'fleet_trip_id');
    }

    public function customer()
    {
        return $this->belongsTo(FleetCustomer::class, 'fleet_customer_id');
    }

    public function formattedDate(): string
    {
        return $this->payment_date ? $this->payment_date->format('d M Y') : '-';
    }

    public function receiptNumber(): string
    {
        return 'RCP-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function remainingAfterPayment(): float
    {
        return $this->paymentSnapshotAfter()['remaining'];
    }

    public function paidAfterPayment(): float
    {
        return $this->paymentSnapshotAfter()['paid'];
    }

    private function paymentSnapshotAfter(): array
    {
        if (! $this->trip) {
            return ['paid' => 0.0, 'remaining' => 0.0];
        }

        $this->trip->loadMissing('payments');

        $cumulative = 0.0;
        foreach ($this->sortedTripPayments() as $payment) {
            $cumulative += (float) $payment->amount;

            if ((int) $payment->id === (int) $this->id) {
                return [
                    'paid' => round($cumulative, 2),
                    'remaining' => max(0, round($this->trip->totalAmount() - $cumulative, 2)),
                ];
            }
        }

        return [
            'paid' => $this->trip->paidAmount(),
            'remaining' => $this->trip->remainingAmount(),
        ];
    }

    private function sortedTripPayments()
    {
        return $this->trip->payments->sortBy(function (FleetTripPayment $payment) {
            $date = optional($payment->payment_date)->format('Y-m-d') ?? '0000-00-00';

            return $date . '-' . str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT);
        });
    }
}
