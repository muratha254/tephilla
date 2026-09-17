@extends('layouts.fleet')

@section('title', 'Expired Items Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Expired Items Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Expired Items Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.expired') }}" class="sx-acc-filter">
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
                        <label>Item Name</label>
                        <select name="product_id" class="form-control">
                            <option value="">All Items</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @if((string) $productId === (string) $product->id) selected @endif>
                                    {{ $product->name }}
                                </option>
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
                <button type="button" class="btn btn-info btn-sm" id="sx-exp-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-exp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Lot Number</th>
                            <th>Expire Date</th>
                            <th class="text-right">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['item_code'] }}</td>
                                <td>{{ $row['item'] }}</td>
                                <td>{{ $row['lot'] }}</td>
                                <td>{{ $row['expire_date'] }}</td>
                                <td class="text-right">{{ number_format($row['stock'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No expired items found for the selected period.</td></tr>
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
    'tableId' => 'sx-exp-table',
    'printId' => 'sx-exp-print-unused',
    'excelId' => 'sx-exp-excel',
    'title' => 'Expired Items Report',
    'filename' => 'expired-items-report',
])
@endif
