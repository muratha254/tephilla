@extends('layouts.fleet')

@section('title', 'Vehicle Vendors Info')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-vendor-head">
        <h1 class="fleet-page-title">Vehicle Vendors Info</h1>
        <a href="{{ route('vehicle-vendors.create') }}" class="fleet-vendor-add-icon" title="Add Vehicle Vendor"><i class="fa fa-plus"></i></a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Vehicle Vendors Info</li>
    </ul>
</div>

<div class="fleet-panel fleet-vendor-card">
    <div class="fleet-vendor-table-tools">
        <div class="fleet-export-btns">
            <button type="button" class="fleet-export-btn fleet-export-copy" id="vendor-copy-btn">Copy</button>
            <button type="button" class="fleet-export-btn fleet-export-excel" id="vendor-excel-btn">Excel</button>
            <button type="button" class="fleet-export-btn fleet-export-csv" id="vendor-csv-btn">CSV</button>
            <a href="{{ route('vehicle-vendors.export-pdf', request()->only('search')) }}" class="fleet-export-btn fleet-export-pdf" id="vendor-pdf-btn">PDF</a>
        </div>

        <form method="GET" action="{{ route('vehicle-vendors.index') }}" class="fleet-vendor-search">
            <label for="vendor-search">Search:</label>
            <input type="text" id="vendor-search" name="search" value="{{ $search }}" placeholder="">
        </form>
    </div>

    <div class="fleet-table-wrap">
        <table class="fleet-vendor-table" id="vendor-table">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Company</th>
                    <th>Contact Person</th>
                    <th>Mobile</th>
                    <th>Date Of Contract</th>
                    <th>Contract Doc</th>
                    <th>Address</th>
                    <th>Is Active</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vendors as $index => $vendor)
                <tr>
                    <td>{{ $vendors->firstItem() + $index }}</td>
                    <td>{{ $vendor->company }}</td>
                    <td>{{ $vendor->contact_person }}</td>
                    <td>{{ $vendor->mobile }}</td>
                    <td>{{ format_fleet_date($vendor->contract_date) }}</td>
                    <td>
                        @if ($vendor->contract_doc)
                            <a href="{{ asset('storage/' . $vendor->contract_doc) }}" target="_blank">View</a>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $vendor->address ?: '-' }}</td>
                    <td>
                        <span class="fleet-status-tag {{ $vendor->is_active ? 'fleet-status-active' : 'fleet-status-inactive' }}">
                            {{ $vendor->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <div class="fleet-vendor-action-btns">
                            <a href="{{ route('vehicle-vendors.edit', $vendor) }}" class="fleet-vendor-action-btn" title="Edit"><i class="fa fa-edit"></i></a>
                            <form action="{{ route('vehicle-vendors.destroy', $vendor) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this vendor?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-vendor-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="fleet-empty-row">No vehicle vendors found. <a href="{{ route('vehicle-vendors.create') }}">Add a vendor</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($vendors->hasPages())
    <div class="fleet-vendor-pagination">
        @if ($vendors->onFirstPage())
            <span class="fleet-page-link disabled">Previous</span>
        @else
            <a href="{{ $vendors->previousPageUrl() }}" class="fleet-page-link">Previous</a>
        @endif

        @foreach ($vendors->getUrlRange(1, $vendors->lastPage()) as $page => $url)
            <a href="{{ $url }}" class="fleet-page-link {{ $vendors->currentPage() === $page ? 'active' : '' }}">{{ $page }}</a>
        @endforeach

        @if ($vendors->hasMorePages())
            <a href="{{ $vendors->nextPageUrl() }}" class="fleet-page-link">Next</a>
        @else
            <span class="fleet-page-link disabled">Next</span>
        @endif
    </div>
    @elseif ($vendors->total() > 0)
    <div class="fleet-vendor-pagination">
        <span class="fleet-page-link disabled">Previous</span>
        <span class="fleet-page-link active">1</span>
        <span class="fleet-page-link disabled">Next</span>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('vendor-table');
    if (!table) return;

    function tableText() {
        var rows = table.querySelectorAll('tr');
        var lines = [];
        rows.forEach(function (row) {
            var cells = row.querySelectorAll('th, td');
            var values = [];
            cells.forEach(function (cell, index) {
                if (index === cells.length - 1) return;
                values.push(cell.innerText.trim().replace(/\s+/g, ' '));
            });
            if (values.length) lines.push(values.join('\t'));
        });
        return lines.join('\n');
    }

    document.getElementById('vendor-copy-btn').addEventListener('click', function () {
        navigator.clipboard.writeText(tableText());
    });

    document.getElementById('vendor-csv-btn').addEventListener('click', function () {
        var blob = new Blob([tableText().replace(/\t/g, ',')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'vehicle-vendors.csv';
        link.click();
    });

    document.getElementById('vendor-excel-btn').addEventListener('click', function () {
        var blob = new Blob([tableText()], { type: 'application/vnd.ms-excel' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'vehicle-vendors.xls';
        link.click();
    });
})();
</script>
@endpush
