@extends('layouts.fleet')

@section('title', 'Edit Vehicle')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Edit Vehicle</h1>
        <a href="{{ route('vehicles.show', $vehicle) }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-eye"></i> View Details</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('vehicles.index') }}">Vehicle</a></li>
        <li>Edit</li>
    </ul>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="fleet-form-errors">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('vehicles.update', $vehicle) }}" method="POST" enctype="multipart/form-data" class="fleet-vehicle-form">
    @csrf
    @method('PUT')
    @include('fleet.vehicles.partials.form', [
        'vehicle' => $vehicle,
        'submitLabel' => 'Update Vehicle',
        'cancelUrl' => route('vehicles.show', $vehicle),
    ])
</form>

@include('fleet.vehicles.partials.lookup_modals')
@endsection

@push('scripts')
@include('fleet.vehicles.partials.form_scripts')
@endpush
