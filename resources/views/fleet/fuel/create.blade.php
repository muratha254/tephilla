@extends('layouts.fleet')

@section('title', 'Add Fuel')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-maintenance.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('css/fleet-fuel.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Add Fuel</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('fuel.index') }}">Fuel</a></li>
        <li>Add Fuel</li>
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

@include('fleet.fuel.partials.form', [
    'refill' => null,
    'formAction' => route('fuel.store'),
    'formMethod' => 'POST',
    'vehicles' => $vehicles,
    'drivers' => $drivers,
    'fuelTypes' => $fuelTypes,
    'vendors' => $vendors,
    'fuelStockLiters' => $fuelStockLiters,
    'fuelStockUnitPrice' => $fuelStockUnitPrice,
    'fuelStockItemId' => $fuelStockItemId,
])
@endsection

@push('scripts')
@include('fleet.fuel.partials.calc_scripts')
@endpush
