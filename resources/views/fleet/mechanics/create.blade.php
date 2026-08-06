@extends('layouts.fleet')

@section('title', 'Add Mechanic')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-mechanics.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Add Mechanic</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('mechanics.index') }}">Mechanics</a></li>
        <li>Add Mechanic</li>
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

<form action="{{ route('mechanics.store') }}" method="POST">
    @csrf
    @include('fleet.mechanics.partials.form', ['mechanic' => null])
</form>
@endsection
