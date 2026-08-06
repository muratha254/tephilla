@extends('layouts.fleet')

@section('title', 'Stock Management')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-stock.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Stock Management</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Stock Management</li>
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

@include('fleet.stock.partials.form', [
    'item' => null,
    'formAction' => route('stock.store'),
    'formMethod' => 'POST',
])
@endsection
