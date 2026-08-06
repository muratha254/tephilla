@extends('layouts.fleet')

@section('title', 'Add Customer')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-customers.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Add Customer</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('customers.index') }}">Customer</a></li>
        <li>Add Customer</li>
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

<form action="{{ route('customers.store') }}" method="POST" class="fleet-customer-form">
    @csrf
    @include('fleet.customers.partials.form', ['customer' => null])
</form>
@endsection

@push('scripts')
@include('fleet.customers.partials.form_scripts')
@endpush
