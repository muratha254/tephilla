@extends('layouts.auth')

@section('login')
<img src="{{ asset('img/login-bg.jpg') }}" alt="" class="login-bg-image" aria-hidden="true">

<div class="login-shell">
    <div class="login-card">
        <div class="login-card-body">
            <div class="login-logo" aria-label="{{ fleet_system_name() }}">
                <span class="login-logo-prime">{{ fleet_system_name() }}</span>
            </div>

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
                    <div class="login-input-group @error('email') has-error @enderror">
                        <span class="login-input-addon"><i class="fa fa-user"></i></span>
                        <input
                            type="text"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Username"
                            required
                            autofocus
                            autocomplete="username"
                        >
                    </div>
                    @error('email')
                        <div class="login-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="login-field">
                    <div class="login-input-group @error('password') has-error @enderror">
                        <span class="login-input-addon"><i class="fa fa-lock"></i></span>
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

                <div class="login-links">
                    <a href="#" class="login-forgot" onclick="return false;">Forgot Password?</a>
                    <a href="{{ route('register') }}" class="login-signup">Create account</a>
                </div>

                <button type="submit" class="login-submit">
                    Sign In
                    <i class="fa fa-sign-in"></i>
                </button>
            </form>
        </div>

        <div class="login-card-footer">
            <p>Powered by {{ fleet_system_name() }}</p>
            @if(optional($setting)->poweredByWebsite())
            <p>
                <a href="{{ $setting->poweredByWebsiteUrl() }}" target="_blank" rel="noopener">
                    {{ $setting->poweredByWebsite() }}
                </a>
            </p>
            @endif
            @if(optional($setting)->poweredByEmail())
            <p>
                <a href="mailto:{{ $setting->poweredByEmail() }}">
                    {{ $setting->poweredByEmail() }}
                </a>
            </p>
            @endif
        </div>
    </div>

    <p class="login-copyright">
        &copy; {{ date('Y') }}. Powered by {{ fleet_system_name() }}
    </p>
</div>
@endsection
