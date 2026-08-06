@extends('layouts.fleet')

@section('title', 'Edit Trip Status')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-trips.css') }}?v=7">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Edit Trip - {{ $trip->displayTripCode() }}</h1>
        <a href="{{ route('trips.show', $trip) }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-arrow-left"></i> Back to Trip</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('trips.index') }}">Trips</a></li>
        <li><a href="{{ route('trips.show', $trip) }}">Details</a></li>
        <li>Edit Status</li>
    </ul>
</div>

@if (session('success'))
    <div class="fleet-alert fleet-alert-success">{{ session('success') }}</div>
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

<div class="fleet-panel fleet-trip-status-edit-card">
    <div class="fleet-panel-header">Update Trip Status</div>
    <div class="fleet-panel-body">
        <div class="fleet-trip-status-edit-summary">
            <div class="fleet-detail-item"><span>Customer</span><strong>{{ $trip->customer_name }}</strong></div>
            <div class="fleet-detail-item"><span>Route</span><strong>{{ $trip->routeLocationShort($trip->pickup_location) }} → {{ $trip->routeLocationShort($trip->drop_location) }}</strong></div>
            <div class="fleet-detail-item"><span>Departure</span><strong>{{ format_fleet_date($trip->start_date) }}</strong></div>
            <div class="fleet-detail-item"><span>Current Status</span><strong><span class="fleet-trip-status-tag {{ $trip->statusBadgeClass() }}">{{ $trip->statusLabel() }}</span></strong></div>
        </div>

        <form method="POST" action="{{ route('trips.update-status', $trip) }}" class="fleet-trip-status-edit-form">
            @csrf
            @method('PATCH')
            <div class="fleet-form-group">
                <label for="trip-status-select">Trip Status</label>
                <select name="status" id="trip-status-select" class="fleet-input fleet-trip-status-select" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" {{ old('status', $trip->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fleet-trip-status-edit-actions">
                <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-save"></i> Save Status</button>
                <a href="{{ route('trips.show', $trip) }}" class="fleet-btn fleet-btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
