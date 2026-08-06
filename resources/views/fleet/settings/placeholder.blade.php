@extends('layouts.fleet')

@section('title', $sectionLabel)

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-settings.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">{{ $sectionLabel }}</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('settings.general') }}">Settings</a></li>
        <li>{{ $sectionLabel }}</li>
    </ul>
</div>

<div class="fleet-panel fleet-settings-placeholder-card">
    <div class="fleet-settings-placeholder-body">
        <i class="fa fa-cog"></i>
        <h2>{{ $sectionLabel }}</h2>
        <p>This section is coming soon.</p>
        <a href="{{ route('settings.general') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-arrow-left"></i> Back to General Settings</a>
    </div>
</div>
@endsection
