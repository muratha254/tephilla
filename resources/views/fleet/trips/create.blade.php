@extends('layouts.fleet')

@section('title', 'New Booking')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-trips.css') }}?v=8">
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">New Booking</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('trips.index') }}">Trips</a></li>
        <li>Add</li>
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

@include('fleet.trips.partials.booking_form', [
    'trip' => null,
    'formOptions' => $formOptions,
    'formAction' => route('trips.store'),
    'submitLabel' => 'Create Booking',
])
@endsection

@push('scripts')
@include('fleet.trips.partials.booking_form_scripts')
@endpush
