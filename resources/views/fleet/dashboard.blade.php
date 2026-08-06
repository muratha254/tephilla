@extends('layouts.fleet')

@section('title', 'Dashboard')

@section('content')
<h1 class="fleet-page-title">Dashboard</h1>
<ul class="fleet-breadcrumb">
    <li>Home</li>
    <li>Dashboard</li>
</ul>

<div class="fleet-stat-row">
    <div class="fleet-stat-card cyan">
        <div class="fleet-stat-value">{{ $stats['vehicles'] }}</div>
        <div class="fleet-stat-label">Total Vehicles</div>
        <i class="fa fa-truck icon"></i>
    </div>
    <div class="fleet-stat-card green">
        <div class="fleet-stat-value">{{ $stats['drivers'] }}</div>
        <div class="fleet-stat-label">Total Drivers</div>
        <i class="fa fa-id-card icon"></i>
    </div>
    <div class="fleet-stat-card yellow">
        <div class="fleet-stat-value">{{ $stats['customers'] }}</div>
        <div class="fleet-stat-label">Total Customers</div>
        <i class="fa fa-users icon"></i>
    </div>
    <div class="fleet-stat-card red">
        <div class="fleet-stat-value">{{ $stats['today_trips'] }}</div>
        <div class="fleet-stat-label">Today Trips</div>
        <i class="fa fa-clock-o icon"></i>
    </div>
</div>

