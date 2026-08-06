@extends('layouts.fleet')

@section('title', 'Vehicle Management')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Vehicle Management</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Vehicle's Management</li>
    </ul>
</div>

<div class="fleet-vehicle-toolbar">
    <div class="fleet-status-pills">
        <button type="button" class="fleet-pill fleet-pill-blue active">All Vehicles ({{ $counts['all'] }})</button>
        <button type="button" class="fleet-pill fleet-pill-green">Active ({{ $counts['active'] }})</button>
        <button type="button" class="fleet-pill fleet-pill-red">Inactive ({{ $counts['inactive'] }})</button>
        <button type="button" class="fleet-pill fleet-pill-red-outline"><i class="fa fa-tint"></i> Low Fuel ({{ $counts['low_fuel'] }})</button>
    </div>
    <a href="{{ route('vehicles.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add Vehicle</a>
</div>

<div class="fleet-panel fleet-vehicle-card">
    <div class="fleet-vehicle-filters">
        <div class="fleet-search-wrap">
            <i class="fa fa-search"></i>
            <input type="text" class="fleet-search-input" placeholder="Search vehicle name, model, reg no...">
        </div>
        <select class="fleet-select">
            <option>All Statuses</option>
            <option>Active</option>
            <option>Inactive</option>
        </select>
        <div class="fleet-view-toggle">
            <button type="button" class="fleet-view-icon" aria-label="Grid view"><i class="fa fa-th"></i></button>
            <button type="button" class="fleet-view-icon active" aria-label="List view"><i class="fa fa-list"></i></button>
        </div>
    </div>

    <div class="fleet-table-wrap">
        <table class="fleet-vehicle-table">
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Driver &amp; Fuel</th>
                    <th>Group &amp; Owner</th>
                    <th>Status &amp; Expiry</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vehicles as $vehicle)
                @php
                    $fuelPct = $vehicle['fuel_max'] > 0
                        ? min(100, round(($vehicle['fuel_liters'] / $vehicle['fuel_max']) * 100))
                        : 0;
                    $fuelClass = $vehicle['low_fuel'] ? 'is-low' : 'is-ok';
                @endphp
                <tr>
                    <td>
                        <div class="fleet-vehicle-name">{{ $vehicle['name'] }}</div>
                        <div class="fleet-vehicle-meta">
                            {{ $vehicle['reg_no'] }} | {{ $vehicle['model'] }}
                            @if ($vehicle['type'])
                                | {{ $vehicle['type'] }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="fleet-driver"><i class="fa fa-user"></i> {{ $vehicle['driver'] }}</div>
                        <div class="fleet-fuel-row">
                            <div class="fleet-fuel-bar {{ $fuelClass }}">
                                <span style="width: {{ $fuelPct }}%;"></span>
                            </div>
                            <span class="fleet-fuel-text">{{ $vehicle['fuel_liters'] }} L</span>
                        </div>
                    </td>
                    <td>
                        @if ($vehicle['group'])
                            <div class="fleet-meta-line"><strong>Group:</strong> {{ $vehicle['group'] }}</div>
                        @endif
                        <div class="fleet-meta-line"><strong>Owner:</strong> {{ $vehicle['owner'] }}</div>
                        @if ($vehicle['lease_start'])
                            <div class="fleet-meta-line"><strong>Lease Start:</strong> {{ $vehicle['lease_start'] }}</div>
                        @endif
                        @if ($vehicle['lease_end'])
                            <div class="fleet-meta-line"><strong>Lease End:</strong> {{ $vehicle['lease_end'] }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="fleet-status-tag fleet-status-active">{{ $vehicle['status'] }}</span>
                        @if ($vehicle['reg_expiry'])
                            <div class="fleet-meta-line"><strong>Reg Exp:</strong> {{ $vehicle['reg_expiry'] }}</div>
                        @endif
                        @if ($vehicle['ins_expiry'])
                            <div class="fleet-meta-line"><strong>Ins Exp:</strong> {{ $vehicle['ins_expiry'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="fleet-action-btns">
                            <a href="{{ route('vehicles.show', $vehicle['id']) }}" class="fleet-action-btn view" title="View"><i class="fa fa-eye"></i></a>
                            <a href="{{ route('vehicles.edit', $vehicle['id']) }}" class="fleet-action-btn edit" title="Edit"><i class="fa fa-pencil"></i></a>
                            <form action="{{ route('vehicles.destroy', $vehicle['id']) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this vehicle?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="fleet-empty-row">No vehicles yet. <a href="{{ route('vehicles.create') }}">Add your first vehicle</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
