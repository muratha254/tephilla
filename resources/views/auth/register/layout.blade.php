@extends('layouts.auth')

@php
    $step = $step ?? 'business';
    $wide = ! empty($wide);
    $steps = [
        'business' => 'Business',
        'account' => 'Login',
        'plans' => 'Plan',
        'review' => 'Review',
    ];
@endphp

@section('login')
<img src="{{ asset('img/login-bg.jpg') }}" alt="" class="login-bg-image" aria-hidden="true">

<div class="login-shell">
    <div class="login-card {{ $wide ? 'login-card-wide' : '' }}">
        <div class="login-card-body">
            <div class="login-logo" aria-label="Sellix POS">
                <span class="login-logo-prime">Sellix</span><span class="login-logo-pos">POS</span>
            </div>

            @if($step !== 'submitted')
            <ol class="login-steps">
                @foreach($steps as $key => $label)
                    <li class="{{ $step === $key ? 'is-current' : '' }}">{{ $label }}</li>
                @endforeach
            </ol>
            @endif

            @if(session('error'))
            <div class="login-alert login-alert-warning">{{ session('error') }}</div>
            @endif

            @if($errors->any())
            <div class="login-alert login-alert-danger">{{ $errors->first() }}</div>
            @endif

            @yield('register-body')
        </div>

        <div class="login-card-footer">
            <p>{{ optional($setting)->poweredByText() ?? 'Powered by Sellix POS' }}</p>
        </div>
    </div>

    <p class="login-copyright">
        &copy; {{ date('Y') }}. {{ optional($setting)->poweredByText() ?? 'Powered by Sellix POS' }}
    </p>
</div>
@endsection
