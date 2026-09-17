@extends('layouts.fleet')

@section('title', 'Sales Cancelled Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sales Cancelled Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Sales Cancelled Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.cancelled') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>From Date</label><input type="date" name="from" class="form-control" value="{{ $from }}" required></div></div>
                <div class="col-md-6"><div class="form-group"><label>To Date</label><input type="date" name="to" class="form-control" value="{{ $to }}" required></div></div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Employee Name</label>
                        <select name="user_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" @if((string) $userId === (string) $member->id) selected @endif>{{ $member->name }}</option>
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
            <div><button type="button" class="btn btn-info btn-sm" id="sx-can-excel">Excel</button></div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-can-table">
                    <thead>
                        <tr><th colspan="9" class="text-center">CANCELLED SALES REPORT</th></tr>
                        <tr>
                            <th>#</th><th>Branch</th><th>Invoice Number</th><th>Item Name</th><th>Sales Date</th>
                            <th>Customer Name</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['item'] }}</td>
                                <td>{{ $row['sales_date'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td class="text-right">{{ number_format($row['qty'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['price'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
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
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-can-table', 'printId' => 'sx-can-print-unused', 'excelId' => 'sx-can-excel', 'title' => 'Cancelled Sales Report', 'filename' => 'sales-cancelled-report'])
@endif
