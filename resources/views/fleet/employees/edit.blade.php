@extends('layouts.fleet')

@section('title', 'Edit Employee')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-employees.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Employee Details</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('employees.index') }}">Employee</a></li>
        <li>Edit Employee</li>
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

<div class="fleet-panel fleet-employee-form-card">
    <div class="fleet-employee-form-head">
        <h2>Employee Details</h2>
        <a href="{{ route('employees.index') }}" class="fleet-btn-back-list"><i class="fa fa-arrow-left"></i> Back to List</a>
    </div>
    <div class="fleet-panel-body fleet-employee-form-body">
        <form action="{{ route('employees.update', $employee) }}" method="POST" class="fleet-employee-form">
            @csrf
            @method('PUT')
            @include('fleet.employees.partials.form', ['employee' => $employee, 'permissionGroups' => $permissionGroups])
        </form>
    </div>
</div>
@endsection

@push('scripts')
@include('fleet.employees.partials.form_scripts')
@endpush
