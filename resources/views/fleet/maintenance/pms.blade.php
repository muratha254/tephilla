@extends('layouts.fleet')

@section('title', 'Preventive Maintenance Scheduler')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-maintenance.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('css/fleet-pms.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Preventive Maintenance Scheduler (PMS)</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('maintenance.index') }}">Maintenance</a></li>
        <li>PMS</li>
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

@if (session('pms_due_rules'))
<div class="fleet-alert fleet-alert-warning">
    <strong>{{ count(session('pms_due_rules')) }} rule(s) due for service:</strong>
    <ul>
        @foreach (session('pms_due_rules') as $dueRule)
            <li>{{ $dueRule['vehicle'] }} ({{ $dueRule['registration'] }}) — {{ $dueRule['service'] }} — last: {{ $dueRule['last_service'] }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="fleet-pms-layout">
    <div class="fleet-pms-left">
        <div class="fleet-panel fleet-pms-form-card">
            <div class="fleet-pms-card-head">Add New PMS Rule</div>
            <div class="fleet-pms-card-body">
                <form method="POST" action="{{ route('maintenance.pms.store') }}" class="fleet-pms-form">
                    @csrf
                    <div class="fleet-pms-field">
                        <label>Vehicle</label>
                        <select name="fleet_vehicle_id" class="fleet-pms-input" required>
                            <option value="">Select Vehicle</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" {{ (string) old('fleet_vehicle_id') === (string) $vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->displayName() }} ({{ $vehicle->registration_number }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fleet-pms-field">
                        <label>Service Name</label>
                        <input type="text" name="service_name" class="fleet-pms-input" value="{{ old('service_name') }}" placeholder="e.g. Oil Change, Tyre Rotation" required>
                    </div>

                    <div class="fleet-pms-row">
                        <div class="fleet-pms-field">
                            <label>Interval (KM)</label>
                            <input type="number" min="1" name="interval_km" class="fleet-pms-input" value="{{ old('interval_km') }}" placeholder="e.g. 10000">
                        </div>
                        <div class="fleet-pms-field">
                            <label>Interval (Days)</label>
                            <input type="number" min="1" name="interval_days" class="fleet-pms-input" value="{{ old('interval_days') }}" placeholder="e.g. 180">
                        </div>
                    </div>

                    <p class="fleet-pms-hint">Leave blank if not applicable. At least one interval is recommended.</p>

                    <button type="submit" class="fleet-btn fleet-btn-primary fleet-pms-submit"><i class="fa fa-plus"></i> Add Rule</button>
                </form>
            </div>
        </div>

        <div class="fleet-panel fleet-pms-check-card">
            <div class="fleet-pms-card-body">
                <p>Manually check all rules against current vehicle status.</p>
                <form method="POST" action="{{ route('maintenance.pms.check') }}">
                    @csrf
                    <button type="submit" class="fleet-btn fleet-pms-check-btn"><i class="fa fa-refresh"></i> Run Manual Check</button>
                </form>
            </div>
        </div>
    </div>

    <div class="fleet-panel fleet-pms-rules-card">
        <div class="fleet-pms-rules-head">Existing Rules</div>
        <div class="fleet-table-wrap">
            <table class="fleet-pms-table">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Service</th>
                        <th>Interval</th>
                        <th>Last Service</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                    <tr>
                        <td>
                            <div class="fleet-pms-vehicle-name">{{ optional($rule->vehicle)->displayName() ?: '-' }}</div>
                            <div class="fleet-pms-vehicle-meta">{{ optional($rule->vehicle)->registration_number }}</div>
                        </td>
                        <td>{{ $rule->service_name }}</td>
                        <td>{{ $rule->formattedInterval() }}</td>
                        <td>{{ $rule->formattedLastService() }}</td>
                        <td>
                            <form action="{{ route('maintenance.pms.destroy', $rule) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this PMS rule?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="fleet-empty-row">No PMS rules yet. Add your first rule on the left.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
