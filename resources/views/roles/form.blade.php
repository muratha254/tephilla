@extends('layouts.fleet')

@php $isEdit = $role->exists; @endphp

@section('title', $isEdit ? 'Edit Role' : 'New Role')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Edit Role' : 'New Role',
    'subtitle' => 'Assign role permissions',
    'backUrl' => route('roles.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Roles List', 'url' => route('roles.index')],
        ['label' => $isEdit ? 'Edit Role' : 'New Role'],
    ],
])

<form class="sx-item-form" method="post" action="{{ $isEdit ? route('roles.update', $role) : route('roles.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-box">
        <div class="sx-box-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Role Name <span class="sx-req">*</span></label>
                        <input type="text" name="display_name" class="form-control" value="{{ old('display_name', $role->display_name) }}" required @if($role->is_system) @endif>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" value="{{ old('description', $role->description) }}">
                    </div>
                </div>
            </div>

            <h4 style="margin-top:10px;">Permissions</h4>
            <div class="row">
                @foreach($permissionGroups as $module => $perms)
                    <div class="col-md-4" style="margin-bottom:15px;">
                        <div class="sx-box" style="margin:0;min-height:140px;">
                            <div class="sx-box-body" style="padding:12px;">
                                <strong style="text-transform:uppercase;">{{ str_replace('_', ' ', $module) }}</strong>
                                <div style="margin-top:8px;">
                                    @foreach($perms as $perm)
                                        <label style="display:block;font-weight:normal;">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm['name'] }}"
                                                @if(in_array($perm['name'], $selectedPermissions, true)) checked @endif
                                                @if($role->is_system && $role->name === 'super_admin' && ! auth()->user()->isSuperAdmin()) disabled @endif>
                                            {{ $perm['label'] }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="sx-form-actions text-center">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('roles.index') }}" class="btn btn-warning">Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
