@extends('layouts.fleet')

@section('title', 'Add Maintenance')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-maintenance.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Add Maintenance</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('maintenance.index') }}">Maintenance</a></li>
        <li>Add</li>
    </ul>
</div>

@if ($errors->any())
<div class="fleet-alert fleet-alert-error">
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@include('fleet.maintenance.partials.form', [
    'maintenance' => null,
    'formOptions' => $formOptions,
    'checklistItems' => $checklistItems,
    'formAction' => route('maintenance.store'),
    'formMethod' => 'POST',
    'submitLabel' => 'Save Maintenance',
    'cancelUrl' => route('maintenance.index'),
])
@endsection

@push('scripts')
@include('fleet.maintenance.partials.form_scripts')
@endpush
