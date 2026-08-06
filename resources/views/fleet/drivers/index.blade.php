@extends('layouts.fleet')

@section('title', 'Driver Info')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-drivers.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Driver Info</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Driver Info</li>
    </ul>
</div>

<div class="fleet-vehicle-toolbar">
    <div class="fleet-status-pills">
        <button type="button" class="fleet-pill fleet-pill-blue active">All Drivers ({{ $counts['all'] }})</button>
        <button type="button" class="fleet-pill fleet-pill-green">Active ({{ $counts['active'] }})</button>
        <button type="button" class="fleet-pill fleet-pill-red">Inactive ({{ $counts['inactive'] }})</button>
    </div>
    <a href="{{ route('drivers.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add Driver</a>
</div>

<div class="fleet-panel fleet-vehicle-card">
    <form method="GET" action="{{ route('drivers.index') }}" class="fleet-vehicle-filters">
        <div class="fleet-search-wrap">
            <i class="fa fa-search"></i>
            <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search driver name, license, contact...">
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
        <table class="fleet-driver-list-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>License No</th>
                    <th>License Expiry</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($drivers as $index => $driver)
                @php
                    $expiryDays = $driver->licenseExpiryDays();
                    $expiryClass = $expiryDays !== null && $expiryDays < 0 ? 'is-expired' : 'is-valid';
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        @if ($driver->photo_path)
                            <img src="{{ asset('storage/' . $driver->photo_path) }}" alt="" class="fleet-driver-list-photo">
                        @else
                            <span class="fleet-driver-list-photo fleet-driver-list-photo-empty"><i class="fa fa-user"></i></span>
                        @endif
                    </td>
                    <td>
                        <div class="fleet-vehicle-name">{{ $driver->name }}</div>
                    </td>
                    <td>
                        <div class="fleet-meta-line"><i class="fa fa-phone"></i> {{ $driver->mobile }}</div>
                        <div class="fleet-meta-line"><i class="fa fa-envelope"></i> {{ $driver->email }}</div>
                    </td>
                    <td>{{ $driver->license_number ?: '-' }}</td>
                    <td>
                        @if ($driver->license_expiry)
                            <div>{{ format_fleet_date($driver->license_expiry) }}</div>
                            <div class="fleet-license-expiry {{ $expiryClass }}">{{ $driver->licenseExpiryLabel() }}</div>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        <span class="fleet-status-tag {{ $driver->status === 'Active' ? 'fleet-status-active' : 'fleet-status-inactive' }}">
                            {{ $driver->status }}
                        </span>
                    </td>
                    <td>
                        <div class="fleet-action-btns">
                            <a href="{{ route('drivers.show', $driver) }}" class="fleet-action-btn view" title="View"><i class="fa fa-eye"></i></a>
                            <a href="{{ route('drivers.edit', $driver) }}#driver-password" class="fleet-action-btn key" title="Reset Password"><i class="fa fa-key"></i></a>
                            <a href="{{ route('drivers.edit', $driver) }}" class="fleet-action-btn edit-green" title="Edit"><i class="fa fa-pencil"></i></a>
                            <form action="{{ route('drivers.destroy', $driver) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this driver?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="fleet-empty-row">No drivers found. <a href="{{ route('drivers.create') }}">Add your first driver</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
