@extends('layouts.fleet')

@section('title', 'Fuel Info')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-fuel.css') }}?v=4">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Fuel Info</h1>
        <a href="{{ route('fuel.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add Fuel</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Fuel Info</li>
    </ul>
</div>

<div class="fleet-panel fleet-fuel-panel">
    <div class="fleet-fuel-toolbar">
        <div class="fleet-fuel-summary">
            <div class="fleet-fuel-summary-item">
                <strong>{{ $recordCount }}</strong> Records
            </div>
            <div class="fleet-fuel-summary-item fleet-fuel-summary-cost">
                <strong>{{ format_kes($totalCost) }}</strong> Total Cost
            </div>
            <div class="fleet-fuel-summary-item">
                <strong>{{ number_format($totalVolume, 0) }}</strong> Total Volume
            </div>
        </div>

        <form method="GET" action="{{ route('fuel.index') }}" class="fleet-fuel-filters" id="fuel-filter-form">
            <div class="fleet-fuel-date-filters">
                <label class="fleet-fuel-date-label" for="fuel-date-from">From</label>
                <input type="date" id="fuel-date-from" name="date_from" class="fleet-fuel-date-input" value="{{ $dateFrom }}">
                <label class="fleet-fuel-date-label" for="fuel-date-to">To</label>
                <input type="date" id="fuel-date-to" name="date_to" class="fleet-fuel-date-input" value="{{ $dateTo }}">
            </div>
            <div class="fleet-search-wrap">
                <i class="fa fa-search"></i>
                <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search Fuel...">
            </div>
            @if ($dateFrom !== '' || $dateTo !== '' || $search !== '')
            <a href="{{ route('fuel.index') }}" class="fleet-btn fleet-btn-default fleet-fuel-clear-btn">Clear</a>
            @endif
        </form>
    </div>

    <div class="fleet-table-wrap">
        <table class="fleet-fuel-list-table">
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Date</th>
                    <th>Quantity</th>
                    <th>Total Cost</th>
                    <th>Odometer</th>
                    <th>Driver</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($refills as $refill)
                <tr>
                    <td>
                        <div class="fleet-fuel-table-vehicle">
                            <i class="fa fa-truck"></i>
                            <div>
                                <div class="fleet-fuel-vehicle-name">{{ optional($refill->vehicle)->displayName() ?: '-' }}</div>
                                <div class="fleet-fuel-vehicle-meta">{{ optional($refill->vehicle)->registration_number }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $refill->badgeDate() }}</td>
                    <td>{{ $refill->formattedLiters() }}</td>
                    <td class="fleet-fuel-cost-cell">{{ $refill->formattedCost() }}</td>
                    <td>{{ $refill->odometer ? number_format($refill->odometer) : '-' }}</td>
                    <td>{{ $refill->driverLabel() }}</td>
                    <td>
                        @include('fleet.fuel.partials.row_actions', ['refill' => $refill])
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="fleet-empty-row">No fuel records found. <a href="{{ route('fuel.create') }}">Add fuel</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('fuel-filter-form');
    var dateFrom = document.getElementById('fuel-date-from');
    var dateTo = document.getElementById('fuel-date-to');

    if (!form) {
        return;
    }

    [dateFrom, dateTo].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener('change', function () {
            form.submit();
        });
    });
})();
</script>
@endpush
