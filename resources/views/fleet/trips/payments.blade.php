@extends('layouts.fleet')

@section('title', 'Trip Payments')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-trips.css') }}?v=10">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Record Payment - {{ $trip->displayTripCode() }}</h1>
        <a href="{{ route('trips.show', $trip) }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-arrow-left"></i> Back to Trip</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('trips.index') }}">Trips</a></li>
        <li><a href="{{ route('trips.show', $trip) }}">Details</a></li>
        <li>Payment</li>
    </ul>
</div>

@if (session('success'))
    @include('fleet.partials.payment_receipt_success')
@endif

@if ($errors->any())
    <div class="fleet-alert fleet-alert-error">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="fleet-trip-payment-summary">
    <div class="fleet-trip-payment-summary-card">
        <span>Trip Total</span>
        <strong>{{ format_kes($trip->totalAmount()) }}</strong>
    </div>
    <div class="fleet-trip-payment-summary-card paid">
        <span>Total Paid</span>
        <strong>{{ format_kes($trip->paidAmount()) }}</strong>
    </div>
    <div class="fleet-trip-payment-summary-card remaining">
        <span>Remaining Balance</span>
        <strong>{{ format_kes($trip->remainingAmount()) }}</strong>
    </div>
</div>

@if ($trip->customer)
    <div class="fleet-trip-payment-customer-note">
        Customer: <strong>{{ $trip->customer->name }}</strong>
        &bull; Account outstanding: <strong>{{ format_kes($trip->customer->outstanding_payment) }}</strong>
    </div>
@endif

<div class="fleet-trip-expense-layout">
    <div class="fleet-panel fleet-vehicle-card fleet-trip-expense-form-panel">
        <div class="fleet-panel-header">Record Deposit / Payment</div>
        <div class="fleet-panel-body">
            @if ($trip->remainingAmount() <= 0)
                <div class="fleet-alert fleet-alert-success">This trip is fully paid.</div>
            @else
            <form method="POST" action="{{ route('trips.payments.store', $trip) }}" class="fleet-trip-expense-form">
                @csrf
                <div class="fleet-trip-expense-form-grid">
                    <div class="fleet-field">
                        <label>Payment Date</label>
                        <input type="date" name="payment_date" class="fleet-input" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="fleet-field">
                        <label>Amount (KSh)</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $trip->remainingAmount() }}" name="amount" class="fleet-input" value="{{ old('amount') }}" placeholder="0.00" required>
                        <small class="fleet-field-hint">Max: {{ format_kes($trip->remainingAmount()) }}</small>
                    </div>
                    <div class="fleet-field">
                        <label>Payment Method</label>
                        <select name="payment_method" class="fleet-select fleet-input" required>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method }}" {{ old('payment_method', 'Cash') === $method ? 'selected' : '' }}>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fleet-field">
                        <label>Reference No.</label>
                        <input type="text" name="reference_no" class="fleet-input" value="{{ old('reference_no') }}" placeholder="M-Pesa / receipt code">
                    </div>
                    <div class="fleet-field fleet-field-wide">
                        <label>Notes</label>
                        <input type="text" name="notes" class="fleet-input" value="{{ old('notes') }}" placeholder="Deposit, partial payment, etc.">
                    </div>
                </div>
                <div class="fleet-trip-expense-form-actions">
                    <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-check"></i> Record Payment</button>
                </div>
            </form>
            @endif
        </div>
    </div>

    <div class="fleet-panel fleet-vehicle-card">
        <div class="fleet-trip-expense-list-head">
            <h3>Payment History</h3>
            <div class="fleet-trip-expense-list-actions">
                <span class="fleet-trip-expense-total">Remaining: <strong>{{ format_kes($trip->remainingAmount()) }}</strong></span>
            </div>
        </div>
        <div class="fleet-table-wrap">
            <table class="fleet-trip-list-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Notes</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trip->payments as $index => $payment)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $payment->formattedDate() }}</td>
                        <td>{{ $payment->payment_method }}</td>
                        <td>{{ $payment->reference_no ?: '-' }}</td>
                        <td>{{ $payment->notes ?: '-' }}</td>
                        <td>{{ format_kes($payment->amount) }}</td>
                        <td>
                            <div class="fleet-action-btns">
                                <a href="{{ route('payments.receipt-pdf', $payment) }}" class="fleet-action-btn receipt-pdf" title="Download Receipt" target="_blank" rel="noopener"><i class="fa fa-download"></i></a>
                                <a href="{{ route('payments.receipt-print', $payment) }}" class="fleet-action-btn receipt-print" title="Print Receipt" target="_blank" rel="noopener"><i class="fa fa-print"></i></a>
                                <form action="{{ route('trips.payments.destroy', [$trip, $payment]) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Remove this payment? Outstanding balance will be restored.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="fleet-empty-row">No payments recorded yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
