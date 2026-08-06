<?php

namespace App\Services;

use App\Models\FleetCustomer;
use App\Models\FleetTrip;
use App\Models\FleetTripPayment;
use Illuminate\Support\Facades\DB;

class FleetTripPaymentService
{
    public function record(FleetTrip $trip, array $validated): FleetTripPayment
    {
        $remaining = $trip->remainingAmount();

        if ((float) $validated['amount'] > $remaining) {
            throw new \InvalidArgumentException(
                'Payment cannot exceed the remaining balance of ' . number_format($remaining, 2) . ' KSh.'
            );
        }

        return DB::transaction(function () use ($trip, $validated) {
            $payment = $trip->payments()->create([
                'fleet_customer_id' => $trip->fleet_customer_id,
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->reduceOutstandingForPayment($trip, (float) $validated['amount']);

            return $payment;
        });
    }

    public function delete(FleetTrip $trip, FleetTripPayment $payment): void
    {
        if ((int) $payment->fleet_trip_id !== (int) $trip->id) {
            abort(404);
        }

        DB::transaction(function () use ($trip, $payment) {
            $amount = (float) $payment->amount;
            $payment->delete();
            $this->restoreOutstandingForPayment($trip, $amount);
        });
    }

    private function reduceOutstandingForPayment(FleetTrip $trip, float $amount): void
    {
        if (! $trip->fleet_customer_id || $amount <= 0) {
            return;
        }

        $customer = FleetCustomer::query()->find($trip->fleet_customer_id);

        if (! $customer) {
            return;
        }

        $customer->outstanding_payment = max(0, round((float) $customer->outstanding_payment - $amount, 2));
        $customer->save();
    }

    private function restoreOutstandingForPayment(FleetTrip $trip, float $amount): void
    {
        if (! $trip->fleet_customer_id || $amount <= 0) {
            return;
        }

        FleetCustomer::query()
            ->whereKey($trip->fleet_customer_id)
            ->increment('outstanding_payment', $amount);
    }
}
