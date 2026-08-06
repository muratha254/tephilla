@extends('layouts.fleet')

@section('title', 'Edit Reminder')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-reminders.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head fleet-reminder-page-head">
    <h1 class="fleet-page-title">
        <span class="fleet-reminder-title-icon"><i class="fa fa-th"></i></span>
        Edit Reminder
    </h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('reminders.index') }}">Reminder</a></li>
        <li>Edit Reminder</li>
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

@include('fleet.reminders.partials.form', [
    'reminder' => $reminder,
    'formAction' => route('reminders.update', $reminder),
    'formMethod' => 'PUT',
    'vehicles' => $vehicles,
])
@endsection
