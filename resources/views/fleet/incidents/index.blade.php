@extends('layouts.fleet')

@section('title', 'Incident Reports')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-incidents.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Incident Reports</h1>
        <a href="{{ route('incidents.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Report Issue</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Incidents</li>
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

<div class="fleet-panel fleet-vehicle-card">
    <form method="GET" action="{{ route('incidents.index') }}" class="fleet-incident-search">
        <label for="incident-search">Search:</label>
        <input type="text" id="incident-search" name="search" class="fleet-incident-search-input" value="{{ $search }}" placeholder="">
    </form>

    <div class="fleet-table-wrap">
        <table class="fleet-incident-table">
            <thead>
                <tr>
                    <th>S.NO</th>
                    <th>Vehicle</th>
                    <th>Reported By</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($incidents as $index => $incident)
                <tr>
                    <td>{{ $incidents->firstItem() + $index }}</td>
                    <td>
                        <div class="fleet-incident-vehicle-name">{{ optional($incident->vehicle)->displayName() ?: '-' }}</div>
                        <div class="fleet-incident-vehicle-meta">{{ optional($incident->vehicle)->registration_number }}</div>
                    </td>
                    <td>{{ $incident->reported_by ?: '-' }}</td>
                    <td>
                        <ul class="fleet-incident-desc-list">
                            @foreach ($incident->descriptionLines() as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </td>
                    <td>{{ $incident->formattedDate() }}</td>
                    <td>
                        <span class="fleet-incident-status {{ $incident->statusBadgeClass() }}">{{ $incident->status }}</span>
                    </td>
                    <td>
                        <div class="fleet-incident-actions">
                            @if ($incident->canConvert())
                                <form action="{{ route('incidents.convert', $incident) }}" method="POST" class="fleet-delete-form">
                                    @csrf
                                    <button type="submit" class="fleet-incident-convert-btn">
                                        <i class="fa fa-wrench"></i> Convert to Maint.
                                    </button>
                                </form>
                            @elseif ($incident->maintenance)
                                <a href="{{ route('maintenance.show', $incident->maintenance) }}" class="fleet-incident-view-maint">View Maintenance</a>
                            @endif
                            <form action="{{ route('incidents.destroy', $incident) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this incident report?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="fleet-empty-row">No incident reports yet. <a href="{{ route('incidents.create') }}">Report Issue</a></td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($incidents->hasPages())
    <div class="fleet-incident-pagination">
        {{ $incidents->links('fleet.incidents.partials.pagination') }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var searchInput = document.getElementById('incident-search');
    var searchForm = searchInput ? searchInput.closest('form') : null;
    var timer;

    if (searchInput && searchForm) {
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                searchForm.submit();
            }, 400);
        });
    }
})();
</script>
@endpush
