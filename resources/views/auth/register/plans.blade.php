@extends('auth.register.layout')

@section('title', 'Choose a plan')

@section('register-body')
<p class="login-intro">Select one subscription plan. Access starts only after the System Owner approves your request.</p>

<form action="{{ route('register.plans.store') }}" method="post">
    @csrf

    <div class="login-plan-grid">
        @foreach($plans as $plan)
            @php $checked = (string) old('plan_id', $selectedId) === (string) $plan->id; @endphp
            <label class="login-plan-card {{ $checked ? 'is-selected' : '' }}">
                <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ $checked ? 'checked' : '' }} required>
                <strong>{{ $plan->name }}</strong>
                <span class="login-plan-price">{{ format_kes($plan->price) }} / {{ $plan->periodLabel() }}</span>
                @if($plan->description)
                    <span class="login-plan-desc">{{ $plan->description }}</span>
                @endif
                <span class="login-plan-meta">Shops / branches: {{ $plan->maxBranchesLabel() }}</span>
            </label>
        @endforeach
    </div>

    <button type="submit" class="login-submit">
        Review selection
        <i class="fa fa-arrow-right"></i>
    </button>

    <p class="login-switch">
        <a href="{{ route('register.account') }}">Back to login account</a>
    </p>
</form>
@endsection
