@extends('layouts.fleet')

@section('title', 'Customer Payments')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-payments.css') }}?v=6">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Customer Payments</h1>
        <div class="fleet-page-actions">
            <a href="{{ route('payments.history') }}" class="fleet-btn fleet-btn-outline">
                <i class="fa fa-history"></i> Payment History
            </a>
            <button type="button" class="fleet-btn fleet-btn-primary" id="open-record-payment-modal">
                <i class="fa fa-money"></i> Record Payment
            </button>
        </div>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Customer Payments</li>
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

<div class="fleet-payments-summary">
    <div class="fleet-payments-summary-card paid">
        <span>Total Amount Paid</span>
        <strong>{{ format_kes($summary['total_paid']) }}</strong>
    </div>
    <div class="fleet-payments-summary-card outstanding">
        <span>Total Outstanding Balance</span>
        <strong>{{ format_kes($summary['total_outstanding']) }}</strong>
    </div>
    <div class="fleet-payments-summary-card">
        <span>Trips With Balance</span>
        <strong>{{ $summary['outstanding_trips'] }}</strong>
    </div>
    <div class="fleet-payments-summary-card">
        <span>Customers</span>
        <strong>{{ $customerBalances->count() }}</strong>
    </div>
</div>

@if ($selectedCustomerBalance)
<div class="fleet-payments-selected-customer">
    <div class="fleet-payments-selected-customer-head">
        <strong>{{ $selectedCustomerBalance['customer']->name }}</strong>
        <span>Filtered customer balance</span>
    </div>
    <div class="fleet-payments-selected-customer-grid">
        <div><span>Total Invoiced</span><strong>{{ format_kes($selectedCustomerBalance['total_invoiced']) }}</strong></div>
        <div><span>Amount Paid</span><strong class="is-paid">{{ format_kes($selectedCustomerBalance['amount_paid']) }}</strong></div>
        <div><span>Outstanding Balance</span><strong class="is-outstanding">{{ format_kes($selectedCustomerBalance['outstanding_balance']) }}</strong></div>
    </div>
</div>
@endif

<div class="fleet-panel fleet-payments-balances-card">
    <div class="fleet-payments-list-head">
        <h3>Customer Payment &amp; Balance</h3>
        <span class="fleet-payments-list-note">Amount paid and outstanding balance for each customer</span>
    </div>
    <div class="fleet-table-wrap">
        <table class="fleet-payments-table fleet-payments-balances-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Mobile</th>
                    <th>Total Invoiced</th>
                    <th>Amount Paid</th>
                    <th>Outstanding Balance</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customerBalances as $index => $row)
                <tr class="{{ (int) $customerFilter === (int) $row['customer']->id ? 'is-selected' : '' }}">
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <a href="{{ route('customers.show', $row['customer']) }}">{{ $row['customer']->name }}</a>
                    </td>
                    <td>{{ $row['customer']->mobile }}</td>
                    <td>{{ format_kes($row['total_invoiced']) }}</td>
                    <td class="fleet-payments-amount">{{ format_kes($row['amount_paid']) }}</td>
                    <td class="fleet-payments-outstanding">{{ format_kes($row['outstanding_balance']) }}</td>
                    <td>
                        <div class="fleet-action-btns">
                            <a href="{{ route('payments.history', ['customer_id' => $row['customer']->id]) }}" class="fleet-action-btn view" title="View Payment History"><i class="fa fa-eye"></i></a>
                            <button type="button" class="fleet-action-btn edit-green js-open-record-payment" data-customer-id="{{ $row['customer']->id }}" data-customer-name="{{ $row['customer']->name }}" title="Record Payment"><i class="fa fa-money"></i></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="fleet-empty-row">No customers found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('fleet.payments.partials.record_payment_modal')
@endsection

@include('fleet.payments.partials.payment_modal_scripts')
