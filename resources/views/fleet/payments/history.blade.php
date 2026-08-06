@extends('layouts.fleet')

@section('title', 'Payment History')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-payments.css') }}?v=7">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Payment History</h1>
        <a href="{{ route('payments.index') }}" class="fleet-btn fleet-btn-outline">
            <i class="fa fa-credit-card"></i> Customer Payments
        </a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('payments.index') }}">Customer Payments</a></li>
        <li>Payment History</li>
    </ul>
</div>

@if (session('success'))
    <div class="fleet-alert fleet-alert-success">{{ session('success') }}</div>
@endif

<div class="fleet-payments-summary">
    <div class="fleet-payments-summary-card paid">
        <span>Total Collected</span>
        <strong>{{ format_kes($summary['total_collected']) }}</strong>
    </div>
    <div class="fleet-payments-summary-card">
        <span>Payments in View</span>
        <strong>{{ number_format($summary['payment_count']) }}</strong>
    </div>
    <div class="fleet-payments-summary-card outstanding">
        <span>Total Outstanding Balance</span>
        <strong>{{ format_kes($summary['total_outstanding']) }}</strong>
    </div>
    <div class="fleet-payments-summary-card paid">
        <span>Total Amount Paid</span>
        <strong>{{ format_kes($summary['total_paid']) }}</strong>
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

<div class="fleet-panel fleet-payments-list-card fleet-payments-list-full">
    <div class="fleet-payments-list-head">
        <h3>All Payments</h3>
        <div class="fleet-payments-list-tools">
            <div class="fleet-payments-export-btns">
                <a href="{{ route('payments.export-pdf', array_filter(['search' => $search, 'customer_id' => $customerFilter ?: null, 'date_from' => $dateFrom ?: null, 'date_to' => $dateTo ?: null])) }}" class="fleet-btn fleet-btn-outline" target="_blank" rel="noopener">
                    <i class="fa fa-download"></i> Download Report
                </a>
                <button type="button" class="fleet-btn fleet-btn-outline" id="payments-print-report-btn">
                    <i class="fa fa-print"></i> Print Report
                </button>
            </div>
            <form method="GET" action="{{ route('payments.history') }}" class="fleet-payments-filters" id="payments-filter-form">
                <div class="fleet-search-wrap">
                    <i class="fa fa-search"></i>
                    <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search customer, trip, reference...">
                </div>
                <select name="customer_id" class="fleet-select" onchange="this.form.submit()">
                    <option value="">All Customers</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" {{ (int) $customerFilter === $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" class="fleet-input fleet-payments-date" value="{{ $dateFrom }}" title="From date">
                <input type="date" name="date_to" class="fleet-input fleet-payments-date" value="{{ $dateTo }}" title="To date">
                <button type="submit" class="fleet-btn fleet-btn-default">Filter</button>
                @if ($search !== '' || $customerFilter > 0 || $dateFrom !== '' || $dateTo !== '')
                    <a href="{{ route('payments.history') }}" class="fleet-btn fleet-btn-light">Clear</a>
                @endif
            </form>
        </div>
    </div>

    <div class="fleet-payments-print-area" id="payments-print-area">
        <div class="fleet-payments-print-summary">
            <strong>Payment Report</strong>
            <span>Total Collected: {{ format_kes($summary['total_collected']) }}</span>
            @if ($selectedCustomerBalance)
                <span>Customer Remaining Balance: {{ format_kes($selectedCustomerBalance['outstanding_balance']) }}</span>
            @else
                <span>Total Outstanding: {{ format_kes($summary['total_outstanding']) }}</span>
            @endif
        </div>

        <div class="fleet-table-wrap">
            <table class="fleet-payments-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Trip / Invoice</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Remaining Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $index => $payment)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ format_fleet_date($payment->payment_date) }}</td>
                        <td>
                            @if ($payment->customer)
                                <a href="{{ route('customers.show', $payment->customer) }}">{{ $payment->customer->name }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if ($payment->trip)
                                <a href="{{ route('trips.show', $payment->trip) }}">{{ $payment->trip->invoiceNumber() }}</a>
                                <div class="fleet-payments-trip-ref">{{ $payment->trip->displayTripCode() }}</div>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $payment->payment_method }}</td>
                        <td>{{ $payment->reference_no ?: '-' }}</td>
                        <td class="fleet-payments-amount">{{ format_kes($payment->amount) }}</td>
                        <td class="fleet-payments-outstanding">
                            @if ($payment->trip)
                                {{ format_kes($payment->remainingAfterPayment()) }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <div class="fleet-action-btns">
                                <a href="{{ route('payments.receipt-pdf', $payment) }}" class="fleet-action-btn view" title="Download Receipt" target="_blank" rel="noopener"><i class="fa fa-download"></i></a>
                                <a href="{{ route('payments.receipt-print', $payment) }}" class="fleet-action-btn edit-green" title="Print Receipt" target="_blank" rel="noopener"><i class="fa fa-print"></i></a>
                                <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Remove this payment? Outstanding balance will be restored.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="fleet-empty-row">No customer payments recorded yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var printReportBtn = document.getElementById('payments-print-report-btn');
    if (printReportBtn) {
        printReportBtn.addEventListener('click', function () {
            window.print();
        });
    }
})();
</script>
@endpush
