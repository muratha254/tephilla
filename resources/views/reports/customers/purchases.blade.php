@extends('layouts.fleet')

@section('title', 'Customer Purchase Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customer Purchase Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Customer Purchase Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.customers.purchases') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>From Date:</label>
                        <input type="date" name="from" class="form-control" value="{{ $from }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>To Date:</label>
                        <input type="date" name="to" class="form-control" value="{{ $to }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Customers:</label>
                        <select name="customer_id" class="form-control">
                            <option value="">~~All Customers~~</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @if((string) $customerId === (string) $customer->id) selected @endif>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-info"><i class="fa fa-filter"></i> Filter</button>
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
                <button type="button" class="btn btn-success btn-sm" id="sx-cp-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-cp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Invoice Number</th>
                            <th>Sales Date</th>
                            <th>Customer Name</th>
                            <th>Sold By</th>
                            <th class="text-right">Invoice Total(Ksh)</th>
                            <th class="text-right">Paid Amt(Ksh)</th>
                            <th class="text-right">Due Amt(Ksh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['sales_date'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['sold_by'] }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['paid'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['due'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9">No purchase records found for the selected period.</td></tr>
                        @endforelse
                        @if($rows->isNotEmpty())
                            <tr>
                                <th colspan="6" class="text-right">Totals</th>
                                <th class="text-right">{{ number_format($rows->sum('total'), 2) }}</th>
                                <th class="text-right">{{ number_format($rows->sum('paid'), 2) }}</th>
                                <th class="text-right">{{ number_format($rows->sum('due'), 2) }}</th>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-cp-table',
    'printId' => 'sx-cp-print-unused',
    'excelId' => 'sx-cp-excel',
    'title' => 'Customer Purchase Report',
    'filename' => 'customer-purchase-report',
])
@endif
