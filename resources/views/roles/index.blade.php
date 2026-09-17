@extends('layouts.fleet')

@section('title', 'Roles List')

@push('css')
@include('accounting.partials.datatable-css')
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Roles List',
    'subtitle' => 'View/Search Items Category',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Roles List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Roles List</h3>
        @if($canManage)
            <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> New Role</a>
        @endif
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="roles-length"></div>
            <div class="sx-export-btns" id="roles-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="roles-colvis"></ul>
                </div>
            </div>
            <div id="roles-search"></div>
        </div>

        <div class="table-responsive">
            <table id="roles-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $i => $role)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $role->display_name ?: $role->name }}</td>
                            <td>{{ $role->description ?: $role->display_name }}</td>
                            <td>
                                @if($role->is_system)
                                    <span class="label label-warning">Restricted</span>
                                @else
                                    <span class="label label-success">Active</span>
                                @endif
                            </td>
                            <td>
                                @if($role->is_system && $role->name === 'super_admin' && ! auth()->user()->isSuperAdmin())
                                    --
                                @elseif($canManage)
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                            Action <span class="caret"></span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                            <li><a href="{{ route('roles.edit', $role) }}"><i class="fa fa-pencil"></i> Edit</a></li>
                                            @if(! $role->is_system)
                                                <li>
                                                    <a href="#" onclick="event.preventDefault(); if(confirm('Delete this role?')) document.getElementById('sx-del-role-{{ $role->id }}').submit();">
                                                        <i class="fa fa-trash"></i> Delete
                                                    </a>
                                                    <form id="sx-del-role-{{ $role->id }}" action="{{ route('roles.destroy', $role) }}" method="post" class="hidden">
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
    'tableId' => 'roles-table',
    'lengthId' => 'roles-length',
    'searchId' => 'roles-search',
    'exportId' => 'roles-export',
    'colvisId' => 'roles-colvis',
    'title' => 'Roles List',
    'filename' => 'roles-list',
    'order' => [[0, 'asc']],
    'noSort' => [4],
])
