@extends('layouts.fleet')

@section('title', 'Credit Sales Aging')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Credit Sales Aging',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Credit Sales Aging'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.credit-aging') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>From Date</label><input type="date" name="from" class="form-control" value="{{ $from }}" required></div></div>
                <div class="col-md-6"><div class="form-group"><label>To Date</label><input type="date" name="to" class="form-control" value="{{ $to }}" required></div></div>
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
                        <label>Customer</label>
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
                <button type="submit" class="btn btn-success"><i class="fa fa-search"></i> Show</button>
                <a href="{{ route('reports.sales.credit-aging') }}" class="btn btn-warning"><i class="fa fa-refresh"></i> Refresh</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Credit Sales Aging Report</span>
        @if($generated)
            <div>
                <button type="button" class="btn btn-info btn-sm" id="sx-age-print"><i class="fa fa-file-pdf-o"></i> Pdf</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-age-excel">Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-age-table">
                    <thead>
                        <tr><th colspan="11" class="text-center">CREDIT SALES AGING REPORT</th></tr>
                        <tr>
                            <th>#</th><th>Invoice Number</th><th>Sales Date</th><th>Due Date</th><th>Age</th>
                            <th>Sales Person</th><th>Customer Name</th>
                            <th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th>Branch</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['sales_date'] }}</td>
                                <td>{{ $row['due_date'] }}</td>
                                <td>{{ $row['age'] }}</td>
                                <td>{{ $row['sales_person'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['paid'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                                <td>{{ $row['branch'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-age-table', 'printId' => 'sx-age-print', 'excelId' => 'sx-age-excel', 'title' => 'Credit Sales Aging', 'filename' => 'credit-sales-aging'])
@endif
