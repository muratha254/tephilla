@extends('layouts.fleet')

@section('title', 'Income & Expenses Report')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-reports.css') }}?v=4">
@endpush

@section('content')
<div class="fleet-report-shell">
    <form method="GET" action="{{ route('reports.income') }}" class="fleet-report-filters fleet-report-filters-income">
        <div class="fleet-report-filter">
            <label for="income-date-from">Start Date</label>
            <input type="date" id="income-date-from" name="date_from" class="fleet-report-input" value="{{ $dateFrom }}">
        </div>
        <div class="fleet-report-filter">
            <label for="income-date-to">End Date</label>
            <input type="date" id="income-date-to" name="date_to" class="fleet-report-input" value="{{ $dateTo }}">
        </div>
        <div class="fleet-report-filter">
            <label for="income-vehicle">Vehicle</label>
            <select id="income-vehicle" name="vehicle_id" class="fleet-report-input">
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
        <button type="button" class="fleet-export-btn fleet-export-pdf" id="income-report-pdf-btn">
            <i class="fa fa-file-pdf-o"></i> Export PDF
        </button>
    </div>

    <div class="fleet-report-grid">
        <div class="fleet-panel fleet-report-chart-card">
            <div class="fleet-report-card-head">Income vs Expense</div>
            <div class="fleet-report-chart-wrap">
                <canvas id="income-expense-chart"></canvas>
            </div>
            <div class="fleet-report-legend">
                <span><i class="fleet-report-dot completed"></i> Income</span>
                <span><i class="fleet-report-dot cancelled"></i> Expense</span>
            </div>
        </div>

        <div class="fleet-panel fleet-report-chart-card">
            <div class="fleet-report-card-head">Financial Trend (Daily)</div>
            <div class="fleet-report-chart-wrap is-line">
                <canvas id="income-trend-chart"></canvas>
            </div>
            <div class="fleet-report-legend">
                <span><i class="fleet-report-dot completed"></i> Income</span>
                <span><i class="fleet-report-dot cancelled"></i> Expense</span>
            </div>
        </div>
    </div>

    <div class="fleet-report-metrics">
        <div class="fleet-report-metric is-income">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Income</span>
                <strong class="fleet-report-metric-value">{{ format_kes($summary['total_income']) }}</strong>
            </div>
            <i class="fa fa-arrow-down fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-costs">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Costs</span>
                <strong class="fleet-report-metric-value">{{ format_kes($summary['total_costs']) }}</strong>
            </div>
            <i class="fa fa-arrow-up fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-profit">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Net Profit</span>
                <strong class="fleet-report-metric-value">{{ format_kes($summary['net_profit']) }}</strong>
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

    var donutCtx = document.getElementById('income-expense-chart');
    if (donutCtx && typeof Chart !== 'undefined') {
        var income = chart.income || 0;
        var expense = chart.expense || 0;

        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Income', 'Expense'],
                datasets: [{
                    data: [income, expense],
                    backgroundColor: ['#00a65a', '#dd4b39'],
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

    var trendCtx = document.getElementById('income-trend-chart');
    if (trendCtx && typeof Chart !== 'undefined') {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: chart.trend.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: chart.trend.income,
                        borderColor: '#00a65a',
                        backgroundColor: 'rgba(0, 166, 90, 0.08)',
                        fill: false,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: '#00a65a'
                    },
                    {
                        label: 'Expense',
                        data: chart.trend.expense,
                        borderColor: '#dd4b39',
                        backgroundColor: 'rgba(221, 75, 57, 0.08)',
                        fill: false,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: '#dd4b39'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
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

    document.getElementById('income-report-pdf-btn').addEventListener('click', function () {
        var params = new URLSearchParams();
        params.set('date_from', @json($dateFrom));
        params.set('date_to', @json($dateTo));
        var vehicle = document.getElementById('income-vehicle');
        if (vehicle && vehicle.value !== '') {
            params.set('vehicle_id', vehicle.value);
        }
        window.open(@json(route('reports.income.export-pdf')) + '?' + params.toString(), '_blank');
    });
})();
</script>
@endpush
