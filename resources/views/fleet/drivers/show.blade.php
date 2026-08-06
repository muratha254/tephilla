@extends('layouts.fleet')



@section('title', 'Driver Info')



@push('css')

<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">

<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">

<link rel="stylesheet" href="{{ asset('css/fleet-drivers.css') }}?v=3">

@endpush



@section('content')

@php

    $photoUrl = $driver->photo_path ? asset('storage/' . $driver->photo_path) : null;

@endphp



<div class="fleet-page-head">

    <div class="fleet-page-title-row">

        <h1 class="fleet-page-title">{{ $driver->name }}</h1>

        <a href="{{ route('drivers.index') }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-list"></i> View List</a>

    </div>

    <ul class="fleet-breadcrumb fleet-breadcrumb-right">

        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>

        <li><a href="{{ route('drivers.index') }}">Driver Info</a></li>

        <li>View</li>

    </ul>

</div>



<div class="fleet-driver-view-layout">

    <div class="fleet-driver-form-grid">

        <div class="fleet-panel fleet-driver-photo-card">

            <div class="fleet-panel-header">Driver Photo</div>

            <div class="fleet-panel-body fleet-driver-photo-panel">

                <div class="fleet-driver-photo-preview">

                    @if ($photoUrl)

                        <a href="{{ $photoUrl }}" target="_blank" rel="noopener">

                            <img src="{{ $photoUrl }}" alt="{{ $driver->name }}">

                        </a>

                    @else

                        <i class="fa fa-user fleet-driver-photo-placeholder"></i>

                    @endif

                </div>

                <span class="fleet-status-tag {{ $driver->status === 'Active' ? 'fleet-status-active' : 'fleet-status-inactive' }}">{{ $driver->status }}</span>

            </div>

        </div>



        <div class="fleet-panel fleet-driver-form-card">

            <div class="fleet-panel-header">Driver Details</div>

            <div class="fleet-panel-body">

                <div class="fleet-detail-grid">

                    <div class="fleet-detail-item"><span>Mobile</span><strong>{{ $driver->mobile }}</strong></div>

                    <div class="fleet-detail-item"><span>Email</span><strong>{{ $driver->email }}</strong></div>

                    <div class="fleet-detail-item"><span>Age</span><strong>{{ $driver->age }}</strong></div>

                    <div class="fleet-detail-item"><span>Date Of Joining</span><strong>{{ format_fleet_date($driver->date_of_joining) }}</strong></div>

                    <div class="fleet-detail-item"><span>License No</span><strong>{{ $driver->license_number ?: '-' }}</strong></div>

                    <div class="fleet-detail-item"><span>License Expiry</span><strong>{{ format_fleet_date($driver->license_expiry) }}</strong></div>

                    <div class="fleet-detail-item"><span>ID Number</span><strong>{{ $driver->id_number ?: '-' }}</strong></div>

                    <div class="fleet-detail-item"><span>Employee ID</span><strong>{{ $driver->employee_id ?: '-' }}</strong></div>

                    <div class="fleet-detail-item"><span>Department</span><strong>{{ $driver->department ?: '-' }}</strong></div>

                    <div class="fleet-detail-item"><span>Employment Type</span><strong>{{ $driver->employment_type ?: '-' }}</strong></div>

                    <div class="fleet-detail-item"><span>Contract End Date</span><strong>{{ format_fleet_date($driver->contract_end_date) }}</strong></div>

                    <div class="fleet-detail-item"><span>License Status</span><strong class="{{ ($driver->licenseExpiryDays() ?? 1) < 0 ? 'is-expired-text' : 'is-valid-text' }}">{{ $driver->licenseExpiryLabel() }}</strong></div>

                </div>

                <div class="fleet-driver-form-footer" style="border-top:none;padding-top:16px;">

                    <a href="{{ route('drivers.edit', $driver) }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-pencil"></i> Edit Driver</a>

                </div>

            </div>

        </div>

    </div>



    @include('fleet.drivers.partials.documents')

</div>

@endsection


