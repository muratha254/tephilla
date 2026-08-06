@extends('layouts.fleet')

@section('title', 'Edit Maintenance')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-maintenance.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Edit Maintenance</h1>
        <a href="{{ route('maintenance.show', $maintenance) }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-eye"></i> View Details</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('maintenance.index') }}">Maintenance</a></li>
        <li>Edit</li>
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
    'maintenance' => $maintenance,
    'formOptions' => $formOptions,
    'checklistItems' => $checklistItems,
    'formAction' => route('maintenance.update', $maintenance),
    'formMethod' => 'PUT',
    'submitLabel' => 'Update Maintenance',
    'cancelUrl' => route('maintenance.show', $maintenance),
])
@endsection

@push('scripts')
@include('fleet.maintenance.partials.form_scripts')
@endpush
