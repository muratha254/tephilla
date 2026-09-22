@extends('layouts.fleet')

@section('title', $title ?? 'Subscription')

@section('content')
<div class="sx-box" style="max-width:720px;margin:40px auto;">
    <div class="sx-box-body" style="padding:32px;text-align:center;">
        <div class="sx-info-icon" style="margin:0 auto 16px;width:64px;height:64px;border-radius:50%;background:#f4f4f4;display:flex;align-items:center;justify-content:center;">
            <i class="fa {{ ($reason ?? '') === 'suspended' ? 'fa-ban' : 'fa-lock' }}" style="font-size:28px;color:#dd4b39;"></i>
        </div>
        <h2 style="margin-top:0;">{{ $title }}</h2>
        <p class="lead">{{ $message }}</p>
        @if(!empty($company))
            <p><strong>{{ $company->name }}</strong></p>
        @endif
        @if(!empty($subscription) && $subscription->expires_at && ! in_array($reason ?? '', ['pending_approval', 'rejected'], true))
            <p>Expiry date: {{ $subscription->expires_at->format('d M Y') }}</p>
        @endif
        <p>
            <a href="{{ route('billing.index') }}" class="btn btn-primary">View invoices &amp; payment history</a>
        </p>
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button type="submit" class="btn btn-danger">Sign out</button>
        </form>
    </div>
</div>
@endsection
