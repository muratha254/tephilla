@extends('layouts.fleet')

@section('title', 'Driver Report')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-reports.css') }}?v=6">
@endpush

@section('content')
<div class="fleet-report-shell">
    <form method="GET" action="{{ route('reports.driver') }}" class="fleet-report-filters fleet-report-filters-income">
        <div class="fleet-report-filter">
            <label for="driver-date-from">From Date</label>
            <input type="date" id="driver-date-from" name="date_from" class="fleet-report-input" value="{{ $dateFrom }}">
        </div>
        <div class="fleet-report-filter">
            <label for="driver-date-to">To Date</label>
            <input type="date" id="driver-date-to" name="date_to" class="fleet-report-input" value="{{ $dateTo }}">
        </div>
        <div class="fleet-report-filter">
            <label for="driver-filter">Driver</label>
            <select id="driver-filter" name="driver_id" class="fleet-report-input">
                <option value="">All Drivers</option>
                @foreach ($drivers as $driver)
                    <option value="{{ $driver->id }}" {{ (string) $driverId === (string) $driver->id ? 'selected' : '' }}>
                        {{ $driver->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="fleet-report-filter fleet-report-filter-action">
            <label>&nbsp;</label>
            <button type="submit" class="fleet-btn fleet-btn-primary fleet-report-submit">
                <i class="fa fa-search"></i> Generate
            </button>
        </div>
    </form>

    <div class="fleet-report-export-bar">
        <button type="button" class="fleet-export-btn fleet-export-pdf" id="driver-report-pdf-btn">
            <i class="fa fa-file-pdf-o"></i> Export PDF
        </button>
    </div>

    <div class="fleet-report-grid">
        <div class="fleet-panel fleet-report-chart-card">
            <div class="fleet-report-card-head">Daily Trip Count</div>
            <div class="fleet-report-chart-wrap">
                <canvas id="driver-trip-count-chart"></canvas>
            </div>
        </div>

        <div class="fleet-panel fleet-report-chart-card">
            <div class="fleet-report-card-head">Daily Distance (km)</div>
            <div class="fleet-report-chart-wrap is-line">
                <canvas id="driver-distance-chart"></canvas>
            </div>
        </div>
    </div>

    <div class="fleet-report-metrics">
        <div class="fleet-report-metric is-bookings">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Trips</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['total_trips']) }}</strong>
            </div>
            <i class="fa fa-road fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-revenue">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Distance</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['total_distance'], 2) }} km</strong>
            </div>
            <i class="fa fa-map-marker fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-distance">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Duration</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['total_duration_hours'], 2) }} Hrs</strong>
            </div>
            <i class="fa fa-clock-o fleet-report-metric-icon"></i>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function () {
    var chart = @json($chart);

    var tripCtx = document.getElementById('driver-trip-count-chart');
    if (tripCtx && typeof Chart !== 'undefined') {
        new Chart(tripCtx, {
            type: 'bar',
            data: {
                labels: chart.labels,
                datasets: [{
                    label: 'Trips',
                    data: chart.trip_counts,
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
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    var distanceCtx = document.getElementById('driver-distance-chart');
    if (distanceCtx && typeof Chart !== 'undefined') {
        new Chart(distanceCtx, {
            type: 'line',
            data: {
                labels: chart.labels,
                datasets: [{
                    label: 'Distance (km)',
                    data: chart.distance,
                    borderColor: '#00a65a',
                    backgroundColor: 'rgba(0, 166, 90, 0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: '#00a65a'
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

    document.getElementById('driver-report-pdf-btn').addEventListener('click', function () {
        var params = new URLSearchParams();
        params.set('date_from', @json($dateFrom));
        params.set('date_to', @json($dateTo));
        var driver = document.getElementById('driver-filter');
        if (driver && driver.value !== '') {
            params.set('driver_id', driver.value);
        }
        window.open(@json(route('reports.driver.export-pdf')) + '?' + params.toString(), '_blank');
    });
})();
</script>
@endpush
