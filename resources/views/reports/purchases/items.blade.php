@extends('layouts.fleet')

@section('title', 'Items Purchase Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Items Purchase Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Purchase List', 'url' => route('purchases.index')],
        ['label' => 'Items Purchase Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.purchases.items') }}" class="sx-acc-filter">
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
                            <option value="">-Select-</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @if((string) ($filters['product_id'] ?? '') === (string) $product->id) selected @endif>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @if(($filters['status'] ?? '') === $status) selected @endif>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @if((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id) selected @endif>{{ $supplier->name }}</option>
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
        <span>Item Purchase Report</span>
        @if($generated)
            <div>
                <button type="button" class="btn btn-info btn-sm" id="sx-poi-print"><i class="fa fa-file-pdf-o"></i> Pdf</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-poi-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-poi-table">
                    <thead>
                        <tr><th colspan="12" class="text-center">ITEMS PURCHASE REPORT</th></tr>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Invoice Number</th>
                            <th>Purchase Date</th>
                            <th>Item Name</th>
                            <th>Supplier Name</th>
                            <th>Status</th>
                            <th class="text-right">Ordered Qty</th>
                            <th class="text-right">Received Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Expense</th>
                            <th class="text-right">Grand Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['purchase_date'] }}</td>
                                <td>{{ $row['item'] }}</td>
                                <td>{{ $row['supplier'] }}</td>
                                <td>{{ ucfirst($row['status']) }}</td>
                                <td class="text-right">{{ number_format($row['ordered_qty'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['received_qty'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['unit_price'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['expense'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['grand_total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="12">No records found.</td></tr>
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
    'tableId' => 'sx-poi-table',
    'printId' => 'sx-poi-print',
    'excelId' => 'sx-poi-excel',
    'title' => 'Items Purchase Report',
    'filename' => 'items-purchase-report',
])
@endif
