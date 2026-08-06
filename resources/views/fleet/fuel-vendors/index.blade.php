@extends('layouts.fleet')

@section('title', 'Fuel Vendor')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-fuel-vendors.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-vendor-head">
        <h1 class="fleet-page-title">Fuel Vendor</h1>
        <button type="button" class="fleet-vendor-add-icon" id="fuel-vendor-add-btn" title="Add Fuel Vendor"><i class="fa fa-plus"></i></button>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Fuel Vendor</li>
    </ul>
</div>

<div class="fleet-panel fleet-vendor-card">
    <div class="fleet-vendor-table-tools">
        <div class="fleet-export-btns">
            <button type="button" class="fleet-export-btn fleet-export-copy" id="fuel-vendor-copy-btn">Copy</button>
            <button type="button" class="fleet-export-btn fleet-export-excel" id="fuel-vendor-excel-btn">Excel</button>
            <button type="button" class="fleet-export-btn fleet-export-csv" id="fuel-vendor-csv-btn">CSV</button>
            <a href="{{ route('fuel-vendors.export-pdf', request()->only('search')) }}" class="fleet-export-btn fleet-export-pdf" target="_blank">PDF</a>
        </div>

        <form method="GET" action="{{ route('fuel-vendors.index') }}" class="fleet-vendor-search">
            <label for="fuel-vendor-search">Search:</label>
            <input type="text" id="fuel-vendor-search" name="search" value="{{ $search }}" placeholder="">
        </form>
    </div>

    <div class="fleet-table-wrap">
        <table class="fleet-vendor-table fleet-fuel-vendor-table" id="fuel-vendor-table">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vendors as $index => $vendor)
                <tr>
                    <td>{{ $vendors->firstItem() + $index }}</td>
                    <td>{{ $vendor->name }}</td>
                    <td>
                        <form action="{{ route('fuel-vendors.destroy', $vendor) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this fuel vendor?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="fleet-vendor-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="fleet-empty-row">No fuel vendors found. Click + to add one.</td>
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

<div class="fleet-modal" id="fuel-vendor-modal" hidden>
    <div class="fleet-modal-backdrop" data-close-fuel-vendor-modal></div>
    <div class="fleet-modal-dialog fleet-fuel-vendor-modal">
        <div class="fleet-modal-header">
            <h3>Add Fuel Vendor</h3>
            <button type="button" class="fleet-modal-close" data-close-fuel-vendor-modal aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="{{ route('fuel-vendors.store') }}">
            @csrf
            <div class="fleet-modal-body">
                @if ($errors->any())
                <div class="fleet-alert fleet-alert-error">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                <div class="fleet-fuel-vendor-field">
                    <label for="fuel-vendor-name">Vendor Name</label>
                    <input type="text" id="fuel-vendor-name" name="name" class="fleet-maint-input" value="{{ old('name') }}" placeholder="Enter vendor name" required>
                </div>
            </div>
            <div class="fleet-modal-footer">
                <button type="button" class="fleet-btn fleet-btn-default" data-close-fuel-vendor-modal>Close</button>
                <button type="submit" class="fleet-btn fleet-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('fuel-vendor-table');
    var modal = document.getElementById('fuel-vendor-modal');
    var addBtn = document.getElementById('fuel-vendor-add-btn');

    if (addBtn && modal) {
        addBtn.addEventListener('click', function () {
            modal.hidden = false;
            document.body.classList.add('fleet-modal-open');
        });

        modal.querySelectorAll('[data-close-fuel-vendor-modal]').forEach(function (el) {
            el.addEventListener('click', function () {
                modal.hidden = true;
                document.body.classList.remove('fleet-modal-open');
            });
        });
    }

    @if ($errors->any())
    if (modal) {
        modal.hidden = false;
        document.body.classList.add('fleet-modal-open');
    }
    @endif

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

    document.getElementById('fuel-vendor-copy-btn').addEventListener('click', function () {
        navigator.clipboard.writeText(tableText());
    });

    document.getElementById('fuel-vendor-csv-btn').addEventListener('click', function () {
        var blob = new Blob([tableText().replace(/\t/g, ',')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'fuel-vendors.csv';
        link.click();
    });

    document.getElementById('fuel-vendor-excel-btn').addEventListener('click', function () {
        var blob = new Blob([tableText()], { type: 'application/vnd.ms-excel' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'fuel-vendors.xls';
        link.click();
    });
})();
</script>
@endpush
