@extends('layouts.fleet')

@php $isEdit = $designation->exists; @endphp
@section('title', $isEdit ? 'Update Designation' : 'Add Designation')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Update Designation' : 'Add Designation',
    'subtitle' => 'Designation Details',
    'backUrl' => route('hr.designations.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Designation List', 'url' => route('hr.designations.index')],
        ['label' => $isEdit ? 'Edit' : 'Add Designation'],
    ],
])

<form method="post" action="{{ $isEdit ? route('hr.designations.update', $designation) : route('hr.designations.store') }}" class="sx-item-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-3 control-label">Department <span class="sx-req">*</span></label>
                    <div class="col-sm-9">
                        <select name="department_id" class="form-control" required>
                            <option value="">-Select Department-</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @if((string) old('department_id', $designation->department_id) === (string) $department->id) selected @endif>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Designation Name <span class="sx-req">*</span></label>
                    <div class="col-sm-9">
                        <input type="text" name="name" class="form-control" value="{{ old('name', $designation->name) }}" placeholder="Enter Designation" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Description</label>
                    <div class="col-sm-9">
                        <textarea name="description" class="form-control" rows="4" placeholder="Type here...">{{ old('description', $designation->description) }}</textarea>
                    </div>
                </div>
                @if($isEdit)
                <div class="form-group">
                    <label class="col-sm-3 control-label">Status</label>
                    <div class="col-sm-9">
                        <select name="is_active" class="form-control">
                            <option value="1" @if((string) old('is_active', $designation->is_active ? '1' : '0') === '1') selected @endif>Active</option>
                            <option value="0" @if((string) old('is_active', $designation->is_active ? '1' : '0') === '0') selected @endif>Inactive</option>
                        </select>
                    </div>
                </div>
                @endif
            </div>
            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('hr.designations.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
