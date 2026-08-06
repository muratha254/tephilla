@extends('layouts.fleet')

@section('title', 'Reminder Info')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-reminders.css') }}?v=2">
@endpush

@section('content')
@php
    $queryParams = fn (array $extra = []) => array_filter(array_merge([
        'search' => $search,
        'status' => $statusFilter,
        'view' => $viewMode,
    ], $extra), fn ($value) => $value !== null && $value !== '');
@endphp

<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Reminder Info</h1>
        <a href="{{ route('reminders.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add Reminder</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Reminder Info</li>
    </ul>
</div>

<div class="fleet-panel fleet-reminder-panel">
    <div class="fleet-reminder-toolbar">
        <div class="fleet-reminder-summary">
            <a href="{{ route('reminders.index', $queryParams(['status' => ''])) }}" class="fleet-reminder-pill fleet-reminder-pill-total {{ $statusFilter === '' ? 'active' : '' }}">
                <strong>{{ $counts['total'] }}</strong> Total
            </a>
            <a href="{{ route('reminders.index', $queryParams(['status' => 'completed'])) }}" class="fleet-reminder-pill fleet-reminder-pill-completed {{ $statusFilter === 'completed' ? 'active' : '' }}">
                <strong>{{ $counts['completed'] }}</strong> Completed
            </a>
            <a href="{{ route('reminders.index', $queryParams(['status' => 'pending'])) }}" class="fleet-reminder-pill fleet-reminder-pill-pending {{ $statusFilter === 'pending' ? 'active' : '' }}">
                <strong>{{ $counts['pending'] }}</strong> Pending
            </a>
        </div>

        <form method="GET" action="{{ route('reminders.index') }}" class="fleet-reminder-filters">
            @if ($statusFilter !== '')
                <input type="hidden" name="status" value="{{ $statusFilter }}">
            @endif
            @if ($viewMode === 'list')
                <input type="hidden" name="view" value="list">
            @endif
            <div class="fleet-search-wrap">
                <i class="fa fa-search"></i>
                <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search Reminders...">
            </div>
            <div class="fleet-view-toggle">
                <a href="{{ route('reminders.index', $queryParams(['view' => 'grid'])) }}" class="fleet-view-icon {{ $viewMode === 'grid' ? 'active' : '' }}" aria-label="Grid view"><i class="fa fa-th"></i></a>
                <a href="{{ route('reminders.index', $queryParams(['view' => 'list'])) }}" class="fleet-view-icon {{ $viewMode === 'list' ? 'active' : '' }}" aria-label="List view"><i class="fa fa-list"></i></a>
            </div>
        </form>
    </div>

    @if ($viewMode === 'list')
    <div class="fleet-table-wrap">
        <table class="fleet-reminder-list-table">
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Services</th>
                    <th>Notes</th>
                    <th>Complete</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reminders as $reminder)
                <tr>
                    <td>{{ optional($reminder->vehicle)->displayName() ?: '-' }}</td>
                    <td>{{ $reminder->badgeDate() }}</td>
                    <td><span class="fleet-reminder-status-bar {{ $reminder->statusClass() }}">{{ $reminder->statusLabel() }}</span></td>
                    <td>{{ $reminder->servicesSummary(80) }}</td>
                    <td>{{ $reminder->notes ?: '-' }}</td>
                    <td>
                        @include('fleet.reminders.partials.complete_toggle', ['reminder' => $reminder])
                    </td>
                    <td>
                        @include('fleet.reminders.partials.row_actions', ['reminder' => $reminder])
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="fleet-empty-row">No reminders found. <a href="{{ route('reminders.create') }}">Add a reminder</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @else
    <div class="fleet-reminder-grid">
        @forelse ($reminders as $reminder)
        <div class="fleet-reminder-card">
            <div class="fleet-reminder-card-head">
                <div class="fleet-reminder-card-vehicle">
                    <i class="fa fa-truck"></i>
                    <span>{{ optional($reminder->vehicle)->displayName() ?: 'Unknown Vehicle' }}</span>
                </div>
                <span class="fleet-reminder-date-badge">{{ $reminder->badgeDate() }}</span>
            </div>

            <div class="fleet-reminder-status-bar {{ $reminder->statusClass() }}">{{ $reminder->statusLabel() }}</div>

            <div class="fleet-reminder-card-body">
                <div class="fleet-reminder-services">
                    <i class="fa fa-wrench"></i>
                    <i class="fa fa-commenting-o"></i>
                    <span>{{ $reminder->servicesSummary() }}</span>
                </div>
                @if ($reminder->notes)
                <p class="fleet-reminder-note">{{ $reminder->notes }}</p>
                @endif
            </div>

            <div class="fleet-reminder-card-footer">
                @include('fleet.reminders.partials.complete_toggle', ['reminder' => $reminder])
                <form action="{{ route('reminders.destroy', $reminder) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this reminder?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="fleet-reminder-delete-btn" title="Delete"><i class="fa fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
        @empty
        <div class="fleet-reminder-empty">
            No reminders found. <a href="{{ route('reminders.create') }}">Add your first reminder</a>.
        </div>
        @endforelse
    </div>
    @endif
</div>
@endsection
