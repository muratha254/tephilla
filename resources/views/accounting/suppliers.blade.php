@extends('layouts.fleet')

@section('title', 'Suppliers Account Balances')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Suppliers Account Balances',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Supplier Balances'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        <a href="{{ route('accounting.suppliers') }}" class="btn btn-success"><i class="fa fa-file-text-o"></i> Generate Balances</a>
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'sb-table'])
        <div class="table-responsive">
            <table id="sb-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Branch</th>
                        <th>Supplier Name</th>
                        <th>Phone Number</th>
                        <th>Current Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $index => $supplier)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ optional($supplier->branch)->name ?: optional($branch)->name }}</td>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->phone }}</td>
                            <td data-order="{{ $supplier->currentBalance() }}">Ksh {{ number_format($supplier->currentBalance(), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'sb-table',
    'lengthId' => 'sb-table-length',
    'searchId' => 'sb-table-search',
    'exportId' => 'sb-table-export',
    'colvisId' => 'sb-table-colvis',
    'title' => 'Suppliers Account Balances',
    'filename' => 'supplier-balances',
    'noSort' => [0],
    'order' => [[2, 'asc']],
])
