@extends('layouts.fleet')

@section('title', 'Tyre Management')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-pms.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/fleet-tyres.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Tyre Management</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Tyres</li>
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

<div class="fleet-pms-layout">
    <div class="fleet-pms-left">
        <div class="fleet-panel fleet-pms-form-card">
            <div class="fleet-pms-card-head">Add New Tyre</div>
            <div class="fleet-pms-card-body">
                <form method="POST" action="{{ route('tyres.store') }}" class="fleet-pms-form fleet-tyre-form">
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
                        <label>Position</label>
                        <select name="position" class="fleet-pms-input" required>
                            @foreach ($positions as $position)
                                <option value="{{ $position }}" {{ old('position', 'Front-Left') === $position ? 'selected' : '' }}>{{ $position }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fleet-pms-field">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number" class="fleet-pms-input" value="{{ old('serial_number') }}" placeholder="Unique SN" required>
                    </div>

                    <div class="fleet-pms-field">
                        <label>Brand / Model</label>
                        <input type="text" name="brand_model" class="fleet-pms-input" value="{{ old('brand_model') }}" placeholder="e.g. Michelin X Multi">
                    </div>

                    <div class="fleet-pms-field">
                        <label>Install Date</label>
                        <input type="date" name="install_date" class="fleet-pms-input" value="{{ old('install_date', now()->format('Y-m-d')) }}" required>
                    </div>

                    <div class="fleet-pms-field">
                        <label>Install Odometer</label>
                        <input type="number" min="0" name="install_odometer" class="fleet-pms-input" value="{{ old('install_odometer') }}" placeholder="e.g. 10000">
                    </div>

                    <div class="fleet-pms-field">
                        <label>Tyre Cost</label>
                        <div class="fleet-tyre-cost-wrap">
                            <span class="fleet-tyre-cost-prefix">{{ kes_symbol() }}</span>
                            <input type="number" step="0.01" min="0" name="cost" class="fleet-pms-input fleet-tyre-cost-input" value="{{ old('cost') }}" placeholder="0.00">
                        </div>
                    </div>

                    <button type="submit" class="fleet-btn fleet-btn-primary fleet-pms-submit"><i class="fa fa-plus"></i> Add Tyre</button>
                </form>
            </div>
        </div>
    </div>

    <div class="fleet-panel fleet-pms-rules-card fleet-tyre-table-card">
        <form method="GET" action="{{ route('tyres.index') }}" class="fleet-tyre-search">
            <label for="tyre-search">Search:</label>
            <input type="text" id="tyre-search" name="search" class="fleet-tyre-search-input" value="{{ $search }}">
        </form>

        <div class="fleet-table-wrap">
            <table class="fleet-tyre-table">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Position</th>
                        <th>Serial No.</th>
                        <th>Brand</th>
                        <th>Cost</th>
                        <th>Install Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tyres as $tyre)
                    <tr>
                        <td>
                            <div class="fleet-tyre-vehicle-name">{{ optional($tyre->vehicle)->displayName() ?: '-' }}</div>
                            <div class="fleet-tyre-vehicle-meta">{{ optional($tyre->vehicle)->registration_number }}</div>
                        </td>
                        <td>{{ $tyre->position }}</td>
                        <td>{{ $tyre->serial_number }}</td>
                        <td>{{ $tyre->brand_model ?: '-' }}</td>
                        <td>{{ $tyre->formattedCost() }}</td>
                        <td>
                            <div>{{ $tyre->formattedInstallDate() }}</div>
                            <div class="fleet-tyre-odometer">{{ $tyre->formattedOdometer() }}</div>
                        </td>
                        <td>
                            <form action="{{ route('tyres.destroy', $tyre) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this tyre record?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="fleet-empty-row">No tyres recorded yet. Add your first tyre on the left.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tyres->hasPages())
        <div class="fleet-tyre-pagination">
            {{ $tyres->links('fleet.incidents.partials.pagination') }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var searchInput = document.getElementById('tyre-search');
    var searchForm = searchInput ? searchInput.closest('form') : null;
    var timer;

    if (searchInput && searchForm) {
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                searchForm.submit();
            }, 400);
        });
    }
})();
</script>
@endpush
