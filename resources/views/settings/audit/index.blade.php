@extends('layouts.fleet')

@section('title', 'Audit List')

@push('css')
@include('accounting.partials.datatable-css')
<style>
    #audit-table td.sx-audit-action a { color: #3c8dbc; font-weight: 600; }
    #audit-table td.sx-audit-computer { max-width: 320px; white-space: normal; word-break: break-all; font-size: 12px; }
</style>
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Audit List',
    'subtitle' => 'View/Search Audit Trail',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Audit List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="audit-length"></div>
            <div class="sx-export-btns" id="audit-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="audit-colvis"></ul>
                </div>
            </div>
            <div id="audit-search"></div>
        </div>

        <div class="table-responsive">
            <table id="audit-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>##</th>
                        <th>Username</th>
                        <th>Action</th>
                        <th>Computer</th>
                        <th>Date/Time</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $row)
                        <tr>
                            <td data-order="{{ $row['id'] }}">{{ $row['index'] }}</td>
                            <td>{{ $row['username'] }}</td>
                            <td class="sx-audit-action"><a href="javascript:void(0)">{{ $row['action'] }}</a></td>
                            <td class="sx-audit-computer">{{ $row['computer'] }}</td>
                            <td data-order="{{ $row['sort'] }}">{{ $row['datetime'] }}</td>
                            <td>{{ $row['source'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'audit-table',
    'lengthId' => 'audit-length',
    'searchId' => 'audit-search',
    'exportId' => 'audit-export',
    'colvisId' => 'audit-colvis',
    'title' => 'Audit List',
    'filename' => 'audit-list',
    'order' => [[4, 'desc']],
    'noSort' => [],
])
