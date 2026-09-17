@extends('layouts.fleet')
@section('title', $title)
@section('content')
@include('layouts.partials.page-header', ['title' => $title, 'backUrl' => route('dashboard'), 'breadcrumbs' => [['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'], ['label' => $title]]])
<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.stock.price-list') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            @include('reports.stock.partials.category-brand-filter')
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Category</label><select name="display_cost" class="form-control"><option value="hide" @if(($filters['display_cost'] ?? '') === 'hide') selected @endif>Don't Display Cost</option><option value="show" @if(($filters['display_cost'] ?? '') === 'show') selected @endif>Display Cost</option></select></div></div>
                <div class="col-md-6"><div class="form-group"><label>Price Category</label><select name="price_category" class="form-control"><option value="retail" @if(($filters['price_category'] ?? '') === 'retail') selected @endif>Retail Price</option><option value="wholesale" @if(($filters['price_category'] ?? '') === 'wholesale') selected @endif>Wholesale Price</option><option value="promo" @if(($filters['price_category'] ?? '') === 'promo') selected @endif>Promotion Price</option></select></div></div>
            </div>
            <div class="sx-form-actions"><button type="submit" class="btn btn-success">Show</button><a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a></div>
        </form>
    </div>
</div>
@if($generated)
<div class="sx-acc-card">
    <div class="sx-acc-result-head"><span>Records Table</span><div><button type="button" class="btn btn-info btn-sm" id="sx-pl-print">Export Pdf</button><button type="button" class="btn btn-success btn-sm" id="sx-pl-excel">Excel</button></div></div>
    <div class="sx-acc-card-body"><div class="table-responsive"><table class="table table-bordered sx-gold-table" id="sx-pl-table"><thead><tr><th>#</th><th>Category</th><th>Brand</th><th>Item Name</th><th>Sku</th>@if(($filters['display_cost'] ?? '') === 'show')<th class="text-right">Purchase Price</th>@endif<th class="text-right">Selling Price</th><th class="text-right">Stock</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row['index'] }}</td><td>{{ $row['category'] }}</td><td>{{ $row['brand'] }}</td><td>{{ $row['item'] }}</td><td>{{ $row['sku'] }}</td>@if(($filters['display_cost'] ?? '') === 'show')<td class="text-right">{{ number_format($row['purchase_price'], 2) }}</td>@endif<td class="text-right">{{ number_format($row['selling_price'], 2) }}</td><td class="text-right">{{ number_format($row['stock'], 2) }}</td></tr>@empty<tr><td colspan="8">No records found.</td></tr>@endforelse</tbody></table></div></div>
</div>
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-pl-table', 'printId' => 'sx-pl-print', 'excelId' => 'sx-pl-excel', 'title' => $title, 'filename' => 'price-list'])
@endif
@endsection
