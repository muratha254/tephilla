@extends('layouts.fleet')

@section('title', 'Customers Account Balances')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customers Account Balances',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Customer Balances'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        <a href="{{ route('accounting.customers') }}" class="btn btn-success"><i class="fa fa-file-text-o"></i> Generate Balances</a>
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'cb-table'])
        <div class="table-responsive">
            <table id="cb-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Branch</th>
                        <th>Customer Name</th>
                        <th>Phone Number</th>
                        <th>Current Balance</th>
                        <th>Savings</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $index => $customer)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ optional($customer->branch)->name ?: optional($branch)->name }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->phone }}</td>
                            <td data-order="{{ $customer->creditAmount() }}">Ksh {{ number_format($customer->creditAmount(), 2) }}</td>
                            <td>Ksh {{ number_format(0, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'cb-table',
    'lengthId' => 'cb-table-length',
    'searchId' => 'cb-table-search',
    'exportId' => 'cb-table-export',
    'colvisId' => 'cb-table-colvis',
    'title' => 'Customers Account Balances',
    'filename' => 'customer-balances',
    'noSort' => [0],
    'order' => [[2, 'asc']],
])
