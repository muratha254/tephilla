@extends('layouts.fleet')

@section('title', 'Sales Report Custom')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sales Report Custom',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Sales Report Custom'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.sales-custom') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>From Date</label><input type="date" name="from" class="form-control" value="{{ $from }}" required></div></div>
                <div class="col-md-6"><div class="form-group"><label>To Date</label><input type="date" name="to" class="form-control" value="{{ $to }}" required></div></div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Customer Name</label>
                        <select name="customer_id" class="form-control">
                            <option value="">Search Customer...</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @if((string) ($filters['customer_id'] ?? '') === (string) $customer->id) selected @endif>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Employee</label>
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
                        <label>Payment Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($paymentStatuses as $status)
                                <option value="{{ $status }}" @if(($filters['payment_status'] ?? '') === $status) selected @endif>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Show</button>
                <a href="{{ route('reports.sales.sales-custom') }}" class="btn btn-warning">Refresh</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Records Table</span>
        @if($generated)
            <div>
                <button type="button" class="btn btn-info btn-sm" id="sx-sc-print"><i class="fa fa-print"></i> Print</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-sc-excel">Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-sc-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Invoice Number</th><th>Sales Date</th><th>Customer Name</th><th>Employee</th><th>Branch</th>
                            <th class="text-right">Invoice Total(Ksh)</th><th class="text-right">Paid Amt(Ksh)</th><th class="text-right">Due Amt(Ksh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['sales_date'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['employee'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['paid'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['due'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-sc-table', 'printId' => 'sx-sc-print', 'excelId' => 'sx-sc-excel', 'title' => 'Sales Report Custom', 'filename' => 'sales-report-custom'])
@endif
