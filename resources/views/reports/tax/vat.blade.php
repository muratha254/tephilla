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
        <form method="get" action="{{ route('reports.tax.vat') }}" class="sx-acc-filter" id="sx-vat-form">
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
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
                <a href="{{ route('reports.tax.vat.pdf', ['from' => $from, 'to' => $to]) }}" class="btn btn-success" id="sx-vat-pdf"><i class="fa fa-file-pdf-o"></i> Generate Pdf</a>
                <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
            </div>
        </form>
    </div>
</div>

<div class="sx-acc-card">
    <div class="sx-acc-result-head">
        <span>VAT Report</span>
        @if($generated)
            <div>
                <button type="button" class="btn btn-success btn-sm" id="sx-vat-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-vat-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th class="text-right">Invoices</th>
                            <th class="text-right">Taxable Amount</th>
                            <th class="text-right">VAT</th>
                            <th class="text-right">Zero Rated</th>
                            <th class="text-right">Gross</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $row['date'] }}</td>
                                <td class="text-right">{{ number_format($row['invoices']) }}</td>
                                <td class="text-right">{{ number_format($row['taxable'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['vat'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['zero_rated'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No VAT records found for the selected period.</td></tr>
                        @endforelse
                        @if($rows->isNotEmpty())
                            <tr>
                                <th colspan="2" class="text-right">Totals</th>
                                <th class="text-right">{{ number_format($rows->sum('invoices')) }}</th>
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
    'tableId' => 'sx-vat-table',
    'printId' => 'sx-vat-print-unused',
    'excelId' => 'sx-vat-excel',
    'title' => 'VAT Report',
    'filename' => 'vat-report',
])
@endif
