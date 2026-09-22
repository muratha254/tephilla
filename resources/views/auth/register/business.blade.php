@extends('auth.register.layout')

@section('title', 'Business details')

@section('register-body')
<p class="login-intro">Enter your business details to get started.</p>

<form action="{{ route('register.business') }}" method="post" novalidate>
    @csrf

    <div class="login-field">
        <div class="login-input-group @error('business_name') has-error @enderror">
            <span class="login-input-addon"><i class="fa fa-building"></i></span>
            <input type="text" name="business_name" value="{{ old('business_name', $wizard['business']['business_name'] ?? '') }}" placeholder="Business name" required autofocus autocomplete="organization">
        </div>
        @error('business_name')<div class="login-field-error">{{ $message }}</div>@enderror
    </div>

    <div class="login-field">
        <div class="login-input-group @error('owner_name') has-error @enderror">
            <span class="login-input-addon"><i class="fa fa-id-card-o"></i></span>
            <input type="text" name="owner_name" value="{{ old('owner_name', $wizard['business']['owner_name'] ?? '') }}" placeholder="Owner / admin name" required autocomplete="name">
        </div>
        @error('owner_name')<div class="login-field-error">{{ $message }}</div>@enderror
    </div>

    <div class="login-field">
        <div class="login-input-group @error('phone') has-error @enderror">
            <span class="login-input-addon"><i class="fa fa-phone"></i></span>
            <input type="text" name="phone" value="{{ old('phone', $wizard['business']['phone'] ?? '') }}" placeholder="Phone (optional)" autocomplete="tel">
        </div>
    </div>

    <div class="login-field">
        <div class="login-input-group @error('address') has-error @enderror">
            <span class="login-input-addon"><i class="fa fa-map-marker"></i></span>
            <input type="text" name="address" value="{{ old('address', $wizard['business']['address'] ?? '') }}" placeholder="Address (optional)" autocomplete="street-address">
        </div>
    </div>

    <button type="submit" class="login-submit">
        Continue
        <i class="fa fa-arrow-right"></i>
    </button>

    <p class="login-switch">
        Already have an account?
        <a href="{{ route('login') }}">Sign in</a>
    </p>
</form>
@endsection
