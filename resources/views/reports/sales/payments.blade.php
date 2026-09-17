@extends('layouts.fleet')

@section('title', 'Sales Payments Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sales Payments Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Sales Payments Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.payments') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>From Date</label><input type="date" name="from" class="form-control" value="{{ $from }}" required></div>
                    <div class="form-group"><label>From Time</label><input type="time" name="from_time" class="form-control" value="{{ $filters['from_time'] ?? '' }}"></div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>To Date</label><input type="date" name="to" class="form-control" value="{{ $to }}" required></div>
                    <div class="form-group"><label>To Time</label><input type="time" name="to_time" class="form-control" value="{{ $filters['to_time'] ?? '' }}"></div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Employee Name</label>
                        <select name="user_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" @if((string) ($filters['user_id'] ?? '') === (string) $member->id) selected @endif>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Payment Mode</label>
                        <select name="method" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($methods as $method)
                                <option value="{{ $method }}" @if(($filters['method'] ?? '') === $method) selected @endif>{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Show</button>
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
                <button type="button" class="btn btn-info btn-sm" id="sx-pay-print"><i class="fa fa-print"></i> Print</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-pay-excel">Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-pay-table">
                    <thead>
                        <tr><th colspan="10" class="text-center">SALES PAYMENTS REPORT</th></tr>
                        <tr>
                            <th>#</th><th>Branch</th><th>Employee</th><th>Invoice</th><th>PayDate</th>
                            <th>CreatedDate</th><th>Customer</th><th>PayType</th><th>PayNote</th><th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['employee'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['pay_date'] }}</td>
                                <td>{{ $row['created'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['pay_type'] }}</td>
                                <td>{{ $row['note'] }}</td>
                                <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-pay-table', 'printId' => 'sx-pay-print', 'excelId' => 'sx-pay-excel', 'title' => 'Sales Payments Report', 'filename' => 'sales-payments-report'])
@endif
