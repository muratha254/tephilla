@extends('layouts.fleet')

@section('title', 'Complementary Sales Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Complementary Sales Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Complementary Sales Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.complementary') }}" class="sx-acc-filter">
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
                                <option value="{{ $customer->id }}" @if((string) $customerId === (string) $customer->id) selected @endif>{{ $customer->name }}</option>
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
                <button type="button" class="btn btn-info btn-sm" id="sx-comp-print"><i class="fa fa-print"></i> Print</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-comp-excel">Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-comp-table">
                    <thead>
                        <tr><th colspan="6" class="text-center">REPORT FOR DATES {{ $from }} - {{ $to }}</th></tr>
                        <tr>
                            <th>#</th><th>Invoice Number</th><th>Sales Date</th><th>Sales Person</th>
                            <th>Customer Name</th><th class="text-right">Invoice Total(Ksh )</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['sales_date'] }}</td>
                                <td>{{ $row['sales_person'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
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
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-comp-table', 'printId' => 'sx-comp-print', 'excelId' => 'sx-comp-excel', 'title' => 'Complementary Sales Report', 'filename' => 'complementary-sales'])
@endif
