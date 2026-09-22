@extends('auth.register.layout')

@section('title', 'Request submitted')

@section('register-body')
<div class="login-submitted">
    <h2>Request submitted</h2>
    <p>Your subscription request is <strong>pending System Owner approval</strong>.</p>
    <p>You do not have access to the dashboard until the System Owner reviews and approves this request.</p>
    <p>If you sign in before approval, you will see a pending-approval page.</p>
    <a href="{{ route('login') }}" class="login-submit" style="text-decoration:none;">Back to sign in</a>
</div>
@endsection
