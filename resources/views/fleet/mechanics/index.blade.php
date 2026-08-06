@extends('layouts.fleet')

@section('title', 'Mechanic Management')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-mechanics.css') }}?v=1">
@endpush

@section('content')
@php
    $queryParams = fn (array $extra = []) => array_filter(array_merge([
        'search' => $search,
        'view' => $viewMode,
    ], $extra), fn ($value) => $value !== null && $value !== '');
@endphp

<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Mechanic Management</h1>
        <a href="{{ route('mechanics.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add New</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Mechanics</li>
    </ul>
</div>

<div class="fleet-panel fleet-mechanic-panel">
    <div class="fleet-mechanic-toolbar">
        <div class="fleet-mechanic-count">
            <strong>{{ $totalCount }}</strong> Total Mechanics
        </div>

        <form method="GET" action="{{ route('mechanics.index') }}" class="fleet-mechanic-filters">
            @if ($viewMode === 'list')
                <input type="hidden" name="view" value="list">
            @endif
            <div class="fleet-search-wrap">
                <i class="fa fa-search"></i>
                <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search Mechanic...">
            </div>
            <div class="fleet-view-toggle">
                <a href="{{ route('mechanics.index', $queryParams(['view' => 'grid'])) }}" class="fleet-view-icon {{ $viewMode === 'grid' ? 'active' : '' }}" aria-label="Grid view"><i class="fa fa-th"></i></a>
                <a href="{{ route('mechanics.index', $queryParams(['view' => 'list'])) }}" class="fleet-view-icon {{ $viewMode === 'list' ? 'active' : '' }}" aria-label="List view"><i class="fa fa-list"></i></a>
            </div>
        </form>
    </div>

    @if ($viewMode === 'list')
    <div class="fleet-table-wrap">
        <table class="fleet-mechanic-list-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Specialty</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mechanics as $mechanic)
                <tr>
                    <td>
                        <div class="fleet-mechanic-list-name">
                            <span class="fleet-mechanic-avatar fleet-mechanic-avatar-sm">{{ $mechanic->initial() }}</span>
                            <span>{{ $mechanic->name }}</span>
                        </div>
                    </td>
                    <td><span class="fleet-mechanic-badge">{{ $mechanic->specialty }}</span></td>
                    <td>{{ $mechanic->email ?: '-' }}</td>
                    <td>{{ $mechanic->phone ?: '-' }}</td>
                    <td>
                        <div class="fleet-action-btns">
                            <a href="{{ route('mechanics.edit', $mechanic) }}" class="fleet-mechanic-edit-btn"><i class="fa fa-pencil"></i> Edit</a>
                            <form action="{{ route('mechanics.destroy', $mechanic) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this mechanic?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="fleet-empty-row">No mechanics found. <a href="{{ route('mechanics.create') }}">Add your first mechanic</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @else
    <div class="fleet-mechanic-grid">
        @forelse ($mechanics as $mechanic)
        <div class="fleet-mechanic-card">
            <div class="fleet-mechanic-card-body">
                <div class="fleet-mechanic-avatar">{{ $mechanic->initial() }}</div>
                <h3 class="fleet-mechanic-name">{{ $mechanic->name }}</h3>
                <span class="fleet-mechanic-badge">{{ $mechanic->specialty }}</span>

                <div class="fleet-mechanic-contact">
                    @if ($mechanic->email)
                    <div class="fleet-mechanic-contact-line">
                        <i class="fa fa-envelope"></i>
                        <span>{{ $mechanic->email }}</span>
                    </div>
                    @endif
                    @if ($mechanic->phone)
                    <div class="fleet-mechanic-contact-line">
                        <i class="fa fa-phone"></i>
                        <span>{{ $mechanic->phone }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <div class="fleet-mechanic-card-actions">
                <a href="{{ route('mechanics.edit', $mechanic) }}" class="fleet-mechanic-edit-btn"><i class="fa fa-pencil"></i> Edit</a>
                <form action="{{ route('mechanics.destroy', $mechanic) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this mechanic?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                </form>
            </div>
        </div>
        @empty
        <div class="fleet-mechanic-empty">
            No mechanics found. <a href="{{ route('mechanics.create') }}">Add your first mechanic</a>.
        </div>
        @endforelse
    </div>
    @endif
</div>
@endsection
