@extends('layouts.fleet')

@section('title', 'Edit Driver')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-drivers.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Edit Driver</h1>
        <a href="{{ route('drivers.index') }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-list"></i> View List</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('drivers.index') }}">Drivers</a></li>
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

<form action="{{ route('drivers.update', $driver) }}" method="POST" enctype="multipart/form-data" class="fleet-driver-form">
    @csrf
    @method('PUT')
    @include('fleet.drivers.partials.form', ['driver' => $driver])
</form>
@endsection

@push('scripts')
@include('fleet.drivers.partials.form_scripts')
@endpush
