@extends('layouts.fleet')

@php $isEdit = $branch->exists; @endphp

@section('title', $isEdit ? 'Edit Branch' : 'Add Branch')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Branch',
    'subtitle' => 'Add/Update Branch',
    'backUrl' => route('settings.branches'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Branch List', 'url' => route('settings.branches')],
        ['label' => 'Branch'],
    ],
])

@if(!$isEdit && !empty($subscriptionUsage['max_branches']))
    <div class="alert alert-info">
        Your plan allows {{ (int) $subscriptionUsage['max_branches'] }} shop{{ (int) $subscriptionUsage['max_branches'] === 1 ? '' : 's' }}/branches
        ({{ (int) $subscriptionUsage['branches'] }} in use).
    </div>
@endif

<form method="post" action="{{ $isEdit ? route('settings.branches.update', $branch) : route('settings.branches.store') }}" enctype="multipart/form-data" class="sx-item-form">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-box">
        <div class="sx-acc-card-head"><i class="fa fa-info-circle"></i> Please Enter Valid Data</div>
        <div class="sx-box-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Branch Name <span class="sx-req">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Branch Name" value="{{ old('name', $branch->name) }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Short Name <span class="sx-req">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="Short Name" value="{{ old('code', $branch->code) }}" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>System Mode <span class="sx-req">*</span></label>
                        <select name="system_mode" class="form-control" required>
                            @foreach(['Touch Mode(Normal)', 'Classic Mode', 'Touch Mode(Advanced)'] as $mode)
                                <option value="{{ $mode }}" @if(old('system_mode', $branch->system_mode ?: 'Touch Mode(Normal)') === $mode) selected @endif>{{ $mode }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Phone <span class="sx-req">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="Phone" value="{{ old('phone', $branch->phone) }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Alt. Phone</label>
                        <input type="text" name="phone_alt" class="form-control" placeholder="ALT Phone" value="{{ old('phone_alt', $branch->phone_alt) }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Email Address" value="{{ old('email', $branch->email) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Address/Location</label>
                        <input type="text" name="address" class="form-control" placeholder="Location" value="{{ old('address', $branch->address) }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" class="form-control" placeholder="City" value="{{ old('city', $branch->city) }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Postcode</label>
                        <input type="text" name="postcode" class="form-control" placeholder="Postcode" value="{{ old('postcode', $branch->postcode) }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Branch Logo</label>
                        @if($branch->logo_path)
                            <div style="margin-bottom:8px;"><img src="{{ asset('storage/' . $branch->logo_path) }}" alt="Logo" style="max-height:60px;"></div>
                            <label class="checkbox-inline"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label>
                        @endif
                        <input type="file" name="logo" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" class="form-control">
                            <option value="Yes" @if(old('is_active', $branch->is_active ? 'Yes' : 'No') === 'Yes') selected @endif>Active</option>
                            <option value="No" @if(old('is_active', $branch->is_active ? 'Yes' : 'No') === 'No') selected @endif>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>List On Login Interface</label>
                        <select name="list_on_login" class="form-control">
                            <option value="Yes" @if(old('list_on_login', $branch->list_on_login ? 'Yes' : 'No') === 'Yes') selected @endif>Yes</option>
                            <option value="No" @if(old('list_on_login', $branch->list_on_login ? 'Yes' : 'No') === 'No') selected @endif>No</option>
                        </select>
                    </div>
                </div>
            </div>

            <h4 style="margin-top:8px;">Mpesa Details</h4>
            <div class="row">
                <div class="col-md-3"><div class="form-group"><label>Till1</label><input type="text" name="till1" class="form-control" placeholder="Till1" value="{{ old('till1', $branch->till1) }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Account1</label><input type="text" name="account1" class="form-control" placeholder="Account1" value="{{ old('account1', $branch->account1) }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Till2</label><input type="text" name="till2" class="form-control" placeholder="Till2" value="{{ old('till2', $branch->till2) }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Account2</label><input type="text" name="account2" class="form-control" placeholder="Account2" value="{{ old('account2', $branch->account2) }}"></div></div>
            </div>

            <h4>Bank Details</h4>
            <div class="row">
                <div class="col-md-4"><div class="form-group"><label>Account Name</label><input type="text" name="bank_account_name" class="form-control" placeholder="Account Name" value="{{ old('bank_account_name', $branch->bank_account_name) }}"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Bank Name</label><input type="text" name="bank_name" class="form-control" placeholder="Bank Name e.g KCB" value="{{ old('bank_name', $branch->bank_name) }}"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Account Number</label><input type="text" name="bank_account_no" class="form-control" placeholder="Account Number" value="{{ old('bank_account_no', $branch->bank_account_no) }}"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Bank Branch</label><input type="text" name="bank_branch" class="form-control" placeholder="Bank Branch" value="{{ old('bank_branch', $branch->bank_branch) }}"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Bank Code</label><input type="text" name="bank_code" class="form-control" placeholder="Bank Code" value="{{ old('bank_code', $branch->bank_code) }}"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Swift Code</label><input type="text" name="bank_swift" class="form-control" placeholder="Swift Code" value="{{ old('bank_swift', $branch->bank_swift) }}"></div></div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Include Catering Levy <span class="sx-req">*</span></label>
                        <select name="include_catering_levy" class="form-control" required>
                            <option value="No" @if(old('include_catering_levy', $branch->include_catering_levy ? 'Yes' : 'No') === 'No') selected @endif>No</option>
                            <option value="Yes" @if(old('include_catering_levy', $branch->include_catering_levy ? 'Yes' : 'No') === 'Yes') selected @endif>Yes</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Auto Receipt Amt(POS) <span class="sx-req">*</span></label>
                        <select name="auto_receipt_amt_pos" class="form-control" required>
                            <option value="No" @if(old('auto_receipt_amt_pos', $branch->auto_receipt_amt_pos ? 'Yes' : 'No') === 'No') selected @endif>No</option>
                            <option value="Yes" @if(old('auto_receipt_amt_pos', $branch->auto_receipt_amt_pos ? 'Yes' : 'No') === 'Yes') selected @endif>Yes</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group" style="padding-top:28px;">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="is_default" value="1" @if(old('is_default', $branch->is_default)) checked @endif> Default Branch
                        </label>
                    </div>
                </div>
            </div>

            <div class="sx-form-actions text-center">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('settings.branches') }}" class="btn btn-warning">Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
