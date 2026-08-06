@extends('layouts.fleet')

@section('title', 'Report Issue')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-incidents.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Report Issue</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('incidents.index') }}">Incidents</a></li>
        <li>Report</li>
    </ul>
</div>

@if ($errors->any())
<div class="fleet-alert fleet-alert-error">
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="fleet-panel fleet-incident-form-card">
    <form method="POST" action="{{ route('incidents.store') }}" class="fleet-incident-form">
        @csrf
        <div class="fleet-incident-form-grid">
            <div class="fleet-incident-field">
                <label>Vehicle <span class="required">*</span></label>
                <select name="fleet_vehicle_id" class="fleet-incident-input" required>
                    <option value="">Select Vehicle</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" {{ (string) old('fleet_vehicle_id') === (string) $vehicle->id ? 'selected' : '' }}>
                            {{ $vehicle->displayName() }} ({{ $vehicle->registration_number }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="fleet-incident-field">
                <label>Incident Date <span class="required">*</span></label>
                <input type="date" name="incident_date" class="fleet-incident-input" value="{{ old('incident_date', now()->format('Y-m-d')) }}" required>
            </div>
            <div class="fleet-incident-field fleet-incident-field-wide">
                <label>Description <span class="required">*</span></label>
                <textarea name="description" class="fleet-incident-textarea" rows="6" placeholder="Enter each issue on a new line&#10;e.g. Uneven tyre wear&#10;Low tyre pressure warning" required>{{ old('description') }}</textarea>
                <small class="fleet-incident-hint">Enter one issue per line.</small>
            </div>
        </div>
        <div class="fleet-incident-form-actions">
            <a href="{{ route('incidents.index') }}" class="fleet-btn fleet-btn-outline">Cancel</a>
            <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-save"></i> Submit Report</button>
        </div>
    </form>
</div>
@endsection
