@extends('layouts.fleet')

@section('title', 'Reminders Report')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-reports.css') }}?v=7">
@endpush

@section('content')
<div class="fleet-report-shell">
    <form method="GET" action="{{ route('reports.reminders') }}" class="fleet-report-filters fleet-report-filters-reminders">
        <div class="fleet-report-filter">
            <label for="reminders-date-from">From Date</label>
            <input type="date" id="reminders-date-from" name="date_from" class="fleet-report-input" value="{{ $dateFrom }}">
        </div>
        <div class="fleet-report-filter">
            <label for="reminders-date-to">To Date</label>
            <input type="date" id="reminders-date-to" name="date_to" class="fleet-report-input" value="{{ $dateTo }}">
        </div>
        <div class="fleet-report-filter fleet-report-filter-action">
            <label>&nbsp;</label>
            <button type="submit" class="fleet-btn fleet-btn-primary fleet-report-submit">
                Generate
            </button>
        </div>
    </form>

    <div class="fleet-report-reminder-top">
        <div class="fleet-report-metrics fleet-report-metrics-reminders">
            <div class="fleet-report-metric is-reminder-total">
                <div class="fleet-report-metric-body">
                    <span class="fleet-report-metric-label">Total</span>
                    <strong class="fleet-report-metric-value">{{ number_format($summary['total']) }}</strong>
                </div>
                <i class="fa fa-bell fleet-report-metric-icon"></i>
            </div>
            <div class="fleet-report-metric is-reminder-completed">
                <div class="fleet-report-metric-body">
                    <span class="fleet-report-metric-label">Completed</span>
                    <strong class="fleet-report-metric-value">{{ number_format($summary['completed']) }}</strong>
                </div>
                <i class="fa fa-check fleet-report-metric-icon"></i>
            </div>
            <div class="fleet-report-metric is-reminder-pending">
                <div class="fleet-report-metric-body">
                    <span class="fleet-report-metric-label">Pending</span>
                    <strong class="fleet-report-metric-value">{{ number_format($summary['pending']) }}</strong>
                </div>
                <i class="fa fa-clock-o fleet-report-metric-icon"></i>
            </div>
        </div>

        <div class="fleet-panel fleet-report-chart-card fleet-report-reminder-chart">
            <div class="fleet-report-card-head">Status Distribution</div>
            <div class="fleet-report-chart-wrap is-donut">
                <canvas id="reminders-status-chart"></canvas>
            </div>
            <div class="fleet-report-legend">
                <span><i class="fleet-report-dot completed"></i> Completed</span>
                <span><i class="fleet-report-dot pending"></i> Pending</span>
            </div>
        </div>
    </div>

    <div class="fleet-panel fleet-reminder-report-table-card">
        <div class="fleet-reminder-report-table-head">
            <h3><i class="fa fa-list"></i> Reminder History</h3>
        </div>

        <div class="fleet-vendor-table-tools">
            <div class="fleet-export-btns">
                <button type="button" class="fleet-export-btn fleet-export-copy" id="reminders-report-copy-btn">Copy</button>
                <button type="button" class="fleet-export-btn fleet-export-excel" id="reminders-report-excel-btn">Excel</button>
                <button type="button" class="fleet-export-btn fleet-export-csv" id="reminders-report-csv-btn">CSV</button>
                <button type="button" class="fleet-export-btn fleet-export-pdf" id="reminders-report-pdf-btn">PDF</button>
            </div>

            <form method="GET" action="{{ route('reports.reminders') }}" class="fleet-vendor-search">
                <label for="reminders-report-search">Search:</label>
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">
                <input type="text" id="reminders-report-search" name="search" value="{{ $search }}" placeholder="">
            </form>
        </div>

        <div class="fleet-table-wrap">
            <table class="fleet-vendor-table fleet-reminder-report-table" id="reminders-report-table">
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Date</th>
                        <th>Reminder Title</th>
                        <th>Vehicle</th>
                        <th>Services</th>
                        <th>Due Status</th>
                        <th>Completion Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['serial'] }}</td>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['title'] }}</td>
                        <td>
                            <div class="fleet-reminder-report-vehicle">
                                <i class="fa fa-truck"></i>
                                <span>{{ $row['vehicle_name'] }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="fleet-reminder-report-services">
                                @foreach ($row['services'] as $service)
                                    <span class="fleet-reminder-report-service-tag">{{ $service }}</span>
                                @endforeach
                                @if (empty($row['services']))
                                    <span>-</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="fleet-reminder-report-status {{ $row['due_status_class'] }}">{{ $row['due_status'] }}</span>
                        </td>
                        <td>
                            <span class="fleet-reminder-report-completion {{ $row['completion_class'] }}">
                                @if ($row['completion_status'] === 'Pending')
                                    <i class="fa fa-exclamation-circle"></i>
                                @endif
                                {{ $row['completion_status'] }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="fleet-empty-row">No reminders found for this period.</td>
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
    var statusCtx = document.getElementById('reminders-status-chart');

    if (statusCtx && typeof Chart !== 'undefined') {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending'],
                datasets: [{
                    data: [chart.completed || 0, chart.pending || 0],
                    backgroundColor: ['#00a65a', '#f39c12'],
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

    var table = document.getElementById('reminders-report-table');
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

    document.getElementById('reminders-report-copy-btn').addEventListener('click', function () {
        navigator.clipboard.writeText(tableText());
    });

    document.getElementById('reminders-report-csv-btn').addEventListener('click', function () {
        var blob = new Blob([tableText().replace(/\t/g, ',')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'reminders-report.csv';
        link.click();
    });

    document.getElementById('reminders-report-excel-btn').addEventListener('click', function () {
        var blob = new Blob([tableText()], { type: 'application/vnd.ms-excel' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'reminders-report.xls';
        link.click();
    });

    document.getElementById('reminders-report-pdf-btn').addEventListener('click', function () {
        var params = new URLSearchParams();
        params.set('date_from', @json($dateFrom));
        params.set('date_to', @json($dateTo));
        var searchInput = document.getElementById('reminders-report-search');
        if (searchInput && searchInput.value.trim() !== '') {
            params.set('search', searchInput.value.trim());
        }
        window.open(@json(route('reports.reminders.export-pdf')) + '?' + params.toString(), '_blank');
    });
})();
</script>
@endpush
