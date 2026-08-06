@extends('layouts.fleet')

@section('title', 'Maintenance Details')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-maintenance.css') }}?v=2">
@endpush

@section('content')
@php
    $progress = $maintenance->checklistProgress();
    $checklist = collect($maintenance->checklist ?? []);
@endphp

<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Maintenance Details</h1>
        <div class="fleet-page-actions fleet-maint-show-actions">
            <a href="{{ route('maintenance.edit', $maintenance) }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-pencil"></i> Edit</a>
            <form action="{{ route('maintenance.destroy', $maintenance) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this maintenance record?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="fleet-btn fleet-btn-danger"><i class="fa fa-trash"></i> Delete</button>
            </form>
            <a href="{{ route('maintenance.index') }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-list"></i> Back to List</a>
        </div>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('maintenance.index') }}">Maintenance</a></li>
        <li>Details</li>
    </ul>
</div>

<div class="fleet-maint-show-summary">
    <div class="fleet-maint-show-chip">
        <span>Vehicle</span>
        <strong>{{ optional($maintenance->vehicle)->displayName() ?: '-' }}</strong>
        <small>{{ optional($maintenance->vehicle)->registration_number }}</small>
    </div>
    <div class="fleet-maint-show-chip">
        <span>Status</span>
        <strong><span class="fleet-maint-status-tag">{{ $maintenance->statusLabel() }}</span></strong>
    </div>
    <div class="fleet-maint-show-chip">
        <span>Priority</span>
        <strong><span class="fleet-maint-priority fleet-maint-priority-{{ strtolower($maintenance->priority) }}">{{ $maintenance->priority }}</span></strong>
    </div>
    <div class="fleet-maint-show-chip">
        <span>Checklist</span>
        <strong>{{ $progress['done'] }}/{{ $progress['total'] }} ({{ $progress['percent'] }}%)</strong>
    </div>
</div>

<div class="fleet-maint-top">
    <div class="fleet-maint-section fleet-maint-section-blue">
        <div class="fleet-maint-section-head">
            <i class="fa fa-truck"></i>
            <h2>Vehicle Service Details</h2>
        </div>
        <div class="fleet-maint-section-body">
            <div class="fleet-maint-detail-grid">
                <div class="fleet-maint-detail-item">
                    <span>Vehicle</span>
                    <strong>{{ optional($maintenance->vehicle)->displayName() ?: '-' }}</strong>
                </div>
                <div class="fleet-maint-detail-item">
                    <span>Status</span>
                    <strong>{{ $maintenance->statusLabel() }}</strong>
                </div>
                <div class="fleet-maint-detail-item">
                    <span>Start Date</span>
                    <strong>{{ optional($maintenance->start_date)->format('d M Y') ?: '-' }}</strong>
                </div>
                <div class="fleet-maint-detail-item">
                    <span>End Date</span>
                    <strong>{{ optional($maintenance->end_date)->format('d M Y') ?: '-' }}</strong>
                </div>
            </div>
            <div class="fleet-maint-detail-block">
                <span>Service Details</span>
                <p>{{ $maintenance->service_details }}</p>
            </div>
        </div>
    </div>

    <div class="fleet-maint-section fleet-maint-section-green">
        <div class="fleet-maint-section-head">
            <i class="fa fa-money"></i>
            <h2>Financials</h2>
        </div>
        <div class="fleet-maint-section-body">
            <div class="fleet-maint-detail-grid">
                <div class="fleet-maint-detail-item">
                    <span>Total Cost</span>
                    <strong>{{ format_kes($maintenance->total_cost) }}</strong>
                </div>
                <div class="fleet-maint-detail-item">
                    <span>Vendor</span>
                    <strong>{{ optional($maintenance->vendor)->company ?: '-' }}</strong>
                </div>
                <div class="fleet-maint-detail-item fleet-maint-detail-wide">
                    <span>Receipt / Invoice</span>
                    <strong>
                        @if ($maintenance->receipt_path)
                            <a href="{{ asset('storage/' . $maintenance->receipt_path) }}" target="_blank" rel="noopener">View uploaded file</a>
                        @else
                            -
                        @endif
                    </strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="fleet-maint-bottom">
    <div class="fleet-maint-section fleet-maint-section-yellow">
        <div class="fleet-maint-section-head">
            <i class="fa fa-check-square-o"></i>
            <h2>Job Card Checklist</h2>
        </div>
        <div class="fleet-maint-section-body fleet-maint-checklist-body">
            <table class="fleet-maint-checklist-table">
                <thead>
                    <tr>
                        <th class="col-done">Done</th>
                        <th>Task / Inspection Item</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($checklist as $item)
                    <tr>
                        <td class="col-done">
                            @if (! empty($item['done']))
                                <i class="fa fa-check-circle fleet-maint-check-done"></i>
                            @else
                                <i class="fa fa-circle-o fleet-maint-check-pending"></i>
                            @endif
                        </td>
                        <td>{{ $item['task'] ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="fleet-empty-row">No checklist items recorded.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="fleet-maint-section fleet-maint-section-teal">
        <div class="fleet-maint-section-head">
            <i class="fa fa-user"></i>
            <h2>Mechanic And Priority</h2>
        </div>
        <div class="fleet-maint-section-body">
            <div class="fleet-maint-detail-grid">
                <div class="fleet-maint-detail-item">
                    <span>Mechanic</span>
                    <strong>{{ $maintenance->mechanic ?: '-' }}</strong>
                </div>
                <div class="fleet-maint-detail-item">
                    <span>Priority</span>
                    <strong>{{ $maintenance->priority }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
