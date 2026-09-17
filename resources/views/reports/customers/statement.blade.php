@extends('layouts.fleet')

@section('title', 'Customer Statement')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customer Statement',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Customer Statement'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.customers.statement') }}" class="sx-acc-filter">
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
                        <label>Customers</label>
                        <select name="customer_id" class="form-control">
                            <option value="">~~All Customers~~</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @if((string) $customerId === (string) $customer->id) selected @endif>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Report Type</label>
                        <select name="report_type" class="form-control">
                            <option value="detailed" @if($reportType === 'detailed') selected @endif>Detailed Report</option>
                            <option value="summary" @if($reportType === 'summary') selected @endif>Summary Report</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-info"><i class="fa fa-filter"></i> Filter</button>
                <a href="{{ route('reports.customers.statement.pdf', ['from' => $from, 'to' => $to, 'customer_id' => $customerId, 'report_type' => $reportType]) }}" class="btn btn-success">
                    <i class="fa fa-file-pdf-o"></i> Generate Pdf
                </a>
                <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Records Table</span>
        @if($generated)
            <div>
                <button type="button" class="btn btn-success btn-sm" id="sx-cs-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-cs-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['reference'] }}</td>
                                <td>{{ $row['description'] }}</td>
                                <td class="text-right">{{ number_format($row['debit'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['credit'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No statement records found for the selected period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-cs-table',
    'printId' => 'sx-cs-print-unused',
    'excelId' => 'sx-cs-excel',
    'title' => 'Customer Statement',
    'filename' => 'customer-statement',
])
@endif
