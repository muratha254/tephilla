@extends('layouts.fleet')

@section('title', $company->exists ? 'Edit business' : 'New business')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $company->exists ? 'Edit business' : 'Register business',
    'subtitle' => '',
    'backUrl' => route('owner.businesses.index'),
    'breadcrumbs' => [
        ['label' => 'Owner', 'url' => route('owner.dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Businesses', 'url' => route('owner.businesses.index')],
        ['label' => $company->exists ? 'Edit' : 'New'],
    ],
])

<div class="sx-box">
    <div class="sx-box-body">
        <form method="post" action="{{ $company->exists ? route('owner.businesses.update', $company) : route('owner.businesses.store') }}" class="sx-form">
            @csrf
            @if($company->exists)
                @method('PUT')
            @endif
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="sx-req">Business name*</label>
                        <input type="text" name="name" class="form-control" required value="{{ old('name', $company->name) }}">
                    </div>
                    <div class="form-group">
                        <label class="{{ $company->exists ? '' : 'sx-req' }}">Owner / admin name{{ $company->exists ? '' : '*' }}</label>
                        <input type="text" name="owner_name" class="form-control" @if(!$company->exists) required @endif value="{{ old('owner_name', $company->owner_name) }}">
                    </div>
                    <div class="form-group">
                        <label class="{{ $company->exists ? '' : 'sx-req' }}">Email{{ $company->exists ? '' : '*' }}</label>
                        <input type="email" name="email" class="form-control" @if(!$company->exists) required @endif value="{{ old('email', $company->email) }}">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $company->phone) }}">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $company->address) }}</textarea>
                    </div>
                </div>
                @if(! $company->exists)
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="sx-req">Subscription plan*</label>
                        <select name="plan_id" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @if((string) old('plan_id') === (string) $plan->id) selected @endif>
                                    {{ $plan->name }} ({{ $plan->periodLabel() }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Initial status*</label>
                        <select name="status" class="form-control" required>
                            <option value="trial" @if(old('status', 'trial') === 'trial') selected @endif>Trial</option>
                            <option value="active" @if(old('status') === 'active') selected @endif>Active</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Start date*</label>
                        <input type="date" name="starts_at" class="form-control" required value="{{ old('starts_at', now()->toDateString()) }}">
                    </div>
                    <div class="form-group">
                        <label>Expiry date</label>
                        <input type="date" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                        <p class="help-block">Leave blank to calculate from the plan period.</p>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Login name*</label>
                        <input type="text" name="admin_name" class="form-control" required value="{{ old('admin_name') }}">
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Login email*</label>
                        <input type="email" name="admin_email" class="form-control" required value="{{ old('admin_email') }}">
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Username*</label>
                        <input type="text" name="admin_username" class="form-control" required value="{{ old('admin_username') }}">
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Password*</label>
                        <input type="password" name="admin_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Confirm password*</label>
                        <input type="password" name="admin_password_confirmation" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>
                </div>
                @endif
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('owner.businesses.index') }}" class="btn btn-danger">Close</a>
            </div>
        </form>
    </div>
</div>
@endsection
