@extends('layouts.fleet')

@php $isEdit = $category->exists; @endphp
@section('title', $isEdit ? 'Update Employee Category' : 'Add Employee Category')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Update Employee Category' : 'Add Employee Category',
    'backUrl' => route('hr.employee-categories.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Category List', 'url' => route('hr.employee-categories.index')],
        ['label' => $isEdit ? 'Edit' : 'Add Employee Category'],
    ],
])

<form method="post" action="{{ $isEdit ? route('hr.employee-categories.update', $category) : route('hr.employee-categories.store') }}" class="sx-item-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-3 control-label">Category Name <span class="sx-req">*</span></label>
                    <div class="col-sm-9">
                        <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" placeholder="Enter Category" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Description</label>
                    <div class="col-sm-9">
                        <textarea name="description" class="form-control" rows="5" placeholder="Type here...">{{ old('description', $category->description) }}</textarea>
                    </div>
                </div>
                @if($isEdit)
                <div class="form-group">
                    <label class="col-sm-3 control-label">Status</label>
                    <div class="col-sm-9">
                        <select name="is_active" class="form-control">
                            <option value="1" @if((string) old('is_active', $category->is_active ? '1' : '0') === '1') selected @endif>Active</option>
                            <option value="0" @if((string) old('is_active', $category->is_active ? '1' : '0') === '0') selected @endif>Inactive</option>
                        </select>
                    </div>
                </div>
                @endif
            </div>
            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('hr.employee-categories.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
