@extends('auth.register.layout')

@section('title', 'Review request')

@section('register-body')
<p class="login-intro">Review your details and selected plan, then submit for System Owner approval.</p>

<div class="login-review">
    <h4>Business</h4>
    <p><strong>{{ $wizard['business']['business_name'] }}</strong><br>
        {{ $wizard['business']['owner_name'] }}<br>
        {{ $wizard['business']['phone'] ?: 'No phone' }}<br>
        {{ $wizard['business']['address'] ?: 'No address' }}
    </p>

    <h4>Login account</h4>
    <p>{{ $wizard['account']['email'] }}<br>
        Username: {{ $wizard['account']['username'] }}
    </p>

    <h4>Selected plan</h4>
    <p><strong>{{ $plan->name }}</strong><br>
        {{ format_kes($plan->price) }} / {{ $plan->periodLabel() }}<br>
        Shops / branches: {{ $plan->maxBranchesLabel() }}
        @if($plan->description)<br>{{ $plan->description }}@endif
    </p>
</div>

<form action="{{ route('register.submit') }}" method="post">
    @csrf
    <button type="submit" class="login-submit">
        Submit subscription request
        <i class="fa fa-paper-plane"></i>
    </button>
</form>

<p class="login-switch">
    <a href="{{ route('register.plans') }}">Change plan</a>
</p>
@endsection
