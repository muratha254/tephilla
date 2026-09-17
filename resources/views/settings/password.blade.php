@extends('layouts.fleet')

@section('title', 'Change Password')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Change Password',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Change Password'],
    ],
])

<form method="post" action="{{ route('settings.password.update') }}" class="form-horizontal sx-item-form">
    @csrf
    @method('PUT')

    <div class="sx-box" style="max-width:720px;">
        <div class="sx-acc-card-head"><i class="fa fa-info-circle"></i> Please Enter Valid Data</div>
        <div class="sx-box-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="form-group @error('current_password') has-error @enderror">
                <label class="col-sm-4 control-label">Current Password <span class="sx-req">*</span></label>
                <div class="col-sm-8">
                    <input type="password" name="current_password" class="form-control" placeholder="Current Password" required autocomplete="current-password">
                </div>
            </div>

            <div class="form-group @error('password') has-error @enderror">
                <label class="col-sm-4 control-label">New Password <span class="sx-req">*</span></label>
                <div class="col-sm-8">
                    <input type="password" name="password" class="form-control" placeholder="New Password" required autocomplete="new-password">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-4 control-label">Confirm Password <span class="sx-req">*</span></label>
                <div class="col-sm-8">
                    <input type="password" name="password_confirmation" class="form-control" placeholder="Confirm Password" required autocomplete="new-password">
                </div>
            </div>

            <div class="sx-form-actions text-center" style="margin-top:20px;">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('dashboard') }}" class="btn btn-warning">Close</a>
            </div>
        </div>
    </div>
</form>
@endsection
