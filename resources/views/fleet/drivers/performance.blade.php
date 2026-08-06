@extends('layouts.fleet')

@section('title', 'Driver Performance')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-reports.css') }}?v=9">
@endpush

@section('content')
<div class="fleet-driver-report-shell">
    <form method="GET" action="{{ route('drivers.performance') }}" class="fleet-report-filters fleet-report-filters-income">
        <div class="fleet-report-filter">
            <label for="performance-date-from">From Date</label>
            <input type="date" id="performance-date-from" name="date_from" class="fleet-report-input" value="{{ $dateFrom }}">
        </div>
        <div class="fleet-report-filter">
            <label for="performance-date-to">To Date</label>
            <input type="date" id="performance-date-to" name="date_to" class="fleet-report-input" value="{{ $dateTo }}">
        </div>
        <div class="fleet-report-filter fleet-report-filter-action">
            <label>&nbsp;</label>
            <button type="submit" class="fleet-btn fleet-btn-primary fleet-report-submit">
                <i class="fa fa-search"></i> Generate
            </button>
        </div>
    </form>

    <div class="fleet-report-metrics fleet-report-metrics-trips">
        <div class="fleet-report-metric is-bookings">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Active Drivers</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['active_drivers']) }}</strong>
            </div>
            <i class="fa fa-id-card-o fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-bookings">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Trips</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['total_trips']) }}</strong>
            </div>
            <i class="fa fa-road fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-revenue">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Completed Trips</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['completed_trips']) }}</strong>
            </div>
            <i class="fa fa-check-circle fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-revenue">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Revenue</span>
                <strong class="fleet-report-metric-value">{{ format_kes($summary['total_revenue']) }}</strong>
            </div>
            <i class="fa fa-money fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-distance">
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

    @if ($topDriver)
        <div class="fleet-driver-report-top">
            <div class="fleet-driver-top-card">
                <i class="fa fa-trophy fleet-driver-top-crown"></i>
                <div class="fleet-driver-top-avatar">{{ $topDriver['driver']->avatarInitials() }}</div>
                <h2>{{ $topDriver['driver']->name }}</h2>
                <p>Top Performer</p>
                <span class="fleet-driver-top-pill">{{ number_format($topDriver['total_trips']) }} Trips</span>
            </div>

            <div class="fleet-panel fleet-driver-chart-card">
                <div class="fleet-report-card-head">Trips by Driver</div>
                <div class="fleet-driver-chart-wrap">
                    <canvas id="driver-performance-trips-chart"></canvas>
                </div>
            </div>
        </div>
    @else
        <div class="fleet-panel fleet-driver-chart-card">
            <div class="fleet-report-card-head">Trips by Driver</div>
            <div class="fleet-driver-chart-wrap">
                <canvas id="driver-performance-trips-chart"></canvas>
            </div>
        </div>
    @endif

    <div class="fleet-panel fleet-driver-chart-card">
        <div class="fleet-report-card-head">Revenue by Driver</div>
        <div class="fleet-driver-chart-wrap">
            <canvas id="driver-performance-revenue-chart"></canvas>
        </div>
    </div>

    <div class="fleet-panel fleet-driver-table-card">
        <div class="fleet-driver-table-head">
            <h3><i class="fa fa-bar-chart"></i> Driver Performance Breakdown</h3>
        </div>

        <div class="fleet-table-wrap">
            <table class="fleet-vendor-table fleet-driver-report-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Driver</th>
                        <th>Status</th>
                        <th>Trips</th>
                        <th>Completed</th>
                        <th>Ongoing</th>
                        <th>Cancelled</th>
                        <th>Completion</th>
                        <th>Distance</th>
                        <th>Duration</th>
                        <th>Revenue</th>
                        <th>Avg / Trip</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <div class="fleet-driver-report-name">
                                    <span class="fleet-driver-report-avatar">{{ $row['driver']->avatarInitials() }}</span>
                                    <a href="{{ route('drivers.show', $row['driver']) }}">{{ $row['driver']->name }}</a>
                                </div>
                            </td>
                            <td>{{ $row['driver']->status ?: '-' }}</td>
                            <td><span class="fleet-driver-trip-badge">{{ number_format($row['total_trips']) }}</span></td>
                            <td>{{ number_format($row['completed_trips']) }}</td>
                            <td>{{ number_format($row['ongoing_trips']) }}</td>
                            <td>{{ number_format($row['cancelled_trips']) }}</td>
                            <td>{{ number_format($row['completion_rate'], 1) }}%</td>
                            <td>{{ number_format($row['distance_km'], 2) }} km</td>
                            <td>{{ number_format($row['duration_hours'], 2) }} Hrs</td>
                            <td class="is-revenue">{{ format_kes($row['revenue']) }}</td>
                            <td>{{ $row['total_trips'] > 0 ? format_kes($row['avg_revenue']) : '-' }}</td>
                            <td>
                                @if ($row['total_trips'] > 0)
                                    <span class="fleet-driver-rating {{ $row['completion_rate'] >= 80 ? 'is-good' : 'is-bad' }}">
                                        <i class="fa fa-thumbs-{{ $row['completion_rate'] >= 80 ? 'up' : 'down' }}"></i>
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="fleet-empty-row">No drivers found.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->where('total_trips', '>', 0)->isNotEmpty())
                    <tfoot>
                        <tr class="fleet-trips-report-total-row">
                            <td colspan="3"><strong>Totals</strong></td>
                            <td><strong>{{ number_format($summary['total_trips']) }}</strong></td>
                            <td><strong>{{ number_format($summary['completed_trips']) }}</strong></td>
                            <td colspan="2"></td>
                            <td></td>
                            <td><strong>{{ number_format($summary['total_distance'], 2) }} km</strong></td>
                            <td><strong>{{ number_format($summary['total_duration_hours'], 2) }} Hrs</strong></td>
                            <td class="is-revenue"><strong>{{ format_kes($summary['total_revenue']) }}</strong></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function () {
    var chart = @json($chart);

    if (!chart.labels.length) {
        return;
    }

    var tripsCtx = document.getElementById('driver-performance-trips-chart');
    if (tripsCtx && typeof Chart !== 'undefined') {
        new Chart(tripsCtx, {
            type: 'bar',
            data: {
                labels: chart.labels,
                datasets: [{
                    label: 'Trips',
                    data: chart.trip_counts,
                    backgroundColor: '#3c8dbc',
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

    var revenueCtx = document.getElementById('driver-performance-revenue-chart');
    if (revenueCtx && typeof Chart !== 'undefined') {
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: chart.labels,
                datasets: [{
                    label: 'Revenue (KSh)',
                    data: chart.revenue,
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
})();
</script>
@endpush
