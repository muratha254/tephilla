@extends('layouts.fleet')

@php $isEdit = $charge->exists; @endphp
@section('title', $isEdit ? 'Update Charges' : 'Add Charges')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $isEdit ? 'Update Charges' : 'Add Charges',
    'subtitle' => 'Add/Update Charges(Allowances/Deductions)',
    'backUrl' => route('hr.allowances.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Charges List', 'url' => route('hr.allowances.index')],
        ['label' => $isEdit ? 'Edit' : 'Add Charges'],
    ],
])

<form method="post" action="{{ $isEdit ? route('hr.allowances.update', $charge) : route('hr.allowances.store') }}" class="sx-item-form">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto; max-width:640px; margin:0 auto;">
            <div class="form-group">
                <label>Category <span class="sx-req">*</span></label>
                <select name="category" class="form-control" required>
                    <option value="">Select Category</option>
                    @foreach(['Allowance','Deduction'] as $cat)
                        <option value="{{ $cat }}" @if(old('category', $charge->category) === $cat) selected @endif>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Taxable <span class="sx-req">*</span></label>
                <select name="taxable" class="form-control" required>
                    <option value="">~~Select~~</option>
                    @foreach(['Taxable','Non Taxable'] as $tax)
                        <option value="{{ $tax }}" @if(old('taxable', $charge->taxable) === $tax) selected @endif>{{ $tax }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Charge Name <span class="sx-req">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="Charge Name" value="{{ old('name', $charge->name) }}" required>
            </div>
            @if($isEdit)
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" class="form-control">
                    <option value="1" @if((string) old('is_active', $charge->is_active ? '1' : '0') === '1') selected @endif>Active</option>
                    <option value="0" @if((string) old('is_active', $charge->is_active ? '1' : '0') === '0') selected @endif>Inactive</option>
                </select>
            </div>
            @endif
            <div class="text-center sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('hr.allowances.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
