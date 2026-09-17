@extends('layouts.fleet')

@section('title', 'Purchase Payments Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Purchase Payments Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Purchase Payments Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.purchases.payments') }}" class="sx-acc-filter">
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
                                <option value="{{ $supplier->id }}" @if((string) $supplierId === (string) $supplier->id) selected @endif>{{ $supplier->name }}</option>
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
                <button type="button" class="btn btn-info btn-sm" id="sx-pp-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-pp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Invoice Number</th>
                            <th>Payment Date</th>
                            <th>Supplier ID</th>
                            <th>Supplier Name</th>
                            <th>Payment Type</th>
                            <th>Payment Note</th>
                            <th class="text-right">Paid Amt(Ksh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['invoice'] }}</td>
                                <td>{{ $row['payment_date'] }}</td>
                                <td>{{ $row['supplier_id'] }}</td>
                                <td>{{ $row['supplier'] }}</td>
                                <td>{{ $row['payment_type'] }}</td>
                                <td>{{ $row['note'] }}</td>
                                <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
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
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-pp-table',
    'printId' => 'sx-pp-print-unused',
    'excelId' => 'sx-pp-excel',
    'title' => 'Purchase Payments Report',
    'filename' => 'purchase-payments-report',
])
@endif
