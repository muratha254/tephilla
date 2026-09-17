@extends('layouts.fleet')

@section('title', 'Users List')

@push('css')
@include('accounting.partials.datatable-css')
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Users List',
    'subtitle' => 'Add/Update Users',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Users List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Users List</h3>
        @if($canCreate)
            <a href="{{ route('users.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New User</a>
        @endif
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="users-length"></div>
            <div class="sx-export-btns" id="users-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="users-colvis"></ul>
                </div>
            </div>
            <div id="users-search"></div>
        </div>

        <div class="table-responsive">
            <table id="users-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User Name</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Description</th>
                        <th>Created on</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $i => $user)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $user->username ?: $user->name }}</td>
                            <td>{{ $user->phone }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ optional($user->role)->display_name ?: optional($user->role)->name }}</td>
                            <td>{{ $user->description }}</td>
                            <td data-order="{{ optional($user->created_at)->timestamp }}">{{ optional($user->created_at)->format('d-m-Y') }}</td>
                            <td>
                                @if($user->is_active)
                                    <span class="label label-success">Active</span>
                                @else
                                    <span class="label label-default">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        @if($canUpdate)
                                            <li><a href="{{ route('users.edit', $user) }}"><i class="fa fa-pencil"></i> Edit</a></li>
                                        @endif
                                        @if($canDelete && (int) $user->id !== (int) auth()->id())
                                            <li>
                                                <a href="#" onclick="event.preventDefault(); if(confirm('Delete this user?')) document.getElementById('sx-del-user-{{ $user->id }}').submit();">
                                                    <i class="fa fa-trash"></i> Delete
                                                </a>
                                                <form id="sx-del-user-{{ $user->id }}" action="{{ route('users.destroy', $user) }}" method="post" class="hidden">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </li>
                                        @endif
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
    'tableId' => 'users-table',
    'lengthId' => 'users-length',
    'searchId' => 'users-search',
    'exportId' => 'users-export',
    'colvisId' => 'users-colvis',
    'title' => 'Users List',
    'filename' => 'users-list',
    'order' => [[0, 'asc']],
    'noSort' => [8],
])
