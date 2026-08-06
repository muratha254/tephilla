@extends('layouts.fleet')

@section('title', 'Maintenance Report')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-reports.css') }}?v=8">
@endpush

@section('content')
<div class="fleet-report-shell">
    <form method="GET" action="{{ route('reports.maintenance') }}" class="fleet-report-filters fleet-report-filters-reminders">
        <div class="fleet-report-filter">
            <label for="maintenance-date-from">From Date</label>
            <input type="date" id="maintenance-date-from" name="date_from" class="fleet-report-input" value="{{ $dateFrom }}">
        </div>
        <div class="fleet-report-filter">
            <label for="maintenance-date-to">To Date</label>
            <input type="date" id="maintenance-date-to" name="date_to" class="fleet-report-input" value="{{ $dateTo }}">
        </div>
        <div class="fleet-report-filter fleet-report-filter-action">
            <label>&nbsp;</label>
            <button type="submit" class="fleet-btn fleet-btn-primary fleet-report-submit">
                Generate
            </button>
        </div>
    </form>

    <div class="fleet-report-metrics is-two-col">
        <div class="fleet-report-metric is-maintenance-records">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Service Records</span>
                <strong class="fleet-report-metric-value">{{ number_format($summary['total_records']) }}</strong>
            </div>
            <i class="fa fa-wrench fleet-report-metric-icon"></i>
        </div>
        <div class="fleet-report-metric is-maintenance-cost">
            <div class="fleet-report-metric-body">
                <span class="fleet-report-metric-label">Total Maintenance Cost</span>
                <strong class="fleet-report-metric-value">{{ format_kes($summary['total_cost']) }}</strong>
            </div>
            <i class="fa fa-money fleet-report-metric-icon"></i>
        </div>
    </div>

    <div class="fleet-panel fleet-report-chart-card">
        <div class="fleet-report-card-head">Vehicle Cost Analysis</div>
        <div class="fleet-report-chart-wrap">
            <canvas id="maintenance-cost-chart"></canvas>
        </div>
    </div>

    <div class="fleet-panel fleet-reminder-report-table-card">
        <div class="fleet-reminder-report-table-head">
            <h3><i class="fa fa-list"></i> Service Logs</h3>
        </div>

        <div class="fleet-vendor-table-tools">
            <div class="fleet-export-btns">
                <button type="button" class="fleet-export-btn fleet-export-copy" id="maintenance-report-copy-btn">Copy</button>
                <button type="button" class="fleet-export-btn fleet-export-excel" id="maintenance-report-excel-btn">Excel</button>
                <button type="button" class="fleet-export-btn fleet-export-csv" id="maintenance-report-csv-btn">CSV</button>
                <button type="button" class="fleet-export-btn fleet-export-pdf" id="maintenance-report-pdf-btn">PDF</button>
            </div>

            <form method="GET" action="{{ route('reports.maintenance') }}" class="fleet-vendor-search">
                <label for="maintenance-report-search">Search:</label>
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">
                <input type="text" id="maintenance-report-search" name="search" value="{{ $search }}" placeholder="">
            </form>
        </div>

        <div class="fleet-table-wrap">
            <table class="fleet-vendor-table fleet-maintenance-report-table" id="maintenance-report-table">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Service Date</th>
                        <th>Vehicle</th>
                        <th>Odometer</th>
                        <th>Cost</th>
                        <th>Mechanic</th>
                        <th>Parts / Vendor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['serial'] }}</td>
                        <td>
                            <div class="fleet-maintenance-report-dates">
                                <span><i class="fa fa-calendar"></i> {{ $row['start_date'] }}</span>
                                <span><i class="fa fa-flag"></i> {{ $row['end_date'] }}</span>
                            </div>
                        </td>
                        <td>{{ $row['vehicle_name'] }}</td>
                        <td>{{ $row['odometer'] }} km</td>
                        <td class="is-cost">{{ format_kes($row['cost'], 0) }}</td>
                        <td>
                            <span class="fleet-maintenance-report-mechanic">
                                <i class="fa fa-user"></i> {{ $row['mechanic'] }}
                            </span>
                        </td>
                        <td>{{ $row['vendor_label'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="fleet-empty-row">No maintenance records found for this period.</td>
                    </tr>
                    @endforelse
                </tbody>
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
    var colors = ['#dd4b39', '#f39c12', '#00c0ef', '#00a65a', '#3c8dbc', '#605ca8'];
    var barColors = chart.labels.map(function (_, index) {
        return colors[index % colors.length];
    });

    var costCtx = document.getElementById('maintenance-cost-chart');
    if (costCtx && typeof Chart !== 'undefined') {
        new Chart(costCtx, {
            type: 'bar',
            data: {
                labels: chart.labels,
                datasets: [{
                    label: 'Cost',
                    data: chart.values,
                    backgroundColor: barColors,
                    borderRadius: 4,
                    maxBarThickness: 56
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

    var table = document.getElementById('maintenance-report-table');
    if (!table) return;

    function tableText() {
        var rows = table.querySelectorAll('tr');
        var lines = [];
        rows.forEach(function (row) {
            var cells = row.querySelectorAll('th, td');
            var values = [];
            cells.forEach(function (cell) {
                values.push(cell.innerText.trim().replace(/\s+/g, ' '));
            });
            if (values.length) lines.push(values.join('\t'));
        });
        return lines.join('\n');
    }

    document.getElementById('maintenance-report-copy-btn').addEventListener('click', function () {
        navigator.clipboard.writeText(tableText());
    });

    document.getElementById('maintenance-report-csv-btn').addEventListener('click', function () {
        var blob = new Blob([tableText().replace(/\t/g, ',')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'maintenance-report.csv';
        link.click();
    });

    document.getElementById('maintenance-report-excel-btn').addEventListener('click', function () {
        var blob = new Blob([tableText()], { type: 'application/vnd.ms-excel' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'maintenance-report.xls';
        link.click();
    });

    document.getElementById('maintenance-report-pdf-btn').addEventListener('click', function () {
        var params = new URLSearchParams();
        params.set('date_from', @json($dateFrom));
        params.set('date_to', @json($dateTo));
        var searchInput = document.getElementById('maintenance-report-search');
        if (searchInput && searchInput.value.trim() !== '') {
            params.set('search', searchInput.value.trim());
        }
        window.open(@json(route('reports.maintenance.export-pdf')) + '?' + params.toString(), '_blank');
    });
})();
</script>
@endpush
