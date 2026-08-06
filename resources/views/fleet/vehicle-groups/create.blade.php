@extends('layouts.fleet')

@section('title', 'Add Vehicle Group')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-groups.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Add Vehicle Group</h1>
        <a href="{{ route('vehicle-groups.index') }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-list"></i> View Groups</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('vehicle-groups.index') }}">Vehicle Group</a></li>
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

<div class="fleet-panel fleet-group-form-card">
    <div class="fleet-panel-header">Group Details</div>
    <div class="fleet-panel-body">
        <form action="{{ route('vehicle-groups.store') }}" method="POST" class="fleet-group-form">
            @csrf
            @include('fleet.vehicle-groups.partials.form', ['group' => null])
        </form>
    </div>
</div>
@endsection
