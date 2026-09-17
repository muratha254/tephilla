@extends('layouts.fleet')
@section('title', 'Packaging List')
@include('accounting.partials.datatable-css')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Packaging List',
    'subtitle' => 'View/Search Packaging',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Packaging List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div></div>
        @if($canManage)
            <div class="sx-toolbar-actions">
                <a href="{{ route('manufacturing.packaging.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> Create Packaging</a>
            </div>
        @endif
    </div>
    <div class="sx-box-body sx-items-body">
        @include('accounting.partials.dt-toolbar', ['tableId' => 'pkg-table', 'exportId' => 'pkg-table-export-hidden'])
        <style>#pkg-table-export-hidden{display:none!important}</style>
        <div class="table-responsive">
            <table id="pkg-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="pkg-check-all"></th>
                        <th>Packaging Date</th>
                        <th>Item Packaged</th>
                        <th>Qty Packaged</th>
                        <th>Created By</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($packagings as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="pkg-row-check"></td>
                            <td data-order="{{ $row->packaging_date }}">{{ optional($row->packaging_date)->format('Y-m-d') }}</td>
                            <td>{{ optional($row->product)->name }}</td>
                            <td>{{ number_format((float) $row->qty_packaged, 2) }}</td>
                            <td>{{ optional($row->user)->name }}</td>
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
    'tableId' => 'pkg-table', 'lengthId' => 'pkg-table-length', 'searchId' => 'pkg-table-search',
    'exportId' => 'pkg-table-export-hidden', 'colvisId' => 'pkg-table-colvis',
    'checkAll' => 'pkg-check-all', 'rowCheck' => 'pkg-row-check',
    'title' => 'Packaging List', 'filename' => 'packaging-list', 'noSort' => [0, 5], 'order' => [[1, 'desc']],
])
