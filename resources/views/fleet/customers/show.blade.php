@extends('layouts.fleet')

@section('title', 'Customer Details')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-customers.css') }}?v=4">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">{{ $customer->name }}</h1>
        <a href="{{ route('customers.index') }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-list"></i> View List</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('customers.index') }}">Customer Info</a></li>
        <li>View</li>
    </ul>
</div>

<div class="fleet-customer-show-grid">
    <div class="fleet-panel fleet-customer-profile-card">
        <div class="fleet-panel-header">Customer Profile</div>
        <div class="fleet-panel-body">
            <div class="fleet-customer-profile-top">
                <div class="fleet-customer-profile-avatar">
                    <i class="fa fa-user"></i>
                </div>
                <div>
                    <div class="fleet-customer-profile-name">{{ $customer->name }}</div>
                    <span class="fleet-status-tag {{ $customer->status === 'Active' ? 'fleet-status-active' : 'fleet-status-inactive' }}">{{ $customer->status }}</span>
                </div>
            </div>
            <div class="fleet-detail-grid fleet-customer-profile-details">
                <div class="fleet-detail-item"><span>Mobile</span><strong>{{ $customer->mobile }}</strong></div>
                <div class="fleet-detail-item"><span>WhatsApp</span><strong>{{ $customer->whatsapp ?: '-' }}</strong></div>
                <div class="fleet-detail-item"><span>Email</span><strong>{{ $customer->email ?: '-' }}</strong></div>
                <div class="fleet-detail-item fleet-detail-full"><span>Address</span><strong>{{ $customer->address }}</strong></div>
                <div class="fleet-detail-item"><span>WhatsApp Notifications</span><strong>{{ $customer->whatsapp_notifications ? 'Enabled' : 'Disabled' }}</strong></div>
                <div class="fleet-detail-item"><span>Customer Since</span><strong>{{ format_fleet_date($customer->created_at) }}</strong></div>
            </div>
            <div class="fleet-customer-profile-actions">
                <a href="{{ route('customers.edit', $customer) }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-pencil"></i> Edit Customer</a>
            </div>
        </div>
    </div>

    <div class="fleet-customer-show-main">
        <div class="fleet-panel fleet-customer-summary-card">
            <div class="fleet-panel-header">Customer Financial Summary</div>
            <div class="fleet-panel-body">
                <div class="fleet-customer-summary-grid">
                    <div class="fleet-customer-summary-item">
                        <span>Total Amount Invoiced</span>
                        <strong>{{ format_kes($financial['total_invoiced']) }}</strong>
                    </div>
                    <div class="fleet-customer-summary-item paid">
                        <span>Total Amount Paid</span>
                        <strong>{{ format_kes($financial['total_paid']) }}</strong>
                    </div>
                    <div class="fleet-customer-summary-item outstanding">
                        <span>Outstanding Balance</span>
                        <strong>{{ format_kes($financial['outstanding_balance']) }}</strong>
                    </div>
                    <div class="fleet-customer-summary-item status">
                        <span>Payment Status</span>
                        <strong>
                            <span class="fleet-customer-status-pill {{ $customer->paymentStatusBadgeClass($financial['payment_status']) }}">
                                {{ $financial['payment_status'] }}
                            </span>
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="fleet-panel fleet-customer-section-card">
            <div class="fleet-panel-header">Customer Payments</div>
            <div class="fleet-table-wrap">
                <table class="fleet-customer-detail-table">
                    <thead>
                        <tr>
                            <th>Payment Date</th>
                            <th>Payment Method</th>
                            <th>Amount Paid</th>
                            <th>Reference Number</th>
                            <th>Related Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->payments as $payment)
                        <tr>
                            <td>{{ format_fleet_date($payment->payment_date) }}</td>
                            <td>{{ $payment->payment_method ?: '-' }}</td>
                            <td>{{ format_kes($payment->amount) }}</td>
                            <td>{{ $payment->reference_no ?: '-' }}</td>
                            <td>
                                @if ($payment->trip)
                                    <a href="{{ route('trips.invoice-pdf', $payment->trip) }}" target="_blank" rel="noopener">{{ $payment->trip->invoiceNumber() }}</a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="fleet-empty-row">No payments recorded for this customer yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fleet-panel fleet-customer-section-card">
            <div class="fleet-panel-header">Customer Invoices</div>
            <div class="fleet-table-wrap">
                <table class="fleet-customer-detail-table">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Invoice Date</th>
                            <th>Trip Name/Reference</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Balance Due</th>
                            <th>Invoice Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->trips as $trip)
                        <tr>
                            <td>{{ $trip->invoiceNumber() }}</td>
                            <td>{{ format_fleet_date($trip->start_date ?: $trip->created_at) }}</td>
                            <td>
                                <a href="{{ route('trips.show', $trip) }}">{{ $trip->tripDisplayName() }}</a>
                            </td>
                            <td>{{ format_kes($trip->totalAmount()) }}</td>
                            <td>{{ format_kes($trip->paidAmount()) }}</td>
                            <td>{{ format_kes($trip->remainingAmount()) }}</td>
                            <td>
                                <span class="fleet-customer-status-pill {{ $trip->invoiceStatusBadgeClass() }}">{{ $trip->invoiceStatus() }}</span>
                            </td>
                            <td>
                                <a href="{{ route('trips.invoice-pdf', $trip) }}" class="fleet-customer-invoice-link" target="_blank" rel="noopener" title="Download Invoice">
                                    <i class="fa fa-download"></i> PDF
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="fleet-empty-row">No invoices yet. Invoices are generated from trips assigned to this customer.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fleet-panel fleet-customer-section-card">
            <div class="fleet-panel-header">Customer Quotations</div>
            <div class="fleet-table-wrap">
                <table class="fleet-customer-detail-table">
                    <thead>
                        <tr>
                            <th>Quotation Number</th>
                            <th>Date Created</th>
                            <th>Trip Name/Reference</th>
                            <th>Total Amount</th>
                            <th>Quotation Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->quotations as $quotation)
                        <tr>
                            <td>{{ $quotation->quotation_number }}</td>
                            <td>{{ format_fleet_date($quotation->created_at) }}</td>
                            <td>
                                @if ($quotation->trip)
                                    <a href="{{ route('trips.show', $quotation->trip) }}">{{ $quotation->tripReferenceLabel() }}</a>
                                @else
                                    {{ $quotation->tripReferenceLabel() }}
                                @endif
                            </td>
                            <td>{{ format_kes($quotation->total_amount) }}</td>
                            <td>
                                <span class="fleet-customer-status-pill {{ $quotation->statusBadgeClass() }}">{{ $quotation->status }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="fleet-empty-row">No quotations found for this customer.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fleet-panel fleet-customer-section-card">
            <div class="fleet-panel-header">Trip History</div>
            <div class="fleet-table-wrap">
                <table class="fleet-customer-detail-table">
                    <thead>
                        <tr>
                            <th>Trip Name</th>
                            <th>Departure Date</th>
                            <th>Destination</th>
                            <th>Trip Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->trips as $trip)
                        <tr>
                            <td>
                                <a href="{{ route('trips.show', $trip) }}">{{ $trip->tripDisplayName() }}</a>
                            </td>
                            <td>{{ format_fleet_date($trip->start_date) }}</td>
                            <td>{{ $trip->routeLocationShort($trip->drop_location) }}</td>
                            <td>
                                <span class="fleet-customer-trip-status {{ $trip->statusBadgeClass() }}">{{ $trip->statusLabel() }}</span>
                            </td>
                            <td>
                                <a href="{{ route('trips.show', $trip) }}" class="fleet-customer-invoice-link"><i class="fa fa-eye"></i> View</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="fleet-empty-row">No trips assigned to this customer yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
