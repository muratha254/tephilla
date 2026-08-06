<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetTrip extends Model
{
    protected $fillable = [
        'trip_code',
        'trip_type',
        'fleet_vehicle_id',
        'fleet_driver_id',
        'fleet_customer_id',
        'customer_name',
        'customer_phone',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'pickup_location',
        'drop_location',
        'container_number',
        'container_empty_drop_point',
        'distance_km',
        'additional_stops',
        'billing_type',
        'billing_quantity',
        'billing_rate',
        'base_amount',
        'tax_type',
        'coupon_code',
        'discount_amount',
        'status',
        'return_trip_of',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'additional_stops' => 'array',
        'billing_quantity' => 'decimal:3',
        'billing_rate' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'distance_km' => 'decimal:2',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(FleetDriver::class, 'fleet_driver_id');
    }

    public function customer()
    {
        return $this->belongsTo(FleetCustomer::class, 'fleet_customer_id');
    }

    public function expenses()
    {
        return $this->hasMany(FleetTripExpense::class, 'fleet_trip_id')->orderByDesc('expense_date')->orderByDesc('id');
    }

    public function payments()
    {
        return $this->hasMany(FleetTripPayment::class, 'fleet_trip_id')->orderByDesc('payment_date')->orderByDesc('id');
    }

    public function track()
    {
        return $this->hasOne(FleetTripTrack::class, 'fleet_trip_id');
    }

    public function expenseReportNumber(): string
    {
        return 'EXP-' . $this->displayTripCode();
    }

    public function totalExpensesAmount(): float
    {
        if ($this->relationLoaded('expenses')) {
            return round((float) $this->expenses->sum('amount'), 2);
        }

        if (isset($this->expenses_total)) {
            return round((float) $this->expenses_total, 2);
        }

        return round((float) $this->expenses()->sum('amount'), 2);
    }

    public function profitAmount(): float
    {
        return round($this->totalAmount() - $this->totalExpensesAmount(), 2);
    }

    public function displayTripCode(): string
    {
        if ($this->trip_code) {
            return $this->trip_code;
        }

        return 'OT-' . optional($this->created_at)->format('Y') . '-' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    public function invoiceNumber(): string
    {
        return 'INV-' . $this->displayTripCode();
    }

    public function tripDisplayName(): string
    {
        $pickup = $this->routeLocationShort($this->pickup_location);
        $drop = $this->routeLocationShort($this->drop_location);

        return $this->displayTripCode() . ' — ' . $pickup . ' to ' . $drop;
    }

    public function invoiceStatus(): string
    {
        $total = $this->totalAmount();

        if ($total <= 0) {
            return 'Unpaid';
        }

        if ($this->remainingAmount() <= 0) {
            return 'Paid';
        }

        if ($this->paidAmount() > 0) {
            return 'Partially Paid';
        }

        return 'Unpaid';
    }

    public function invoiceStatusBadgeClass(): string
    {
        return match ($this->invoiceStatus()) {
            'Paid' => 'fleet-customer-payment-paid',
            'Partially Paid' => 'fleet-customer-payment-partial',
            default => 'fleet-customer-payment-unpaid',
        };
    }

    public function statusBadgeClass(): string
    {
        $status = strtolower((string) $this->status);

        if ($status === 'ongoing') {
            return 'fleet-trip-status-ongoing';
        }

        if ($status === 'completed') {
            return 'fleet-trip-status-completed';
        }

        if ($status === 'cancelled') {
            return 'fleet-trip-status-cancelled';
        }

        if ($status === 'pending') {
            return 'fleet-trip-status-pending';
        }

        return 'fleet-trip-status-booked';
    }

    public function formattedStart(): string
    {
        return $this->formatDateTime($this->start_date, $this->start_time);
    }

    public function formattedEnd(): string
    {
        return $this->formatDateTime($this->end_date, $this->end_time);
    }

    public function routeLocationShort(?string $location = null): string
    {
        $location = $location ?? '';

        if ($location === '') {
            return '-';
        }

        $parts = explode(',', $location);

        return trim($parts[0]);
    }

    public function formattedRouteDate($date): string
    {
        if (! $date) {
            return '-';
        }

        return format_fleet_date($date);
    }

    public function formattedRouteTime($time): string
    {
        if (! $time) {
            return '-';
        }

        $value = substr((string) $time, 0, 8);

        try {
            return \Carbon\Carbon::createFromFormat(strlen($value) > 5 ? 'H:i:s' : 'H:i', $value)->format('h:i A');
        } catch (\Exception $e) {
            return substr((string) $time, 0, 5);
        }
    }

    public function subtotalAmount(): float
    {
        $base = (float) $this->base_amount;
        $discount = (float) $this->discount_amount;

        return max(0, round($base - $discount, 2));
    }

    public function taxAmount(): float
    {
        $subtotal = $this->subtotalAmount();

        if (! $this->tax_type || $this->tax_type === 'No Tax') {
            return 0;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*%/', (string) $this->tax_type, $matches)) {
            return round($subtotal * ((float) $matches[1] / 100), 2);
        }

        return 0;
    }

    public function totalAmount(): float
    {
        return round($this->subtotalAmount() + $this->taxAmount(), 2);
    }

    public function paidAmount(): float
    {
        if ($this->relationLoaded('payments')) {
            return round((float) $this->payments->sum('amount'), 2);
        }

        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function remainingAmount(): float
    {
        return max(0, round($this->totalAmount() - $this->paidAmount(), 2));
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[(string) $this->status] ?? ucfirst(strtolower((string) $this->status));
    }

    public static function statusOptions(): array
    {
        return [
            'Booked' => 'Booked',
            'pending' => 'Pending',
            'ongoing' => 'Ongoing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function allowedStatuses(): array
    {
        return array_keys(self::statusOptions());
    }

    public static function billingUnitConfig(): array
    {
        return [
            'Per Tonne' => ['quantity' => 'Number of Tonnes', 'rate' => 'Charge per Tonne'],
            'Per KG' => ['quantity' => 'Weight (KG)', 'rate' => 'Charge per KG'],
            'Per KM' => ['quantity' => 'Distance (KM)', 'rate' => 'Charge per KM'],
            'Per Trip' => ['quantity' => 'Number of Trips', 'rate' => 'Charge per Trip'],
            'Per Day' => ['quantity' => 'Number of Days', 'rate' => 'Charge per Day'],
            'Per Hour' => ['quantity' => 'Number of Hours', 'rate' => 'Charge per Hour'],
            'Per Litre' => ['quantity' => 'Litres', 'rate' => 'Charge per Litre'],
            'Per Bag' => ['quantity' => 'Number of Bags', 'rate' => 'Charge per Bag'],
        ];
    }

    public function usesVariableBilling(): bool
    {
        return $this->billing_type && $this->billing_type !== 'Fixed';
    }

    public function billingBreakdownLabel(): ?string
    {
        if (! $this->usesVariableBilling() || ! $this->billing_quantity || ! $this->billing_rate) {
            return null;
        }

        $qty = rtrim(rtrim(number_format((float) $this->billing_quantity, 3, '.', ''), '0'), '.');
        $rate = format_kes((float) $this->billing_rate);
        $total = format_kes((float) $this->base_amount);

        return "{$qty} × {$rate} = {$total}";
    }

    private function formatDateTime($date, $time): string
    {
        if (! $date) {
            return '-';
        }

        $formatted = format_fleet_date($date);

        if ($time) {
            $formatted .= ' ' . substr((string) $time, 0, 5);
        }

        return $formatted;
    }
}
