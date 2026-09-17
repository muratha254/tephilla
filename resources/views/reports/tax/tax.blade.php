@extends('layouts.fleet')

@section('title', 'Tax Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Tax Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Tax Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.tax.index') }}" class="sx-acc-filter">
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
                <button type="submit" class="btn btn-success">Show Report</button>
                <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
            </div>
        </form>
    </div>
</div>

@if($generated && $report)
<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-bar-chart"></i> Tax Summary</div>
    <div class="sx-acc-card-body">
        <table class="table table-bordered sx-report-table">
            <tbody>
                <tr><th>Invoices</th><td class="text-right">{{ number_format($report['invoices']) }}</td></tr>
                <tr><th>Taxable Amount</th><td class="text-right">{{ number_format($report['taxable'], 2) }}</td></tr>
                <tr><th>VAT</th><td class="text-right">{{ number_format($report['vat'], 2) }}</td></tr>
                <tr><th>Zero Rated</th><td class="text-right">{{ number_format($report['zero_rated'], 2) }}</td></tr>
                <tr><th>Gross</th><td class="text-right">{{ number_format($report['gross'], 2) }}</td></tr>
                <tr><th>Net of VAT</th><td class="text-right">{{ number_format($report['net_of_vat'], 2) }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
