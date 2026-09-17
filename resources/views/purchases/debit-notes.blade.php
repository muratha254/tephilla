@extends('layouts.fleet')

@section('title', 'Debit Notes')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Debit Notes',
    'subtitle' => 'Create and view supplier debit notes',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Debit Notes'],
    ],
])

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="sx-box sx-report-filter">
    <form method="get" action="{{ route('debit-notes.index') }}" class="form-inline" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <div class="form-group">
            <label>From</label>
            <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
        </div>
        <div class="form-group">
            <label>Search</label>
            <input type="text" name="q" class="form-control" value="{{ $search }}" placeholder="Debit note / purchase / supplier">
        </div>
        <button type="submit" class="btn sx-btn-filter"><i class="fa fa-filter"></i> Filter</button>
        <button type="submit" class="btn sx-btn-pdf" formaction="{{ route('debit-notes.pdf') }}">
            <i class="fa fa-file-pdf-o"></i> Generate Pdf
        </button>
        @if(!empty($canCreate))
            <a href="{{ route('debit-notes.create') }}" class="btn sx-btn-aqua">
                <i class="fa fa-plus"></i> New Debit Note
            </a>
        @endif
    </form>
</div>

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Debit Notes</h3>
    </div>
    <div class="sx-box-body sx-items-body">
        <div class="table-responsive">
            <table id="sx-debit-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Debit Note No</th>
                        <th>Purchase</th>
                        <th>Supplier</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Created by</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notes as $note)
                        @php
                            $itemLabels = $note->items->map(function ($item) {
                                $name = optional($item->product)->name ?: 'Item';
                                $qty = (float) $item->quantity;
                                $qtyLabel = fmod($qty, 1.0) === 0.0
                                    ? (string) (int) $qty
                                    : rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');

                                return $name . ' × ' . $qtyLabel;
                            })->filter()->values();
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td data-order="{{ optional($note->return_date)->format('Y-m-d') }}">
                                {{ optional($note->return_date)->format('d-m-Y') }}
                            </td>
                            <td>{{ $note->number }}</td>
                            <td>{{ optional($note->purchaseOrder)->number ?: '—' }}</td>
                            <td>{{ optional($note->supplier)->name ?: '—' }}</td>
                            <td>
                                @if($itemLabels->isEmpty())
                                    —
                                @else
                                    <div class="sx-po-items-cell" title="{{ $itemLabels->implode(', ') }}">
                                        {{ $itemLabels->take(3)->implode(', ') }}
                                        @if($itemLabels->count() > 3)
                                            <span class="sx-po-items-more">+{{ $itemLabels->count() - 3 }} more</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td data-order="{{ $note->total }}">{{ number_format((float) $note->total, 2) }}</td>
                            <td>{{ optional($note->user)->name ?: '—' }}</td>
                            <td>{{ $note->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No debit notes found. Create one from a received purchase.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
jQuery(function ($) {
    if ($('#sx-debit-table tbody tr').length && !$('#sx-debit-table tbody td[colspan]').length) {
        $('#sx-debit-table').DataTable({
            pageLength: 25,
            order: [[1, 'desc']],
            columnDefs: [{ targets: [0], orderable: false }]
        });
    }
});
</script>
@endpush
