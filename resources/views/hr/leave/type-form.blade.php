@extends('layouts.fleet')

@php $isEdit = $type->exists; @endphp
@section('title', $isEdit ? 'Update Leave Type' : 'Add Leave Type')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Update Leave Type' : 'Add Leave Type',
    'subtitle' => 'Leave Type Details',
    'backUrl' => route('hr.leave.types'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Leave Types List', 'url' => route('hr.leave.types')],
        ['label' => $isEdit ? 'Edit' : 'Add Leave Type'],
    ],
])

<form method="post" action="{{ $isEdit ? route('hr.leave.types.update', $type) : route('hr.leave.types.store') }}" class="sx-item-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;max-width:640px;margin:0 auto;">
            <div class="form-group">
                <label>Leave Type Name <span class="sx-req">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $type->name) }}" required>
            </div>
            <div class="form-group">
                <label>Days Allowed <span class="sx-req">*</span></label>
                <input type="number" step="0.5" min="0" name="days_allowed" class="form-control" value="{{ old('days_allowed', $type->days_allowed) }}" required>
            </div>
            <div class="form-group">
                <label>Paid <span class="sx-req">*</span></label>
                <select name="is_paid" class="form-control" required>
                    <option value="1" @if((string) old('is_paid', $type->is_paid ? '1' : '0') === '1') selected @endif>Yes</option>
                    <option value="0" @if((string) old('is_paid', $type->is_paid ? '1' : '0') === '0') selected @endif>No</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $type->description) }}</textarea>
            </div>
            @if($isEdit)
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" class="form-control">
                    <option value="1" @if((string) old('is_active', $type->is_active ? '1' : '0') === '1') selected @endif>Active</option>
                    <option value="0" @if((string) old('is_active', $type->is_active ? '1' : '0') === '0') selected @endif>Inactive</option>
                </select>
            </div>
            @endif
            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('hr.leave.types') }}" class="btn btn-warning">Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
