@extends('layouts.fleet')

@section('title', 'Account Type List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Account Type List',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'List of Charts of Accounts'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'at-table'])
        <div class="table-responsive">
            <table id="at-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="at-check-all"></th>
                        <th>Account Code</th>
                        <th>Account Type</th>
                        <th>Report Type</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($types as $type)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="at-row-check" value="{{ $type->id }}"></td>
                            <td>{{ $type->code }}</td>
                            <td>{{ $type->name }}</td>
                            <td>{{ $type->report_type }}</td>
                            <td>{{ $type->description }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'at-table',
    'lengthId' => 'at-table-length',
    'searchId' => 'at-table-search',
    'exportId' => 'at-table-export',
    'colvisId' => 'at-table-colvis',
    'checkAll' => 'at-check-all',
    'rowCheck' => 'at-row-check',
    'title' => 'Account Type List',
    'filename' => 'account-types',
    'noSort' => [0],
])
