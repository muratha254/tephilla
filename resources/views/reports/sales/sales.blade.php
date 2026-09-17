@extends('layouts.fleet')

@section('title', 'Sales Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sales Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Sales Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.sales') }}" class="sx-acc-filter">
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
                        <label>Payment Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($paymentStatuses as $status)
                                <option value="{{ $status }}" @if(($filters['payment_status'] ?? '') === $status) selected @endif>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>KRA Pin</label>
                        <select name="kra" class="form-control">
                            <option value="">-All Customers-</option>
                            <option value="with" @if(($filters['kra'] ?? '') === 'with') selected @endif>With KRA PIN</option>
                            <option value="without" @if(($filters['kra'] ?? '') === 'without') selected @endif>Without KRA PIN</option>
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
                <button type="button" class="btn btn-info btn-sm" id="sx-sales-print"><i class="fa fa-print"></i> Print Pdf</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-sales-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-sales-table">
                    <thead>
                        <tr><th colspan="9" class="text-center">REPORT FOR DATES {{ $from }} ~ {{ $to }}</th></tr>
                        <tr>
                            <th>#</th>
                            <th>Invoice Number</th>
                            <th>Sales Date</th>
                            <th>Customer Name</th>
                            <th>KRA PIN</th>
                            <th>Sales Note</th>
                            <th class="text-right">Invoice Total(Ksh)</th>
                            <th class="text-right">Paid Amt(Ksh)</th>
                            <th class="text-right">Due Amt(Ksh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['sales_date'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['kra_pin'] }}</td>
                                <td>{{ $row['note'] }}</td>
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
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-sales-table',
    'printId' => 'sx-sales-print',
    'excelId' => 'sx-sales-excel',
    'title' => 'Sales Report',
    'filename' => 'sales-report',
])
@endif
