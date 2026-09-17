@extends('layouts.fleet')

@php $isEdit = $tax->exists; @endphp

@section('title', $isEdit ? 'Edit Tax' : 'Add Tax')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Tax',
    'subtitle' => 'Add/Update Tax',
    'backUrl' => route('settings.tax'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Tax List', 'url' => route('settings.tax')],
        ['label' => 'Tax'],
    ],
])

<form method="post" action="{{ $isEdit ? route('settings.tax.update', $tax) : route('settings.tax.store') }}" class="sx-item-form" style="max-width:640px;">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-box">
        <div class="sx-box-body">
            <div class="form-group">
                <label>Tax Name <span class="sx-req">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $tax->name) }}" required>
            </div>
            <div class="form-group">
                <label>Tax Percentage <span class="sx-req">*</span></label>
                <input type="number" step="0.01" min="0" max="100" name="rate" class="form-control" value="{{ old('rate', $tax->rate) }}" required>
            </div>
            @if($isEdit)
                <div class="form-group">
                    <label>Status</label>
                    <select name="is_active" class="form-control">
                        <option value="Yes" @if(old('is_active', $tax->is_active ? 'Yes' : 'No') === 'Yes') selected @endif>Active</option>
                        <option value="No" @if(old('is_active', $tax->is_active ? 'Yes' : 'No') === 'No') selected @endif>Inactive</option>
                    </select>
                </div>
            @endif
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('settings.tax') }}" class="btn btn-warning">Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
