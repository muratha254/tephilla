@extends('layouts.auth')

@section('login')
<div class="login-split">
    <section class="login-split-brand">
        <img
            src="{{ asset('img/login-brand-bg.png') }}"
            alt=""
            class="login-brand-image"
            aria-hidden="true"
        >
        <div class="login-brand-overlay" aria-hidden="true"></div>
        <div class="login-brand-content">
            <h1>{{ $systemName ?? fleet_system_name() }}</h1>
            <p>
                Advanced Fleet Management &amp; Tracking Solution.
                Seamlessly manage your vehicles, drivers, and trips in one centralized platform.
            </p>
        </div>
        <div class="login-brand-tags">Tracking &bull; Trips &bull; GPS Tracking</div>
    </section>

    <section class="login-split-form-wrap">
        <div class="login-split-form">
            <div class="login-logo">
                <span class="login-logo-icon"><i class="fa fa-truck"></i></span>
                <span class="login-logo-text">
                    <span class="track">{{ $systemShortName ?? fleet_system_short_name() }}</span>
                </span>
            </div>
            <p class="login-subtitle">Sign in to your dashboard</p>

            @if(!empty($dbUnavailable))
            <div class="login-alert login-alert-danger">
                Cannot connect to the database. Start <strong>MySQL</strong> in XAMPP, then refresh this page.
            </div>
            @endif

            @if(session('status'))
            <div class="login-alert login-alert-success">{{ session('status') }}</div>
            @endif

            @if(session('success'))
            <div class="login-alert login-alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
            <div class="login-alert login-alert-warning">{{ session('error') }}</div>
            @endif

            @if($errors->any())
            <div class="login-alert login-alert-danger">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('login') }}" method="post" novalidate>
                @csrf

                <div class="login-field">
                    <div class="login-input-wrap @error('email') has-error @enderror">
                        <i class="fa fa-envelope-o"></i>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Email Address"
                            required
                            autofocus
                            autocomplete="email"
                        >
                    </div>
                    @error('email')
                        <div class="login-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="login-field">
                    <div class="login-input-wrap @error('password') has-error @enderror">
                        <i class="fa fa-lock"></i>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Password"
                            required
                            minlength="4"
                            autocomplete="current-password"
                        >
                    </div>
                    @error('password')
                        <div class="login-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="login-options">
                    <label class="login-remember">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="login-forgot" onclick="return false;">Forgot Password?</a>
                </div>

                <button type="submit" class="login-submit">
                    Sign In
                    <i class="fa fa-arrow-right"></i>
                </button>
            </form>

            <div class="login-footer">
                &copy; {{ date('Y') }} {{ $systemName ?? fleet_system_name() }}. All rights reserved.
            </div>
        </div>
    </section>
</div>
@endsection
