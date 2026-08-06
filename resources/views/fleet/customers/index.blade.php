@extends('layouts.fleet')

@section('title', 'Customer Info')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-customers.css') }}?v=5">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Customer Info</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Customer Info</li>
    </ul>
</div>

<div class="fleet-vehicle-toolbar">
    <div class="fleet-status-pills fleet-customer-status-pills">
        <a href="{{ route('customers.index', array_filter(['search' => $search, 'status' => $statusFilter])) }}" class="fleet-pill fleet-pill-blue {{ $activeTab === 'all' ? 'active' : '' }}">All Customers ({{ $counts['all'] }})</a>
        <a href="{{ route('customers.index', array_filter(['tab' => 'active', 'search' => $search, 'status' => $statusFilter])) }}" class="fleet-pill fleet-pill-green {{ $activeTab === 'active' ? 'active' : '' }}">Active ({{ $counts['active'] }})</a>
        <a href="{{ route('customers.index', array_filter(['tab' => 'inactive', 'search' => $search, 'status' => $statusFilter])) }}" class="fleet-pill fleet-pill-red {{ $activeTab === 'inactive' ? 'active' : '' }}">Inactive ({{ $counts['inactive'] }})</a>
    </div>
    <a href="{{ route('customers.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add Customer</a>
</div>

<div class="fleet-panel fleet-vehicle-card">
    <form method="GET" action="{{ route('customers.index') }}" class="fleet-vehicle-filters">
        @if ($activeTab !== 'all')
            <input type="hidden" name="tab" value="{{ $activeTab }}">
        @endif
        <div class="fleet-search-wrap">
            <i class="fa fa-search"></i>
            <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search customer name, mobile, email...">
        </div>
        <select name="status" class="fleet-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="Active" {{ $statusFilter === 'Active' ? 'selected' : '' }}>Active</option>
            <option value="Inactive" {{ $statusFilter === 'Inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <div class="fleet-view-toggle">
            <button type="button" class="fleet-view-icon" aria-label="Grid view"><i class="fa fa-th"></i></button>
            <button type="button" class="fleet-view-icon active" aria-label="List view"><i class="fa fa-list"></i></button>
        </div>
    </form>

    <div class="fleet-table-wrap">
        <table class="fleet-customer-list-table">
            <thead>
                <tr>
                    <th>S.NO</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Amount Paid</th>
                    <th>Outstanding Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $index => $customer)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><div class="fleet-vehicle-name">{{ $customer->name }}</div></td>
                    <td>{{ $customer->mobile }}</td>
                    <td>{{ $customer->email ?: '-' }}</td>
                    <td>{{ $customer->address }}</td>
                    <td class="fleet-customer-money paid">{{ format_kes($customer->total_amount_paid ?? 0) }}</td>
                    <td class="fleet-customer-money outstanding">{{ format_kes($customer->outstanding_payment) }}</td>
                    <td>
                        <span class="fleet-status-tag {{ $customer->status === 'Active' ? 'fleet-status-active' : 'fleet-status-inactive' }}">
                            {{ $customer->status }}
                        </span>
                    </td>
                    <td>
                        <div class="fleet-action-btns">
                            <a href="{{ route('customers.show', $customer) }}" class="fleet-action-btn view" title="View"><i class="fa fa-eye"></i></a>
                            <a href="{{ route('customers.edit', $customer) }}" class="fleet-action-btn edit-green" title="Edit"><i class="fa fa-pencil"></i></a>
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this customer?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="fleet-empty-row">No customers found. <a href="{{ route('customers.create') }}">Add your first customer</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
