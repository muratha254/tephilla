@extends('layouts.fleet')
@section('title', 'Production List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Production List',
    'subtitle' => 'View/Search Production',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Production List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('manufacturing.production.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Create Production</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'prod-table', 'exportId' => 'prod-table-export-hidden'])
        <style>#prod-table-export-hidden{display:none!important}</style>
        <div class="table-responsive">
            <table id="prod-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="prod-check-all"></th>
                        <th>Production Title</th>
                        <th>Production Cost</th>
                        <th>Expected Production</th>
                        <th>Actual Production</th>
                        <th>Created By</th>
                        <th>Created Date</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productions as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="prod-row-check"></td>
                            <td>{{ $row->title }}</td>
                            <td>{{ number_format((float) $row->production_cost, 2) }}</td>
                            <td>{{ number_format((float) $row->expected_production, 0) }}</td>
                            <td>{{ number_format((float) $row->actual_production, 0) }}</td>
                            <td>{{ optional($row->user)->name }}</td>
                            <td data-order="{{ $row->created_at }}">{{ optional($row->created_at)->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">Action <span class="caret"></span></button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li><a href="#"><i class="fa fa-eye"></i> View</a></li>
                                    </ul>
                                </div>
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
    'tableId' => 'prod-table', 'lengthId' => 'prod-table-length', 'searchId' => 'prod-table-search',
    'exportId' => 'prod-table-export-hidden', 'colvisId' => 'prod-table-colvis',
    'checkAll' => 'prod-check-all', 'rowCheck' => 'prod-row-check',
    'title' => 'Production List', 'filename' => 'production-list', 'noSort' => [0, 7], 'order' => [[6, 'desc']],
])
