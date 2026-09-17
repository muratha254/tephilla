@extends('layouts.fleet')

@section('title', 'Item Sales Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Item Sales Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Item Sales Report'],
        ['label' => 'Get Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.item-sales') }}" class="sx-acc-filter">
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
                        <label>Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @if((string) ($filters['category_id'] ?? '') === (string) $category->id) selected @endif>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Brand</label>
                        <select name="brand_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" @if((string) ($filters['brand_id'] ?? '') === (string) $brand->id) selected @endif>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Item Name</label>
                        <select name="product_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @if((string) ($filters['product_id'] ?? '') === (string) $product->id) selected @endif>{{ $product->name }}</option>
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
                <button type="button" class="btn btn-info btn-sm" id="sx-is-print"><i class="fa fa-file-pdf-o"></i> Pdf</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-is-excel">Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-is-table">
                    <thead>
                        <tr><th colspan="9" class="text-center">ITEMS SALES REPORT FOR ~~~</th></tr>
                        <tr>
                            <th>#</th><th>Invoice Number</th><th>Sales Date</th><th>Date/Time</th><th>Customer Name</th>
                            <th>Item Name</th><th class="text-right">Item Sales Count</th>
                            <th class="text-right">Invoice Total(Ksh)</th><th>Sold By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['sales_date'] }}</td>
                                <td>{{ $row['datetime'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['item'] }}</td>
                                <td class="text-right">{{ number_format($row['qty'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['invoice_total'], 2) }}</td>
                                <td>{{ $row['sold_by'] }}</td>
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
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-is-table', 'printId' => 'sx-is-print', 'excelId' => 'sx-is-excel', 'title' => 'Item Sales Report', 'filename' => 'item-sales-report'])
@endif
