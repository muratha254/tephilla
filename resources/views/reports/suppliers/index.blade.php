@extends('layouts.fleet')

@section('title', 'Suppliers Report')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Suppliers Report',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Suppliers Report'],
    ],
])

<div class="sx-acc-card">
    <div class="sx-acc-card-head"><i class="fa fa-filter"></i> Please Enter Valid Information</div>
    <div class="sx-acc-card-body">
        <form method="get" action="{{ route('reports.suppliers') }}" class="sx-acc-filter">
            <input type="hidden" name="show" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Suppliers</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">~~All Suppliers~~</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @if((string) $supplierId === (string) $supplier->id) selected @endif>
                                    {{ $supplier->name }}
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
                <button type="button" class="btn btn-info btn-sm" id="sx-sup-excel"><i class="fa fa-file-excel-o"></i> Excel</button>
            </div>
        @endif
    </div>
    <div class="sx-acc-card-body">
        @if($generated)
            <div class="table-responsive">
                <table class="table table-bordered sx-gold-table" id="sx-sup-table">
                    <thead>
                        <tr>
                            <th colspan="8" class="text-center">SUPPLIERS REPORT</th>
                        </tr>
                        <tr>
                            <th>#</th>
                            <th>Branch</th>
                            <th>Supplier Code</th>
                            <th>Supplier Name</th>
                            <th>Phone Number</th>
                            <th>Registration Date</th>
                            <th>Registered By</th>
                            <th class="text-right">Outstanding Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['branch'] }}</td>
                                <td>{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['phone'] }}</td>
                                <td>{{ $row['registration_date'] }}</td>
                                <td>{{ $row['registered_by'] }}</td>
                                <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No suppliers found.</td></tr>
                        @endforelse
                        @if($rows->isNotEmpty())
                            <tr>
                                <th colspan="7" class="text-right">Total Outstanding</th>
                                <th class="text-right">{{ number_format($rows->sum('balance'), 2) }}</th>
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
    'tableId' => 'sx-sup-table',
    'printId' => 'sx-sup-print-unused',
    'excelId' => 'sx-sup-excel',
    'title' => 'Suppliers Report',
    'filename' => 'suppliers-report',
])
@endif
