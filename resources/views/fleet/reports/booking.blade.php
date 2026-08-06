@extends('layouts.fleet')

@section('title', 'Trips Report')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-reports.css') }}?v=9">
@endpush

@section('content')
<div class="fleet-report-shell">
    <form method="GET" action="{{ route('reports.booking') }}" class="fleet-report-filters">
        <div class="fleet-report-filter">
            <label for="report-date-from">From Date</label>
            <input type="date" id="report-date-from" name="date_from" class="fleet-report-input" value="{{ $dateFrom }}">
        </div>
        <div class="fleet-report-filter">
            <label for="report-date-to">To Date</label>
            <input type="date" id="report-date-to" name="date_to" class="fleet-report-input" value="{{ $dateTo }}">
        </div>
        <div class="fleet-report-filter">
            <label for="report-vehicle">Vehicle</label>
            <select id="report-vehicle" name="vehicle_id" class="fleet-report-input">
                <option value="">All Vehicles</option>
                @foreach ($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}" {{ (string) $vehicleId === (string) $vehicle->id ? 'selected' : '' }}>
                        {{ $vehicle->displayName() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="fleet-report-filter">
            <label for="report-status">Trip Status</label>
            <select id="report-status" name="status" class="fleet-report-input">
                <option value="">All Status</option>
                <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="ongoing" {{ $statusFilter === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                <option value="yet_to_start" {{ $statusFilter === 'yet_to_start' ? 'selected' : '' }}>Yet to Start</option>
                <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>
        <div class="fleet-report-filter">
            <label for="report-customer">Customer</label>
            <select id="report-customer" name="customer_id" class="fleet-report-input">
                <option value="">All Customers</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" {{ (string) $customerId === (string) $customer->id ? 'selected' : '' }}>
                        {{ $customer->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="fleet-report-filter fleet-report-filter-action">
            <label>&nbsp;</label>
            <button type="submit" class="fleet-btn fleet-btn-primary fleet-report-submit">
                <i class="fa fa-search"></i> Report
            </button>
        </div>
    </form>

    <div class="fleet-report-export-bar">
        <button type="button" class="fleet-export-btn fleet-export-pdf" id="trips-report-pdf-btn">
            <i class="fa fa-file-pdf-o"></i> Export PDF
        </button>
    </div>

    <div class="fleet-report-grid">
        <div class="fleet-panel fleet-report-chart-card">
            <div class="fleet-report-card-head">Booking Status Distribution</div>
            <div class="fleet-report-chart-wrap">
                <canvas id="booking-status-chart"></canvas>
            </div>
            <div class="fleet-report-legend">
                <span><i class="fleet-report-dot completed"></i> Completed</span>
                <span><i class="fleet-report-dot ongoing"></i> Ongoing</span>
                <span><i class="fleet-report-dot pending"></i> Yet to Start</span>
                <span><i class="fleet-report-dot cancelled"></i> Cancelled</span>
            </div>
        </div>

        <div class="fleet-panel fleet-report-chart-card">
            <div class="fleet-report-card-head">Revenue Trend (Daily)</div>
            <div class="fleet-report-chart-wrap is-line">
                <canvas id="booking-revenue-chart"></canvas>
            </div>
        </div>
    </div>

    <div class="fleet-report-metrics fleet-report-metrics-trips">
        <div class="fleet-report-metric is-bookings">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Trips</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['total_bookings']) }}</strong>
            </div>
            <i class="fa fa-clipboard fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-revenue">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Trip Amount</span>
                <strong class="fleet-report-metric-value">{{ format_kes($summary['total_revenue']) }}</strong>
            </div>
            <i class="fa fa-money fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-costs">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Expenses</span>
                <strong class="fleet-report-metric-value">{{ format_kes($summary['total_expenses']) }}</strong>
            </div>
            <i class="fa fa-credit-card fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-profit">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Profit</span>
                <strong class="fleet-report-metric-value">{{ format_kes($summary['total_profit']) }}</strong>
            </div>
            <i class="fa fa-line-chart fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-distance">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Distance</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['total_distance'], 2) }} km</strong>
            </div>
            <i class="fa fa-road fleet-report-metric-icon"></i>
        </div>
    </div>

    <div class="fleet-panel fleet-reminder-report-table-card">
        <div class="fleet-reminder-report-table-head">
            <h3><i class="fa fa-list"></i> Trip Profit Breakdown</h3>
        </div>

        <div class="fleet-table-wrap">
            <table class="fleet-vendor-table fleet-trips-report-table">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Trip Ref</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Route</th>
                        <th>Status</th>
                        <th>Trip Amount</th>
                        <th>Expenses</th>
                        <th>Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tripRows as $row)
                        <tr>
                            <td>{{ $row['serial'] }}</td>
                            <td><a href="{{ $row['trip_url'] }}" class="fleet-trip-report-link">{{ $row['trip_code'] }}</a></td>
                            <td>{{ $row['start_date'] }}</td>
                            <td>{{ $row['customer_name'] }}</td>
                            <td>{{ $row['vehicle_name'] }}</td>
                            <td>{{ $row['route'] }}</td>
                            <td><span class="fleet-trip-status-tag {{ $row['status_class'] }}">{{ $row['status'] }}</span></td>
                            <td>{{ format_kes($row['trip_amount']) }}</td>
                            <td>{{ format_kes($row['expenses']) }}</td>
                            <td><span class="fleet-trip-report-profit {{ $row['profit_class'] }}">{{ format_kes($row['profit']) }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="fleet-empty-row">No trips found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($tripRows->isNotEmpty())
                    <tfoot>
                        <tr class="fleet-trips-report-total-row">
                            <td colspan="7"><strong>Totals</strong></td>
                            <td><strong>{{ format_kes($summary['total_revenue']) }}</strong></td>
                            <td><strong>{{ format_kes($summary['total_expenses']) }}</strong></td>
                            <td><strong class="fleet-trip-report-profit {{ $summary['total_profit'] >= 0 ? 'is-profit-positive' : 'is-profit-negative' }}">{{ format_kes($summary['total_profit']) }}</strong></td>
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
    var statusCounts = @json($statusCounts);
    var revenueTrend = @json($revenueTrend);

    var statusCtx = document.getElementById('booking-status-chart');
    if (statusCtx && typeof Chart !== 'undefined') {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Ongoing', 'Yet to Start', 'Cancelled'],
                datasets: [{
                    data: [
                        statusCounts.Completed || 0,
                        statusCounts.Ongoing || 0,
                        statusCounts['Yet to Start'] || 0,
                        statusCounts.Cancelled || 0
                    ],
                    backgroundColor: ['#00a65a', '#00c0ef', '#f39c12', '#dd4b39'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { display: false } }
            }
        });
    }

    var revenueCtx = document.getElementById('booking-revenue-chart');
    if (revenueCtx && typeof Chart !== 'undefined') {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueTrend.labels,
                datasets: [{
                    label: 'Revenue',
                    data: revenueTrend.values,
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
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    document.getElementById('trips-report-pdf-btn').addEventListener('click', function () {
        var params = new URLSearchParams();
        params.set('date_from', @json($dateFrom));
        params.set('date_to', @json($dateTo));

        var vehicle = document.getElementById('report-vehicle');
        if (vehicle && vehicle.value !== '') {
            params.set('vehicle_id', vehicle.value);
        }

        var status = document.getElementById('report-status');
        if (status && status.value !== '') {
            params.set('status', status.value);
        }

        var customer = document.getElementById('report-customer');
        if (customer && customer.value !== '') {
            params.set('customer_id', customer.value);
        }

        window.open(@json(route('reports.booking.export-pdf')) + '?' + params.toString(), '_blank');
    });
})();
</script>
@endpush