<div class="fleet-panel-row">
    <div class="fleet-panel">
        <div class="fleet-panel-header">Fleet Statistics</div>
        <div class="fleet-panel-body">
            <div class="fleet-fleet-stats">
                <div>
                    <div class="count">{{ $stats['vehicles'] }}</div>
                    <div class="sub">Number of Vehicles</div>
                </div>
            </div>
            <div class="fleet-status-list">
                <span><i class="fleet-dot green"></i> {{ $fleet['moving'] }} Moving</span>
                <span><i class="fleet-dot red"></i> {{ $fleet['parked'] }} Parked</span>
                <span><i class="fleet-dot gray"></i> {{ $fleet['offline'] }} Offline</span>
                <span><i class="fleet-dot yellow"></i> {{ $fleet['no_data'] }} No Data</span>
            </div>
        </div>
    </div>

    <div class="fleet-panel">
        <div class="fleet-panel-header">Fleet Utilization</div>
        <div class="fleet-panel-body">
            <div class="fleet-chart-wrap">
                <canvas id="fleetUtilChart"></canvas>
            </div>
            <div class="fleet-util-legend">
                <span><i class="fleet-dot green"></i> In Trip: {{ $fleet['in_trip'] }}</span>
                <span><i class="fleet-dot gray"></i> Available: {{ $fleet['available'] }}</span>
            </div>
        </div>
    </div>

    <div class="fleet-panel">
        <div class="fleet-panel-header">Fuel Consumption (7 Days)</div>
        <div class="fleet-panel-body">
            <div class="fleet-chart-wrap">
                <canvas id="fuelChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="fleet-bottom-row">
    <div class="fleet-panel">
        <div class="fleet-panel-header">Transactions</div>
        <div class="fleet-panel-body">
            @forelse ($recentTransactions as $payment)
                <div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid #f0f2f5;font-size:13px;">
                    <span>{{ $payment->customer?->name ?: 'Customer' }} · {{ $payment->trip?->displayTripCode() ?: 'Trip' }}</span>
                    <strong>{{ format_kes($payment->amount) }}</strong>
                </div>
            @empty
                <p class="text-muted" style="margin:0;">No recent transactions to display.</p>
            @endforelse
        </div>
    </div>

    <div class="fleet-panel yellow-head">
        <div class="fleet-panel-header">Today Trip Summary</div>
        <div class="fleet-panel-body">
            <p style="margin:0 0 8px;"><strong>{{ $stats['today_trips'] }}</strong> trips scheduled today.</p>
            @forelse ($todayTripRows as $trip)
                <div style="padding:6px 0;border-bottom:1px solid #f0f2f5;font-size:13px;">
                    <strong>{{ $trip->displayTripCode() }}</strong>
                    <span class="text-muted"> · {{ $trip->customer?->name ?: ($trip->customer_name ?: 'No customer') }}</span><br>
                    <span class="text-muted">{{ $trip->routeLocationShort($trip->pickup_location) }} → {{ $trip->routeLocationShort($trip->drop_location) }}</span>
                </div>
            @empty
                <p class="text-muted" style="margin:0;">Trip details will appear here once trips are created.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="fleet-dashboard-grid">
    <div class="fleet-panel">
        <div class="fleet-panel-header fleet-panel-header-split">
            <span><i class="fa fa-pie-chart"></i> Cost Overview ({{ $costOverview['month_label'] }})</span>
            <span class="fleet-badge fleet-badge-green">{{ format_kes($costOverview['total']) }}</span>
        </div>
        <div class="fleet-panel-body">
            <div class="fleet-cost-legend">
                <span><i class="fleet-dot" style="background:#3c8dbc;"></i> Fuel: {{ format_kes($costOverview['fuel'], 0) }}</span>
                <span><i class="fleet-dot red"></i> Maintenance: {{ format_kes($costOverview['maintenance'], 0) }}</span>
                <span><i class="fleet-dot yellow"></i> Expenses: {{ format_kes($costOverview['expenses'], 0) }}</span>
            </div>
            <div class="fleet-chart-wrap fleet-chart-wrap-sm">
                <canvas id="costOverviewChart"></canvas>
            </div>
        </div>
    </div>

    <div class="fleet-panel">
        <div class="fleet-panel-header">
            <i class="fa fa-exclamation-circle" style="color:#dd4b39;"></i> Needs Attention
        </div>
        <div class="fleet-panel-body fleet-empty-state">
            @if ($openIncidents > 0)
                <p><strong>{{ $openIncidents }}</strong> open incident(s) need review.</p>
            @else
                <p>No Open Issues</p>
            @endif
            <a href="{{ route('incidents.index') }}" class="fleet-link">View All Incidents</a>
        </div>
    </div>

    <div class="fleet-panel">
        <div class="fleet-panel-header">
            <i class="fa fa-bar-chart"></i> Fleet Performance ({{ \Illuminate\Support\Carbon::now()->format('M') }})
        </div>
        <div class="fleet-panel-body fleet-metrics-list">
            <div class="fleet-metric-item">
                <span class="fleet-metric-icon"><i class="fa fa-road"></i></span>
                <div>
                    <div class="fleet-metric-label">Total Distance Month</div>
                    <div class="fleet-metric-value">{{ number_format($performance['total_distance']) }} km</div>
                </div>
            </div>
            <div class="fleet-metric-item">
                <span class="fleet-metric-icon"><i class="fa fa-tachometer"></i></span>
                <div>
                    <div class="fleet-metric-label">Avg Efficiency</div>
                    <div class="fleet-metric-value">{{ number_format($performance['avg_efficiency']) }} km/L</div>
                </div>
            </div>
            <div class="fleet-metric-item">
                <span class="fleet-metric-icon"><i class="fa fa-money"></i></span>
                <div>
                    <div class="fleet-metric-label">Cost per KM</div>
                    <div class="fleet-metric-value">{{ format_kes($performance['cost_per_km'], 0) }} /km</div>
                </div>
            </div>
        </div>
    </div>

    <div class="fleet-panel">
        <div class="fleet-panel-header">
            <i class="fa fa-history"></i> Recent Activity
        </div>
        <div class="fleet-panel-body fleet-empty-state">
            <div class="fleet-timeline-placeholder"></div>
            <p>No Recent Activity</p>
        </div>
    </div>

    <div class="fleet-panel">
        <div class="fleet-panel-header fleet-panel-header-split">
            <span><i class="fa fa-clipboard"></i> Service Reminder</span>
            <span class="fleet-badge fleet-badge-yellow">{{ $serviceRemindersPending }} Pending</span>
        </div>
        <div class="fleet-panel-body fleet-empty-state">
            @if ($serviceRemindersPending > 0)
                <p>{{ $serviceRemindersPending }} pending service reminder(s).</p>
                <a href="{{ route('reminders.index') }}" class="fleet-link">View Reminders</a>
            @else
                <p>No pending service reminders.</p>
            @endif
        </div>
    </div>

    <div class="fleet-panel">
        <div class="fleet-panel-header">Vehicle Health</div>
        <div class="fleet-panel-body fleet-panel-body-table">
            <table class="fleet-health-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Count</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Service Overdue</td>
                        <td>{{ $vehicleHealth['service_overdue'] }}</td>
                        <td><a href="#" class="fleet-action-icon" onclick="return false;"><i class="fa fa-search"></i></a></td>
                    </tr>
                    <tr>
                        <td>Due Soon</td>
                        <td>{{ $vehicleHealth['due_soon'] }}</td>
                        <td><a href="#" class="fleet-action-icon" onclick="return false;"><i class="fa fa-search"></i></a></td>
                    </tr>
                    <tr>
                        <td>DTC Codes</td>
                        <td>{{ $vehicleHealth['dtc_codes'] }}</td>
                        <td><span class="fleet-badge fleet-badge-green">OK</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') {
            return;
        }

        var utilCtx = document.getElementById('fleetUtilChart');
        if (utilCtx) {
            new Chart(utilCtx, {
                type: 'doughnut',
                data: {
                    labels: ['In Trip', 'Available'],
                    datasets: [{
                        data: [{{ $fleet['in_trip'] }}, {{ $fleet['available'] }}],
                        backgroundColor: ['#00a65a', '#d2d6de'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    cutout: '68%',
                    maintainAspectRatio: false
                }
            });
        }

        var fuelCtx = document.getElementById('fuelChart');
        if (fuelCtx) {
            new Chart(fuelCtx, {
                type: 'bar',
                data: {
                    labels: @json($fuelLabels),
                    datasets: [{
                        label: 'Fuel',
                        data: @json($fuelData),
                        backgroundColor: '#3c8dbc',
                        borderRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true }
                    },
                    maintainAspectRatio: false
                }
            });
        }

        var costCtx = document.getElementById('costOverviewChart');
        if (costCtx) {
            new Chart(costCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Fuel', 'Maintenance', 'Expenses'],
                    datasets: [{
                        data: [
                            {{ $costOverview['fuel'] }},
                            {{ $costOverview['maintenance'] }},
                            {{ $costOverview['expenses'] }}
                        ],
                        backgroundColor: ['#3c8dbc', '#dd4b39', '#f39c12'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    cutout: '62%',
                    maintainAspectRatio: false
                }
            });
        }
    });
</script>
@endpush
