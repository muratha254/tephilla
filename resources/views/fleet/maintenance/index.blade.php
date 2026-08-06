@extends('layouts.fleet')

@section('title', 'Maintenance Management')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-maintenance.css') }}?v=3">
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
        <h1 class="fleet-page-title">Maintenance Management</h1>
        <a href="{{ route('maintenance.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add Maintenance</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Maintenance</li>
    </ul>
</div>

<div class="fleet-maint-quick-links">
    <a href="{{ route('maintenance.pms.index') }}" class="fleet-maint-quick-card">
        <i class="fa fa-calendar-check-o"></i>
        <span>PMS Scheduler</span>
    </a>
    <a href="{{ route('incidents.index') }}" class="fleet-maint-quick-card">
        <i class="fa fa-exclamation-triangle"></i>
        <span>Incident Reports</span>
    </a>
    <a href="{{ route('tyres.index') }}" class="fleet-maint-quick-card">
        <i class="fa fa-circle-o"></i>
        <span>Tyre Management</span>
    </a>
    <a href="{{ route('maintenance.cost-analytics') }}" class="fleet-maint-quick-card">
        <i class="fa fa-pie-chart"></i>
        <span>Cost Analytics</span>
    </a>
</div>

<div class="fleet-maint-toolbar">
    <div class="fleet-maint-status-pills">
        <a href="{{ route('maintenance.index', $queryParams(['status' => ''])) }}" class="fleet-maint-pill fleet-maint-pill-all {{ $statusFilter === '' ? 'active' : '' }}">
            <strong>{{ $counts['all'] }}</strong> All Statuses
        </a>
        <a href="{{ route('maintenance.index', $queryParams(['status' => 'Planned'])) }}" class="fleet-maint-pill fleet-maint-pill-planned {{ $statusFilter === 'Planned' ? 'active' : '' }}">
            <strong>{{ $counts['planned'] }}</strong> Planned
        </a>
        <a href="{{ route('maintenance.index', $queryParams(['status' => 'Ongoing'])) }}" class="fleet-maint-pill fleet-maint-pill-progress {{ $statusFilter === 'Ongoing' ? 'active' : '' }}">
            <strong>{{ $counts['ongoing'] }}</strong> In Progress
        </a>
        <a href="{{ route('maintenance.index', $queryParams(['status' => 'Completed'])) }}" class="fleet-maint-pill fleet-maint-pill-completed {{ $statusFilter === 'Completed' ? 'active' : '' }}">
            <strong>{{ $counts['completed'] }}</strong> Completed
        </a>
    </div>

    <form method="GET" action="{{ route('maintenance.index') }}" class="fleet-maint-filters">
        @if ($statusFilter !== '')
            <input type="hidden" name="status" value="{{ $statusFilter }}">
        @endif
        @if ($viewMode === 'list')
            <input type="hidden" name="view" value="list">
        @endif
        <div class="fleet-search-wrap">
            <i class="fa fa-search"></i>
            <input type="text" name="search" class="fleet-search-input" value="{{ $search }}" placeholder="Search vehicle name, model, details...">
        </div>
        <select name="status" class="fleet-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            @foreach ($statuses as $statusOption)
                <option value="{{ $statusOption }}" {{ $statusFilter === $statusOption ? 'selected' : '' }}>
                    {{ $statusOption === 'Ongoing' ? 'In Progress' : $statusOption }}
                </option>
            @endforeach
        </select>
        <div class="fleet-view-toggle">
            <a href="{{ route('maintenance.index', $queryParams(['view' => 'grid'])) }}" class="fleet-view-icon {{ $viewMode === 'grid' ? 'active' : '' }}" aria-label="Grid view"><i class="fa fa-th"></i></a>
            <a href="{{ route('maintenance.index', $queryParams(['view' => 'list'])) }}" class="fleet-view-icon {{ $viewMode === 'list' ? 'active' : '' }}" aria-label="List view"><i class="fa fa-list"></i></a>
        </div>
    </form>
</div>

@if ($viewMode === 'list')
<div class="fleet-panel fleet-vehicle-card">
    <div class="fleet-table-wrap">
        <table class="fleet-vehicle-table">
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Schedule</th>
                    <th>Cost</th>
                    <th>Service</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($maintenances as $maintenance)
                <tr>
                    <td>
                        <div class="fleet-vehicle-name">{{ optional($maintenance->vehicle)->displayName() ?: '-' }}</div>
                        <div class="fleet-vehicle-meta">{{ optional($maintenance->vehicle)->registration_number }}</div>
                    </td>
                    <td>{{ $maintenance->statusDisplayLabel() }}</td>
                    <td>{{ format_fleet_date($maintenance->start_date) }}</td>
                    <td>{{ format_kes($maintenance->total_cost) }}</td>
                    <td>{{ $maintenance->cardSummary() }}</td>
                    <td>
                        @include('fleet.maintenance.partials.card_actions', ['maintenance' => $maintenance, 'compact' => true])
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="fleet-empty-row">No maintenance records found. <a href="{{ route('maintenance.create') }}">Add Maintenance</a></td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@else
<div class="fleet-maint-grid">
    @forelse ($maintenances as $maintenance)
    <div class="fleet-maint-card">
        <div class="fleet-maint-card-head">
            <div class="fleet-maint-card-vehicle">{{ optional($maintenance->vehicle)->displayName() ?: 'Unknown Vehicle' }}</div>
            <form method="POST" action="{{ route('maintenance.update-status', $maintenance) }}" class="fleet-maint-status-form">
                @csrf
                @method('PATCH')
                <select name="status" class="fleet-maint-card-status" onchange="this.form.submit()">
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption }}" {{ $maintenance->status === $statusOption ? 'selected' : '' }}>
                            {{ $statusOption === 'Ongoing' ? 'In Progress' : $statusOption }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="fleet-maint-card-meta">
            <span><i class="fa fa-calendar"></i> {{ format_fleet_date($maintenance->start_date) }}</span>
            <strong>{{ format_kes($maintenance->total_cost) }}</strong>
        </div>

        <div class="fleet-maint-card-body">
            <i class="fa fa-wrench"></i>
            <p>{{ $maintenance->cardSummary() }}</p>
        </div>

        @include('fleet.maintenance.partials.card_actions', ['maintenance' => $maintenance])
    </div>
    @empty
    <div class="fleet-maint-empty">
        No maintenance records found. <a href="{{ route('maintenance.create') }}">Add Maintenance</a>
    </div>
    @endforelse
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('.fleet-maint-print-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var printWindow = window.open(this.href, '_blank');
            if (printWindow) {
                printWindow.onload = function () {
                    printWindow.focus();
                    printWindow.print();
                };
            }
        });
    });
})();
</script>
@endpush
