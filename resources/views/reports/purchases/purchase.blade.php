@extends('layouts.fleet')

@section('title', 'Purchase Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Purchase Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Purchase List', 'url' => route('purchases.index')],
        ['label' => 'Purchase Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.purchases.purchase') }}" class="sx-acc-filter">
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
                        <label>Supplier Name</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @if((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id) selected @endif>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Status <span class="sx-req">*</span></label>
                        <select name="status" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @if(($filters['status'] ?? '') === $status) selected @endif>{{ ucfirst($status) }}</option>
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
                <button type="button" class="btn btn-info btn-sm" id="sx-po-print"><i class="fa fa-file-pdf-o"></i> Pdf</button>
                <button type="button" class="btn btn-success btn-sm" id="sx-po-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-po-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Invoice Number</th>
                            <th>Purchase Date</th>
                            <th>Supplier Name</th>
                            <th>Item Name</th>
                            <th class="text-right">VAT Amount</th>
                            <th class="text-right">Invoice Total(Ksh)</th>
                            <th class="text-right">Wht(Ksh)</th>
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
                                <td>{{ $row['purchase_date'] }}</td>
                                <td>{{ $row['supplier'] }}</td>
                                <td>{{ $row['item'] }}</td>
                                <td class="text-right">{{ number_format($row['vat'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['total'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['wht'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['paid'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['due'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11">No records found.</td></tr>
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
    'tableId' => 'sx-po-table',
    'printId' => 'sx-po-print',
    'excelId' => 'sx-po-excel',
    'title' => 'Purchase Report',
    'filename' => 'purchase-report',
])
@endif
