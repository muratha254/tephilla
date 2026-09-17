@extends('layouts.fleet')

@php $isEdit = $department->exists; @endphp
@section('title', $isEdit ? 'Update Department' : 'Add Department')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Update Department' : 'Add Department',
    'subtitle' => 'Department Details',
    'backUrl' => route('hr.departments.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Departments List', 'url' => route('hr.departments.index')],
        ['label' => $isEdit ? 'Edit' : 'Add Department'],
    ],
])

<form method="post" action="{{ $isEdit ? route('hr.departments.update', $department) : route('hr.departments.store') }}" class="sx-item-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Department Code</label>
                        <input type="text" name="code" class="form-control" value="{{ old('code', $department->code) }}" placeholder="Auto if left blank">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Department Name <span class="sx-req">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $department->name) }}" required>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Description">{{ old('description', $department->description) }}</textarea>
            </div>
            <div class="form-group">
                <label>Status <span class="sx-req">*</span></label>
                <select name="is_active" class="form-control" required>
                    <option value="1" @if((string) old('is_active', $department->is_active ? '1' : '0') === '1') selected @endif>Active</option>
                    <option value="0" @if((string) old('is_active', $department->is_active ? '1' : '0') === '0') selected @endif>Inactive</option>
                </select>
            </div>
            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('hr.departments.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
