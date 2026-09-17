@extends('layouts.fleet')

@section('title', 'Edit Trip')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-trips.css') }}?v=8">
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Edit Trip - {{ $trip->displayTripCode() }}</h1>
        <a href="{{ route('trips.show', $trip) }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-arrow-left"></i> Back to Trip</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('trips.index') }}">Trips</a></li>
        <li><a href="{{ route('trips.show', $trip) }}">Details</a></li>
        <li>Edit</li>
    </ul>
</div>

@if (session('success'))
    <div class="fleet-alert fleet-alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="fleet-form-errors">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@include('fleet.trips.partials.booking_form', [
    'trip' => $trip,
    'formOptions' => $formOptions,
    'formAction' => route('trips.update', $trip),
    'submitLabel' => 'Save Changes',
    'statuses' => $statuses,
])
@endsection

@push('scripts')
@include('fleet.trips.partials.booking_form_scripts')
@endpush
