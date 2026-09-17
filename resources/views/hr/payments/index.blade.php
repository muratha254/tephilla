@extends('layouts.fleet')
@section('title', 'Salary Payments')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Salary Payments',
    'subtitle' => 'View/Search Payments',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Salary Payments'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('hr.payments.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Record Payment</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'pay-table'])
        <div class="table-responsive">
            <table id="pay-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Payroll</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $row)
                        <tr>
                            <td>{{ $row->number }}</td>
                            <td data-order="{{ optional($row->payment_date)->format('Y-m-d') }}">{{ optional($row->payment_date)->format('Y-m-d') }}</td>
                            <td>{{ optional($row->employee)->fullName() }}</td>
                            <td>{{ optional($row->payrollRun)->number ?: 'Ad-hoc' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $row->method)) }}</td>
                            <td data-order="{{ $row->amount }}">{{ number_format((float) $row->amount, 2) }}</td>
                            <td><span class="sx-status-active">{{ ucfirst($row->status) }}</span></td>
                            <td>
                                <a href="{{ route('hr.payments.show', $row) }}" class="btn btn-xs btn-primary">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'pay-table', 'lengthId' => 'pay-table-length', 'searchId' => 'pay-table-search',
    'exportId' => 'pay-table-export', 'colvisId' => 'pay-table-colvis',
    'title' => 'Salary Payments', 'filename' => 'salary-payments', 'noSort' => [7], 'order' => [[1, 'desc']],
])
