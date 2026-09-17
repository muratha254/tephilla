@extends('layouts.fleet')

@section('title', 'Sales Report-Summary')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sales Report-Summary',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Sales Report-Summary'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.sales-summary') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>From Date</label><input type="date" name="from" class="form-control" value="{{ $from }}" required></div></div>
                <div class="col-md-6"><div class="form-group"><label>To Date</label><input type="date" name="to" class="form-control" value="{{ $to }}" required></div></div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Generate Statement</button>
                <a href="{{ route('reports.sales.sales-summary') }}" class="btn btn-warning">Refresh</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Records Table</span>
        @if($generated)
            <div>
                <button type="button" class="btn btn-info btn-sm" id="sx-ss-print"><i class="fa fa-print"></i> Print</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-ss-excel">Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-ss-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Date</th><th class="text-right">Invoices</th>
                            <th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['date'] }}</td>
                                <td class="text-right">{{ number_format($row['invoices']) }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['paid'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-ss-table', 'printId' => 'sx-ss-print', 'excelId' => 'sx-ss-excel', 'title' => 'Sales Report Summary', 'filename' => 'sales-report-summary'])
@endif
