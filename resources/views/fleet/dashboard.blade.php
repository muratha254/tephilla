@extends('layouts.fleet')

@section('title', 'Dashboard')

@section('content')
<h1 class="fleet-page-title">Dashboard</h1>
<ul class="fleet-breadcrumb">
    <li>Home</li>
    <li>Dashboard</li>
</ul>

<div class="fleet-panel" style="max-width: 640px;">
    <div class="fleet-panel-header">Welcome to {{ $systemName ?? fleet_system_name() }}</div>
    <div class="fleet-panel-body">
        <p style="margin: 0 0 12px;">This is a new POS. There is no sales, stock, or customer data yet.</p>
        <p style="margin: 0;">Use <a href="{{ route('settings.general') }}">Settings</a> to set company details and the login footer, then start adding stock and making sales.</p>
    </div>
</div>
@endsection
