@extends('layouts.fleet')

@section('title', $config['list_title'])

@push('css')
@include('accounting.partials.datatable-css')
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => $config['list_title'],
    'subtitle' => $config['list_subtitle'],
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => $config['list_title']],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">{{ $config['list_title'] }}</h3>
        <a href="{{ route('settings.lookups.create', $kind) }}" class="btn btn-primary"><i class="fa fa-plus"></i> {{ $config['add_label'] }}</a>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="lk-length"></div>
            <div class="sx-export-btns" id="lk-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="lk-colvis"></ul>
                </div>
            </div>
            <div id="lk-search"></div>
        </div>

        <div class="table-responsive">
            <table id="lk-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="lk-check-all"></th>
                        <th>{{ $config['name_label'] }}</th>
                        @if(!empty($config['has_code']))<th>{{ $config['code_label'] ?? 'Code' }}</th>@endif
                        @if(!empty($config['has_symbol']))<th>{{ $config['symbol_label'] ?? 'Symbol' }}</th>@endif
                        @if(!empty($config['has_rate']))<th>{{ $config['rate_label'] ?? 'Rate' }}</th>@endif
                        @if(!empty($config['has_parent']))<th>{{ $config['parent_label'] }}</th>@endif
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="lk-row-check" value="{{ $item->id }}"></td>
                            <td>{{ $item->name }}</td>
                            @if(!empty($config['has_code']))<td>{{ $item->code }}</td>@endif
                            @if(!empty($config['has_symbol']))<td>{{ $item->symbol }}</td>@endif
                            @if(!empty($config['has_rate']))
                                <td>{{ rtrim(rtrim(number_format((float) $item->rate, 6, '.', ''), '0'), '.') ?: '0' }}</td>
                            @endif
                            @if(!empty($config['has_parent']))<td>{{ optional($item->parent)->name }}</td>@endif
                            <td>
                                @if($item->is_active)
                                    <span class="label label-success">Active</span>
                                @else
                                    <span class="label label-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li><a href="{{ route('settings.lookups.edit', [$kind, $item]) }}"><i class="fa fa-pencil"></i> Edit</a></li>
                                        <li>
                                            <a href="#" onclick="event.preventDefault(); if(confirm('Delete this item?')) document.getElementById('sx-del-lk-{{ $item->id }}').submit();">
                                                <i class="fa fa-trash"></i> Delete
                                            </a>
                                            <form id="sx-del-lk-{{ $item->id }}" action="{{ route('settings.lookups.destroy', [$kind, $item]) }}" method="post" class="hidden">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        </li>
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

@php
    $actionCol = 2
        + (!empty($config['has_code']) ? 1 : 0)
        + (!empty($config['has_symbol']) ? 1 : 0)
        + (!empty($config['has_rate']) ? 1 : 0)
        + (!empty($config['has_parent']) ? 1 : 0);
@endphp
@include('accounting.partials.datatable-js', [
    'tableId' => 'lk-table',
    'lengthId' => 'lk-length',
    'searchId' => 'lk-search',
    'exportId' => 'lk-export',
    'colvisId' => 'lk-colvis',
    'checkAll' => 'lk-check-all',
    'rowCheck' => 'lk-row-check',
    'title' => $config['list_title'],
    'filename' => $kind,
    'order' => [[1, 'asc']],
    'noSort' => [0, $actionCol],
])
