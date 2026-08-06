@extends('layouts.fleet')

@section('title', 'Fuel Report')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-reports.css') }}?v=5">
@endpush

@section('content')
<div class="fleet-report-shell">
    <form method="GET" action="{{ route('reports.fuel') }}" class="fleet-report-filters fleet-report-filters-income">
        <div class="fleet-report-filter">
            <label for="fuel-date-from">From Date</label>
            <input type="date" id="fuel-date-from" name="date_from" class="fleet-report-input" value="{{ $dateFrom }}">
        </div>
        <div class="fleet-report-filter">
            <label for="fuel-date-to">To Date</label>
            <input type="date" id="fuel-date-to" name="date_to" class="fleet-report-input" value="{{ $dateTo }}">
        </div>
        <div class="fleet-report-filter">
            <label for="fuel-vehicle">Vehicle</label>
            <select id="fuel-vehicle" name="vehicle_id" class="fleet-report-input">
                <option value="">All Vehicles</option>
                @foreach ($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" {{ (string) $vehicleId === (string) $vehicle->id ? 'selected' : '' }}>
                        {{ $vehicle->displayName() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="fleet-report-filter fleet-report-filter-action">
            <label>&nbsp;</label>
            <button type="submit" class="fleet-btn fleet-btn-primary fleet-report-submit">
                Generate
            </button>
        </div>
    </form>

    <div class="fleet-report-export-bar">
        <button type="button" class="fleet-export-btn fleet-export-pdf" id="fuel-report-pdf-btn">
            <i class="fa fa-file-pdf-o"></i> Export PDF
        </button>
    </div>

    <div class="fleet-report-grid">
        <div class="fleet-panel fleet-report-chart-card">
            <div class="fleet-report-card-head">Daily Fuel Consumption (Liters)</div>
            <div class="fleet-report-chart-wrap">
                <canvas id="fuel-liters-chart"></canvas>
            </div>
        </div>

        <div class="fleet-panel fleet-report-chart-card">
            <div class="fleet-report-card-head">Daily Fuel Cost</div>
            <div class="fleet-report-chart-wrap">
                <canvas id="fuel-cost-chart"></canvas>
            </div>
        </div>
    </div>

    <div class="fleet-report-metrics is-two-col">
        <div class="fleet-report-metric is-fuel-liters">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Fuel Consumed</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['total_liters'], 2) }} Liters</strong>
            </div>
            <i class="fa fa-tint fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-fuel-cost">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Cost</span>
                <strong class="fleet-report-metric-value">{{ format_kes($summary['total_cost']) }}</strong>
            </div>
            <i class="fa fa-money fleet-report-metric-icon"></i>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function () {
    var chart = @json($chart);

    var litersCtx = document.getElementById('fuel-liters-chart');
    if (litersCtx && typeof Chart !== 'undefined') {
        new Chart(litersCtx, {
            type: 'bar',
            data: {
                labels: chart.labels,
                datasets: [{
                    label: 'Liters',
                    data: chart.liters,
                    backgroundColor: '#00c0ef',
                    borderRadius: 4,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    var costCtx = document.getElementById('fuel-cost-chart');
    if (costCtx && typeof Chart !== 'undefined') {
        new Chart(costCtx, {
            type: 'bar',
            data: {
                labels: chart.labels,
                datasets: [{
                    label: 'Cost',
                    data: chart.cost,
                    backgroundColor: '#00a65a',
                    borderRadius: 4,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    document.getElementById('fuel-report-pdf-btn').addEventListener('click', function () {
        var params = new URLSearchParams();
        params.set('date_from', @json($dateFrom));
        params.set('date_to', @json($dateTo));
        var vehicle = document.getElementById('fuel-vehicle');
        if (vehicle && vehicle.value !== '') {
            params.set('vehicle_id', vehicle.value);
        }
        window.open(@json(route('reports.fuel.export-pdf')) + '?' + params.toString(), '_blank');
    });
})();
</script>
@endpush
