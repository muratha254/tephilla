@extends('layouts.fleet')

@section('title', 'Tax List')

@push('css')
@include('accounting.partials.datatable-css')
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Tax List',
    'subtitle' => 'View/Search Tax',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Tax List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Tax List</h3>
        @if($canManage)
            <a href="{{ route('settings.tax.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New Tax</a>
        @endif
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="tx-length"></div>
            <div class="sx-export-btns" id="tx-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="tx-colvis"></ul>
                </div>
            </div>
            <div id="tx-search"></div>
        </div>

        <div class="table-responsive">
            <table id="tx-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="tx-check-all"></th>
                        <th>Tax Name</th>
                        <th>Tax(%)</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($taxes as $tax)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="tx-row-check" value="{{ $tax->id }}"></td>
                            <td>{{ $tax->name }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $tax->rate, 2, '.', ''), '0'), '.') }}</td>
                            <td>
                                @if($tax->is_active)
                                    <span class="label label-success">Active</span>
                                @else
                                    <span class="label label-default">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                            Action <span class="caret"></span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li><a href="{{ route('settings.tax.edit', $tax) }}"><i class="fa fa-pencil"></i> Edit</a></li>
                                            <li>
                                                <a href="#" onclick="event.preventDefault(); if(confirm('Delete this tax?')) document.getElementById('sx-del-tax-{{ $tax->id }}').submit();">
                                                    <i class="fa fa-trash"></i> Delete
                                                </a>
                                                <form id="sx-del-tax-{{ $tax->id }}" action="{{ route('settings.tax.destroy', $tax) }}" method="post" class="hidden">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                @else
                                    --
                                @endif
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
    'tableId' => 'tx-table',
    'lengthId' => 'tx-length',
    'searchId' => 'tx-search',
    'exportId' => 'tx-export',
    'colvisId' => 'tx-colvis',
    'checkAll' => 'tx-check-all',
    'rowCheck' => 'tx-row-check',
    'title' => 'Tax List',
    'filename' => 'tax-list',
    'order' => [[1, 'asc']],
    'noSort' => [0, 4],
])
