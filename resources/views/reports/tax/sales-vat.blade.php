@extends('layouts.fleet')

@section('title', 'VAT Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'VAT Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'VAT Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.tax.sales-vat') }}" class="sx-acc-filter">
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
                        <label>Category</label>
                        <select name="tax_id" class="form-control">
                            <option value="">-Select-</option>
                            @foreach($taxes as $tax)
                                <option value="{{ $tax->id }}" @if((string) $taxId === (string) $tax->id) selected @endif>
                                    {{ $tax->name }} ({{ number_format((float) $tax->rate, 2) }}%)
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
                <button type="button" class="btn btn-info btn-sm" id="sx-sales-vat-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-sales-vat-table">
                    <thead>
                        <tr>
                            <th colspan="9" class="text-center">SALES VAT REPORT ~-~</th>
                        </tr>
                        <tr>
                            <th>#</th>
                            <th>Invoice No.</th>
                            <th>Invoice Date</th>
                            <th>Customer Name</th>
                            <th>VAT No.</th>
                            <th class="text-right">Taxable Amount</th>
                            <th class="text-right">VAT</th>
                            <th class="text-right">Zero Rated</th>
                            <th class="text-right">Gross</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['invoice_no'] }}</td>
                                <td>{{ $row['invoice_date'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['vat_no'] }}</td>
                                <td class="text-right">{{ number_format($row['taxable'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['vat'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['zero_rated'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9">No sales VAT records found for the selected period.</td></tr>
                        @endforelse
                        @if($rows->isNotEmpty())
                            <tr>
                                <th colspan="5" class="text-right">Totals</th>
                                <th class="text-right">{{ number_format($rows->sum('taxable'), 2) }}</th>
                                <th class="text-right">{{ number_format($rows->sum('vat'), 2) }}</th>
                                <th class="text-right">{{ number_format($rows->sum('zero_rated'), 2) }}</th>
                                <th class="text-right">{{ number_format($rows->sum('gross'), 2) }}</th>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@if($generated)
@include('reports.summary.partials.export-scripts', [
    'tableId' => 'sx-sales-vat-table',
    'printId' => 'sx-sales-vat-print-unused',
    'excelId' => 'sx-sales-vat-excel',
    'title' => 'Sales VAT Report',
    'filename' => 'sales-vat-report',
])
@endif
