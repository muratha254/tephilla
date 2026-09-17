@extends('layouts.fleet')

@section('title', 'Branch List')

@push('css')
@include('accounting.partials.datatable-css')
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Branch List',
    'subtitle' => 'View/Search Branch',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Branch List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Branch List</h3>
        @if($canManage)
            <a href="{{ route('settings.branches.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Add Branch</a>
        @endif
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="br-length"></div>
            <div class="sx-export-btns" id="br-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="br-colvis"></ul>
                </div>
            </div>
            <div id="br-search"></div>
        </div>

        <div class="table-responsive">
            <table id="br-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="br-check-all"></th>
                        <th>S/N</th>
                        <th>Branch Name</th>
                        <th>Short Name</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>List On Login Interface</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($branches as $i => $branch)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="br-row-check" value="{{ $branch->id }}"></td>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $branch->name }}</td>
                            <td>{{ $branch->shortName() }}</td>
                            <td>{{ $branch->phone }}</td>
                            <td>{{ $branch->address }}</td>
                            <td>{{ $branch->city }}</td>
                            <td>
                                @if($branch->is_active)
                                    <span class="label label-success">Active</span>
                                @else
                                    <span class="label label-default">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($branch->list_on_login)
                                    <span class="label label-success">Yes</span>
                                @else
                                    <span class="label label-default">No</span>
                                @endif
                            </td>
                            <td>
                                @if($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                            Action <span class="caret"></span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li><a href="{{ route('settings.branches.edit', $branch) }}"><i class="fa fa-pencil"></i> Edit</a></li>
                                            @if(! $branch->is_default)
                                                <li>
                                                    <a href="#" onclick="event.preventDefault(); if(confirm('Delete this branch?')) document.getElementById('sx-del-br-{{ $branch->id }}').submit();">
                                                        <i class="fa fa-trash"></i> Delete
                                                    </a>
                                                    <form id="sx-del-br-{{ $branch->id }}" action="{{ route('settings.branches.destroy', $branch) }}" method="post" class="hidden">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                </li>
                                            @endif
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
    'tableId' => 'br-table',
    'lengthId' => 'br-length',
    'searchId' => 'br-search',
    'exportId' => 'br-export',
    'colvisId' => 'br-colvis',
    'checkAll' => 'br-check-all',
    'rowCheck' => 'br-row-check',
    'title' => 'Branch List',
    'filename' => 'branch-list',
    'order' => [[1, 'asc']],
    'noSort' => [0, 9],
])
