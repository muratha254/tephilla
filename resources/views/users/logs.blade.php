@extends('layouts.fleet')

@section('title', 'User Logs')

@push('css')
@include('accounting.partials.datatable-css')
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'User Logs',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'User Logs'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="ulog-length"></div>
            <div class="sx-export-btns" id="ulog-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="ulog-colvis"></ul>
                </div>
            </div>
            <div id="ulog-search"></div>
        </div>

        <div class="table-responsive">
            <table id="ulog-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Username</th>
                        <th>Date/Time</th>
                        <th>Status</th>
                        <th>System IP</th>
                        <th>System Name</th>
                        <th>Branch</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $row)
                        <tr>
                            <td>{{ $row['index'] }}</td>
                            <td>{{ $row['username'] }}</td>
                            <td data-order="{{ $row['sort'] }}">{{ $row['datetime'] }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>{{ $row['ip'] }}</td>
                            <td>{{ $row['system_name'] }}</td>
                            <td>{{ $row['branch'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@include('accounting.partials.datatable-js', [
    'tableId' => 'ulog-table',
    'lengthId' => 'ulog-length',
    'searchId' => 'ulog-search',
    'exportId' => 'ulog-export',
    'colvisId' => 'ulog-colvis',
    'title' => 'User Logs',
    'filename' => 'user-logs',
    'order' => [[2, 'desc']],
    'noSort' => [],
])
