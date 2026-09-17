@extends('layouts.fleet')

@section('title', 'Loyalty Points Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Loyalty Points Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Loyalty Points Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.loyalty') }}" class="sx-acc-filter">
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
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Report Type</label>
                        <select name="report_type" class="form-control">
                            <option value="">All Report Types</option>
                            <option value="with_points" @if($reportType === 'with_points') selected @endif>With Loyalty Points</option>
                            <option value="zero" @if($reportType === 'zero') selected @endif>Zero Points</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Show Report</button>
                <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
            </div>
        </form>
    </div>
</div>

@if($generated)
<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Records Table</span>
        <div>
            <button type="button" class="btn btn-info btn-sm" id="sx-ly-print"><i class="fa fa-print"></i> Print</button>
            <button type="button" class="btn btn-success btn-sm" id="sx-ly-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
        </div>
    </div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table" id="sx-ly-table">
                <thead>
                    <tr>
                        <th colspan="7" class="text-center">LOYALTY POINTS REPORT</th>
                    </tr>
                    <tr>
                        <th>#</th>
                        <th>Branch</th>
                        <th>Customer Name</th>
                        <th>Phone Number</th>
                        <th class="text-right">Loyalty Points</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['index'] }}</td>
                            <td>{{ $row['branch'] }}</td>
                            <td>{{ $row['customer'] }}</td>
                            <td>{{ $row['phone'] }}</td>
                            <td class="text-right">{{ number_format($row['points'], 2) }}</td>
                            <td>{{ $row['registered'] }}</td>
                            <td>{{ $row['status'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No loyalty records found for the selected period.</td></tr>
                    @endforelse
                    @if($rows->isNotEmpty())
                        <tr>
                            <th colspan="4" class="text-right">Total Points</th>
                            <th class="text-right">{{ number_format($rows->sum('points'), 2) }}</th>
                            <th colspan="2"></th>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-ly-table',
    'printId' => 'sx-ly-print',
    'excelId' => 'sx-ly-excel',
    'title' => 'Loyalty Points Report',
    'filename' => 'loyalty-points-report',
])

@if(isset($transactions) && $transactions->isNotEmpty())
<div class="sx-acc-card" style="margin-top:16px;">
    <div class="sx-acc-result-head"><span>Loyalty Transactions</span></div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th class="text-right">Points</th>
                        <th class="text-right">Balance</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $tx)
                        <tr>
                            <td>{{ optional($tx->created_at)->format('d-m-Y H:i') }}</td>
                            <td>{{ optional($tx->customer)->name ?: '-' }}</td>
                            <td>{{ $tx->type }}</td>
                            <td class="text-right">{{ number_format((float) $tx->points, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $tx->balance_after, 2) }}</td>
                            <td>{{ $tx->reference ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endif
@endsection
