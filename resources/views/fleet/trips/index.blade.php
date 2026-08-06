@extends('layouts.fleet')

@section('title', 'Trips Management')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('css/fleet-trips.css') }}?v=11">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Trips Management</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Trips</li>
    </ul>
</div>

@if (session('success'))
    @include('fleet.partials.payment_receipt_success')
@endif

<div class="fleet-vehicle-toolbar">
    <div class="fleet-status-pills fleet-trip-status-pills">
        <a href="{{ route('trips.index', array_filter(['search' => $search, 'status' => $statusFilter])) }}" class="fleet-pill fleet-pill-blue {{ $activeTab === 'all' ? 'active' : '' }}">All Trips ({{ $counts['all'] }})</a>
        <a href="{{ route('trips.index', array_filter(['tab' => 'upcoming', 'search' => $search, 'status' => $statusFilter])) }}" class="fleet-pill fleet-pill-cyan {{ $activeTab === 'upcoming' ? 'active' : '' }}">Upcoming ({{ $counts['upcoming'] }})</a>
        <a href="{{ route('trips.index', array_filter(['tab' => 'ongoing', 'search' => $search, 'status' => $statusFilter])) }}" class="fleet-pill fleet-pill-yellow {{ $activeTab === 'ongoing' ? 'active' : '' }}">Ongoing ({{ $counts['ongoing'] }})</a>
        <a href="{{ route('trips.index', array_filter(['tab' => 'completed', 'search' => $search, 'status' => $statusFilter])) }}" class="fleet-pill fleet-pill-green {{ $activeTab === 'completed' ? 'active' : '' }}">Completed ({{ $counts['completed'] }})</a>
        <a href="{{ route('trips.index', array_filter(['tab' => 'cancelled', 'search' => $search, 'status' => $statusFilter])) }}" class="fleet-pill fleet-pill-red {{ $activeTab === 'cancelled' ? 'active' : '' }}">Cancelled ({{ $counts['cancelled'] }})</a>
    </div>
    <a href="{{ route('trips.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> New Trip</a>
</div>

<div class="fleet-panel fleet-vehicle-card">
    <form method="GET" action="{{ route('trips.index') }}" class="fleet-vehicle-filters">
        @if ($activeTab !== 'all')
            <input type="hidden" name="tab" value="{{ $activeTab }}">
        @endif
        <div class="fleet-search-wrap">
            <i class="fa fa-search"></i>
            <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search trip, vehicle, driver...">
        </div>
        <select name="status" class="fleet-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="Booked" {{ $statusFilter === 'Booked' ? 'selected' : '' }}>Booked</option>
            <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>pending</option>
            <option value="ongoing" {{ $statusFilter === 'ongoing' ? 'selected' : '' }}>ongoing</option>
            <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>completed</option>
            <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>cancelled</option>
        </select>
        <div class="fleet-view-toggle">
            <button type="button" class="fleet-view-icon" aria-label="Grid view"><i class="fa fa-th"></i></button>
            <button type="button" class="fleet-view-icon active" aria-label="List view"><i class="fa fa-list"></i></button>
            <button type="button" class="fleet-view-icon" aria-label="Calendar view"><i class="fa fa-calendar"></i></button>
        </div>
    </form>

    <div class="fleet-table-wrap">
        <table class="fleet-trip-list-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Details</th>
                    <th>Customer</th>
                    <th>Route</th>
                    <th>Vehicle</th>
                    <th>Driver Name</th>
                    <th class="fleet-trip-amount-col">Total</th>
                    <th class="fleet-trip-amount-col">Paid</th>
                    <th class="fleet-trip-amount-col">Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($trips as $index => $trip)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <div class="fleet-trip-code">{{ $trip->displayTripCode() }}</div>
                        <div class="fleet-trip-datetime"><i class="fa fa-calendar"></i> {{ $trip->formattedStart() }}</div>
                        <div class="fleet-trip-datetime"><i class="fa fa-calendar"></i> {{ $trip->formattedEnd() }}</div>
                    </td>
                    <td>
                        <div class="fleet-trip-customer"><i class="fa fa-user"></i> {{ $trip->customer_name }}</div>
                        @if ($trip->customer_phone)
                            <div class="fleet-trip-phone"><i class="fa fa-phone"></i> {{ $trip->customer_phone }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="fleet-trip-route-line pickup"><span class="fleet-route-dot"></span> {{ $trip->pickup_location }}</div>
                        <div class="fleet-trip-route-line drop"><span class="fleet-route-dot"></span> {{ $trip->drop_location }}</div>
                    </td>
                    <td>
                        @if ($trip->vehicle)
                            <div class="fleet-vehicle-name">{{ $trip->vehicle->displayName() }}</div>
                            <div class="fleet-vehicle-meta">{{ $trip->vehicle->registration_number }}</div>
                        @else
                            <div class="fleet-trip-muted">No Vehicle</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ optional($trip->driver)->name ?: 'Unassigned' }}</div>
                        @if ($trip->return_trip_of)
                            <div class="fleet-trip-return-badge">Return Trip of {{ $trip->return_trip_of }}</div>
                        @endif
                    </td>
                    <td class="fleet-trip-amount-col">
                        <strong class="fleet-trip-list-amount">{{ format_kes($trip->totalAmount()) }}</strong>
                    </td>
                    <td class="fleet-trip-amount-col">
                        <strong class="fleet-trip-list-amount paid">{{ format_kes($trip->paidAmount()) }}</strong>
                    </td>
                    <td class="fleet-trip-amount-col">
                        @php $balance = $trip->remainingAmount(); @endphp
                        <strong class="fleet-trip-list-amount {{ $balance > 0 ? 'balance-due' : 'balance-clear' }}">{{ format_kes($balance) }}</strong>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('trips.update-status', $trip) }}" class="fleet-trip-status-form">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="fleet-trip-status-select fleet-trip-status-select-inline {{ $trip->statusBadgeClass() }}" onchange="this.form.submit()">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ $trip->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td>
                        <div class="fleet-action-btns">
                            @if ($balance > 0)
                            <button
                                type="button"
                                class="fleet-action-btn payment fleet-trip-record-payment-btn"
                                title="Record Payment"
                                data-trip-id="{{ $trip->id }}"
                                data-trip-code="{{ $trip->displayTripCode() }}"
                                data-customer="{{ $trip->customer_name }}"
                                data-total-label="{{ format_kes($trip->totalAmount()) }}"
                                data-paid-label="{{ format_kes($trip->paidAmount()) }}"
                                data-remaining-label="{{ format_kes($balance) }}"
                                data-remaining="{{ number_format($balance, 2, '.', '') }}"
                                data-action="{{ route('trips.payments.store', $trip) }}"
                            ><i class="fa fa-money"></i></button>
                            @endif
                            <a href="{{ route('trips.show', $trip) }}" class="fleet-action-btn view" title="View"><i class="fa fa-eye"></i></a>
                            <a href="{{ route('trips.edit', $trip) }}" class="fleet-action-btn edit" title="Edit"><i class="fa fa-pencil"></i></a>
                            <form action="{{ route('trips.destroy', $trip) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this trip?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="fleet-empty-row">No trips found. <a href="{{ route('trips.create') }}">Create a new trip</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('fleet.trips.partials.record_payment_modal')
@endsection

@push('scripts')
@include('fleet.trips.partials.record_payment_modal_scripts')
@endpush
