@extends('auth.register.layout')

@section('title', 'Create login account')

@section('register-body')
<p class="login-intro">Create the login you will use after the System Owner approves your request.</p>

<form action="{{ route('register.account.store') }}" method="post" novalidate>
    @csrf

    <div class="login-field">
        <div class="login-input-group @error('email') has-error @enderror">
            <span class="login-input-addon"><i class="fa fa-envelope"></i></span>
            <input type="email" name="email" value="{{ old('email', $wizard['account']['email'] ?? '') }}" placeholder="Email" required autocomplete="email">
        </div>
        @error('email')<div class="login-field-error">{{ $message }}</div>@enderror
    </div>

    <div class="login-field">
        <div class="login-input-group @error('username') has-error @enderror">
            <span class="login-input-addon"><i class="fa fa-user"></i></span>
            <input type="text" name="username" value="{{ old('username', $wizard['account']['username'] ?? '') }}" placeholder="Username" required autocomplete="username">
        </div>
        @error('username')<div class="login-field-error">{{ $message }}</div>@enderror
    </div>

    <div class="login-field">
        <div class="login-input-group @error('password') has-error @enderror">
            <span class="login-input-addon"><i class="fa fa-lock"></i></span>
            <input type="password" name="password" placeholder="Password" required minlength="4" autocomplete="new-password">
        </div>
        @error('password')<div class="login-field-error">{{ $message }}</div>@enderror
    </div>

    <div class="login-field">
        <div class="login-input-group">
            <span class="login-input-addon"><i class="fa fa-lock"></i></span>
            <input type="password" name="password_confirmation" placeholder="Confirm password" required minlength="4" autocomplete="new-password">
        </div>
    </div>

    <button type="submit" class="login-submit">
        Continue
        <i class="fa fa-arrow-right"></i>
    </button>

    <p class="login-switch">
        <a href="{{ route('register') }}">Back to business details</a>
    </p>
</form>
@endsection
