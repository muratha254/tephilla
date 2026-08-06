@extends('layouts.fleet')

@section('title', 'Vehicle Group')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-groups.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Vehicle Group</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('vehicles.index') }}">Vehicle</a></li>
        <li>Vehicle Group</li>
    </ul>
</div>

<div class="fleet-vehicle-toolbar">
    <div class="fleet-group-summary">
        <span class="fleet-group-count">{{ $groups->count() }} group{{ $groups->count() === 1 ? '' : 's' }}</span>
    </div>
    <a href="{{ route('vehicle-groups.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add Group</a>
</div>

<div class="fleet-panel fleet-vehicle-card">
    <div class="fleet-table-wrap">
        <table class="fleet-vehicle-table">
            <thead>
                <tr>
                    <th>Group Name</th>
                    <th>Description</th>
                    <th>Vehicles</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                <tr>
                    <td>
                        <div class="fleet-vehicle-name">{{ $group['name'] }}</div>
                    </td>
                    <td>
                        <div class="fleet-vehicle-meta">{{ $group['description'] ?: '-' }}</div>
                    </td>
                    <td>
                        <span class="fleet-group-badge">{{ $group['vehicle_count'] }}</span>
                    </td>
                    <td>
                        <span class="fleet-status-tag {{ $group['status'] === 'Active' ? 'fleet-status-active' : 'fleet-status-inactive' }}">
                            {{ $group['status'] }}
                        </span>
                    </td>
                    <td>
                        <div class="fleet-action-btns">
                            <a href="{{ route('vehicle-groups.edit', $group['id']) }}" class="fleet-action-btn edit" title="Edit"><i class="fa fa-pencil"></i></a>
                            <form action="{{ route('vehicle-groups.destroy', $group['id']) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this vehicle group?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="fleet-empty-row">No vehicle groups yet. <a href="{{ route('vehicle-groups.create') }}">Create your first group</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
