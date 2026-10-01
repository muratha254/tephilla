@extends('layouts.fleet')

@section('title', 'Items Category Sales Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Items Category Sales Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Items Category Sales Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.sales.items-category-summary') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>From Date</label><input type="date" name="from" class="form-control" value="{{ $from }}" required></div></div>
                <div class="col-md-6"><div class="form-group"><label>To Date</label><input type="date" name="to" class="form-control" value="{{ $to }}" required></div></div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @if((string) ($filters['category_id'] ?? '') === (string) $category->id) selected @endif>{{ $category->optionLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
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
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Branch</label>
                        <select name="branch_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($branchList as $b)
                                <option value="{{ $b->id }}" @if((string) ($filters['branch_id'] ?? '') === (string) $b->id) selected @endif>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Generate Statement</button>
                <a href="{{ route('reports.sales.items-category-summary') }}" class="btn btn-warning">Refresh</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>Records Table</span>
        @if($generated)
            <div>
                <button type="button" class="btn btn-info btn-sm" id="sx-ics-print"><i class="fa fa-print"></i> Print</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-ics-excel">Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-ics-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Category</th><th class="text-right">Qty Sold</th><th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['category'] }}</td>
                                <td class="text-right">{{ number_format($row['qty'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', ['tableId' => 'sx-ics-table', 'printId' => 'sx-ics-print', 'excelId' => 'sx-ics-excel', 'title' => 'Items Category Sales Report', 'filename' => 'items-category-sales'])
@endif
