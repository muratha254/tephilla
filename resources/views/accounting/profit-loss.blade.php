@extends('layouts.fleet')

@section('title', 'Profit & Loss Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Profit & Loss Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Profit & Loss Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-folder"></i> Select Filters to Generate Report</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('accounting.profit-loss') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="from" class="form-control" value="{{ $from }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="to" class="form-control" value="{{ $to }}" required>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Show <i class="fa fa-filter"></i></button>
                <a href="{{ route('accounting.profit-loss') }}" class="btn btn-warning">Refresh</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Profit & Loss Report</span>
        @if($generated)
            <div>
                <a href="{{ route('accounting.profit-loss.pdf', ['from' => $from, 'to' => $to]) }}" class="btn btn-warning btn-sm" id="sx-pl-pdf" data-filename="profit-loss-{{ $from }}-to-{{ $to }}.pdf"><i class="fa fa-file-pdf-o"></i> Export Pdf</a>
                <button type="button" class="btn btn-info btn-sm" id="sx-pl-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated && $report)
            <table class="table table-bordered sx-report-table" id="sx-pl-table">
                <thead>
                    <tr>
                        <th>Particulars</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><th colspan="2">Income</th></tr>
                    @forelse($report['income'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2">No income in this period.</td></tr>
                    @endforelse
                    <tr>
                        <th>Total Income</th>
                        <th class="text-right">{{ number_format($report['income_total'], 2) }}</th>
                    </tr>
                    <tr><th colspan="2">Expenses</th></tr>
                    @forelse($report['expenses'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2">No expenses in this period.</td></tr>
                    @endforelse
                    <tr>
                        <th>Total Expenses</th>
                        <th class="text-right">{{ number_format($report['expense_total'], 2) }}</th>
                    </tr>
                    <tr>
                        <th>{{ $report['net'] >= 0 ? 'Net Profit' : 'Net Loss' }}</th>
                        <th class="text-right">{{ number_format($report['net'], 2) }}</th>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('sx-pl-pdf') && document.getElementById('sx-pl-pdf').addEventListener('click', function (e) {
    e.preventDefault();
    var link = this;
    var filename = link.getAttribute('data-filename') || 'profit-loss.pdf';
    fetch(link.href, { credentials: 'same-origin' }).then(function (response) {
        if (!response.ok) throw new Error('pdf');
        return response.blob();
    }).then(function (blob) {
        var file = blob.type && blob.type.indexOf('pdf') === -1
            ? new Blob([blob], { type: 'application/pdf' })
            : blob;
        var a = document.createElement('a');
        a.href = URL.createObjectURL(file);
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(a.href);
    }).catch(function () {
        window.location.href = link.href;
    });
});
document.getElementById('sx-pl-excel') && document.getElementById('sx-pl-excel').addEventListener('click', function () {
    var table = document.getElementById('sx-pl-table');
    if (!table) return;
    var csv = [];
    table.querySelectorAll('tr').forEach(function (tr) {
        csv.push(Array.from(tr.querySelectorAll('th,td')).map(function (td) { return '"' + td.innerText.replace(/"/g, '""') + '"'; }).join(','));
    });
    var blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'application/vnd.ms-excel' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'profit-loss.xls';
    a.click();
});
</script>
@endpush
