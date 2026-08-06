@extends('layouts.fleet')

@section('title', 'Edit Customer')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-customers.css') }}?v=3">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Edit Customer</h1>
        <a href="{{ route('customers.show', $customer) }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-eye"></i> View Details</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('customers.index') }}">Customer Info</a></li>
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

<form action="{{ route('customers.update', $customer) }}" method="POST" class="fleet-customer-form">
    @csrf
    @method('PUT')
    @include('fleet.customers.partials.form', ['customer' => $customer, 'submitLabel' => 'Update'])
</form>
@endsection

@push('scripts')
@include('fleet.customers.partials.form_scripts')
@endpush
