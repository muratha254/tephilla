@extends('layouts.fleet')

@section('title', $title)

@section('content')
@include('layouts.partials.page-header', [
    'title' => $title,
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => $title],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.stock.stock') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Category Name</label>
                        <select name="category_id" class="form-control">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @if((string) ($filters['category_id'] ?? '') === (string) $category->id) selected @endif>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Brand Name</label>
                        <select name="brand_id" class="form-control">
                            <option value="">All Brands</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" @if((string) ($filters['brand_id'] ?? '') === (string) $brand->id) selected @endif>
                                    {{ $brand->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Item Name</label>
                        <select name="product_id" class="form-control">
                            <option value="">All Items</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @if((string) ($filters['product_id'] ?? '') === (string) $product->id) selected @endif>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Report Type</label>
                        <select name="report_type" class="form-control">
                            <option value="version1">Version1</option>
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

@if($generated)
<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Records Table</span>
        <div>
            <button type="button" class="btn btn-info btn-sm" id="sx-st-print">Export Pdf</button>
            <button type="button" class="btn btn-success btn-sm" id="sx-st-excel">Excel</button>
        </div>
    </div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table" id="sx-st-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th class="text-right">Purchase Price</th>
                        <th class="text-right">Sales Price</th>
                        <th class="text-right">Stock</th>
                        <th class="text-right">Reorder</th>
                        <th class="text-right">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['index'] }}</td>
                            <td>{{ $row['item_code'] }}</td>
                            <td>{{ $row['item'] }}</td>
                            <td>{{ $row['category'] }}</td>
                            <td>{{ $row['brand'] }}</td>
                            <td class="text-right">{{ number_format($row['purchase_price'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['selling_price'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['stock'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['reorder'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-st-table',
    'printId' => 'sx-st-print',
    'excelId' => 'sx-st-excel',
    'title' => $title,
    'filename' => 'stock-report',
])
@endif
@endsection
