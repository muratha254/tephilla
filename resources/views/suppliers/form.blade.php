@extends('layouts.fleet')

@php
    $isEdit = $supplier->exists;
@endphp

@section('title', 'Suppliers')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Suppliers',
    'subtitle' => 'Add/Update Suppliers',
    'backUrl' => route('suppliers.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Suppliers List', 'url' => route('suppliers.index')],
        ['label' => 'Suppliers'],
    ],
])

<form class="sx-item-form" method="post" action="{{ $isEdit ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Supplier Name <span class="sx-req">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Supplier Name" value="{{ old('name', $supplier->name) }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Phone No. <span class="sx-req">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="Phone Number" value="{{ old('phone', $supplier->phone) }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Alt. Phone No.</label>
                        <input type="text" name="mobile" class="form-control" placeholder="Alt Phone Number" value="{{ old('mobile', $supplier->mobile) }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Email Address" value="{{ old('email', $supplier->email) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>KRA Pin</label>
                        <input type="text" name="tax_number" class="form-control" placeholder="KRA Pin" value="{{ old('tax_number', $supplier->tax_number) }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Opening Balance <span class="sx-req">*</span></label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control" value="{{ old('opening_balance', $supplier->opening_balance ?? 0) }}" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Postcode</label>
                        <input type="text" name="postcode" class="form-control" placeholder="Postcode" value="{{ old('postcode', $supplier->postcode) }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Apply Withholding Tax (2%)</label>
                        <select name="apply_withholding" class="form-control">
                            <option value="0" @if(! old('apply_withholding', $supplier->apply_withholding)) selected @endif>No</option>
                            <option value="1" @if(old('apply_withholding', $supplier->apply_withholding)) selected @endif>Yes</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Migration Control Account</label>
                        <select name="migration_account" class="form-control">
                            <option value="">-- Select Account --</option>
                            @foreach($accounts as $key => $label)
                                <option value="{{ $key }}" @if(old('migration_account', $supplier->migration_account) === $key) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="sx-supplier-section">
                <i class="fa fa-check-square"></i> Mpesa Payment Details
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Till Number</label>
                        <input type="text" name="till_number" class="form-control" placeholder="Till Number" value="{{ old('till_number', $supplier->till_number) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Paybill Number</label>
                        <input type="text" name="paybill_number" class="form-control" placeholder="Paybill Number" value="{{ old('paybill_number', $supplier->paybill_number) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Account Name</label>
                        <input type="text" name="mpesa_account_name" class="form-control" placeholder="Account Name" value="{{ old('mpesa_account_name', $supplier->mpesa_account_name) }}">
                    </div>
                </div>
            </div>

            <div class="sx-supplier-section">
                <i class="fa fa-check-square"></i> Bank Payment Details
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bank Account Name</label>
                        <input type="text" name="bank_account_name" class="form-control" placeholder="Account Name" value="{{ old('bank_account_name', $supplier->bank_account_name) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bank Account Number</label>
                        <input type="text" name="bank_account_number" class="form-control" placeholder="Account Number" value="{{ old('bank_account_number', $supplier->bank_account_number) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="Bank Name" value="{{ old('bank_name', $supplier->bank_name) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bank Branch</label>
                        <input type="text" name="bank_branch" class="form-control" placeholder="Bank Branch" value="{{ old('bank_branch', $supplier->bank_branch) }}">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Type here...">{{ old('address', $supplier->address) }}</textarea>
            </div>

            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('suppliers.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
