@extends('layouts.fleet')

@php $isEdit = $customer->exists; @endphp

@section('title', 'Customers')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customers',
    'subtitle' => 'Add/Update Customer',
    'backUrl' => route('customers.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Customers List', 'url' => route('customers.index')],
        ['label' => 'Customers'],
    ],
])

<form class="sx-item-form" method="post" action="{{ $isEdit ? route('customers.update', $customer) : route('customers.store') }}" enctype="multipart/form-data">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="sx-box">
        <div class="sx-box-body" style="min-height:auto;">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Branch/Station <span class="sx-req">*</span></label>
                        <select name="branch_id" class="form-control" required>
                            @foreach($branches as $option)
                                <option value="{{ $option->id }}" @if((string) $selectedBranchId === (string) $option->id) selected @endif>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Customer Name <span class="sx-req">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Customer Name" value="{{ old('name', $customer->name) }}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Phone No.</label>
                        <input type="text" name="phone" class="form-control" placeholder="Phone Number" value="{{ old('phone', $customer->phone) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Alt. Phone No.</label>
                        <input type="text" name="mobile" class="form-control" placeholder="Alt. Phone No" value="{{ old('mobile', $customer->mobile) }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Opening Balance outstanding (+ve) advance (-ve)</label>
                        <input type="number" step="0.01" name="opening_balance" class="form-control" value="{{ old('opening_balance', $customer->opening_balance ?? 0) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Credit Limit</label>
                        <input type="number" step="0.01" min="0" name="credit_limit" class="form-control" value="{{ old('credit_limit', $customer->credit_limit ?? 0) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Tax PIN</label>
                        <input type="text" name="tax_number" class="form-control" placeholder="Tax Pin e.g KRA/UERA" value="{{ old('tax_number', $customer->tax_number) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>County</label>
                        <select name="county" class="form-control">
                            <option value="">-- County --</option>
                            @foreach($counties as $county)
                                <option value="{{ $county }}" @if(old('county', $customer->county) === $county) selected @endif>{{ $county }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Email Address" value="{{ old('email', $customer->email) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>National ID</label>
                        <input type="text" name="national_id" class="form-control" placeholder="National ID" value="{{ old('national_id', $customer->national_id) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Estate</label>
                        <input type="text" name="estate" class="form-control" placeholder="Residence Place" value="{{ old('estate', $customer->estate) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Postcode</label>
                        <input type="text" name="postcode" class="form-control" placeholder="e.g 123-00100" value="{{ old('postcode', $customer->postcode) }}">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Customer Shop Picture</label>
                        <input type="file" name="shop_image" class="form-control" accept="image/*">
                        @if($customer->shop_image_path)
                            <div class="sx-hint">Current image on file.</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Migration Control Account</label>
                        <select name="migration_account" class="form-control">
                            <option value="">-- Select Account --</option>
                            @foreach($accounts as $key => $label)
                                <option value="{{ $key }}" @if(old('migration_account', $customer->migration_account) === $key) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Assigned to</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">-- Select Owner --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @if((string) old('assigned_to', $customer->assigned_to) === (string) $user->id) selected @endif>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">-- Category --</option>
                            @php
                                $defaultCategory = $customer->category_id ?: optional($categories->firstWhere('name', 'General'))->id;
                            @endphp
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @if((string) old('category_id', $defaultCategory) === (string) $category->id) selected @endif>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Type here...">{{ old('address', $customer->address) }}</textarea>
            </div>

            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                <a href="{{ route('customers.index') }}" class="btn btn-warning"><i class="fa fa-times"></i> Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
