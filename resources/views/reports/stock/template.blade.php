@extends('layouts.fleet')

@section('title', 'Stock Template')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Stock Template',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Stock Template'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.stock.template') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Branch/Station</label>
                        <select name="branch_id" class="form-control">
                            <option value="">All Branches</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @if((string) ($branchId ?? '') === (string) $branch->id) selected @endif>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Category Name</label>
                        <select name="category_id" class="form-control">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @if((string) ($filters['category_id'] ?? '') === (string) $category->id) selected @endif>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Items Ordering</label>
                        <select name="ordering" class="form-control">
                            <option value="asc" @if(($filters['ordering'] ?? '') === 'asc') selected @endif>Ascending</option>
                            <option value="desc" @if(($filters['ordering'] ?? '') === 'desc') selected @endif>Descending</option>
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
        <div><button type="button" class="btn btn-success btn-sm" id="sx-tpl-excel">Excel</button></div>
    </div>
    <div class="sx-acc-card-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table" id="sx-tpl-table">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Sku</th>
                        <th>Category</th>
                        <th class="text-right">Buying Price</th>
                        <th class="text-right">Selling Price</th>
                        <th class="text-right">Wholesale Price</th>
                        <th class="text-right">Promotion Price</th>
                        <th class="text-right">Current Stock</th>
                        <th class="text-right">Reorder</th>
                        <th>Parent</th>
                        <th class="text-right">No to Break</th>
                        <th>UOM</th>
                        <th>Tax</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['product_id'] }}</td>
                            <td>{{ $row['product_code'] }}</td>
                            <td>{{ $row['product_name'] }}</td>
                            <td>{{ $row['sku'] }}</td>
                            <td>{{ $row['category'] }}</td>
                            <td class="text-right">{{ number_format($row['buying_price'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['selling_price'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['wholesale_price'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['promo_price'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['current_stock'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['reorder'], 2) }}</td>
                            <td>{{ $row['parent'] }}</td>
                            <td class="text-right">{{ number_format($row['conversion_rate'], 2) }}</td>
                            <td>{{ $row['uom'] }}</td>
                            <td>{{ $row['tax'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="15">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-tpl-table',
    'printId' => 'sx-tpl-print-unused',
    'excelId' => 'sx-tpl-excel',
    'title' => 'Stock Template',
    'filename' => 'stock-template',
])
@endif
@endsection
