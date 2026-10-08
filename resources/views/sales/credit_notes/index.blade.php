@extends('layouts.fleet')

@section('title', 'Credit Notes')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Credit Notes',
    'subtitle' => 'View/Search Credit Notes',
    'backUrl' => route('sales.index'),
    'headerAction' => !empty($canCreate) ? [
        'url' => route('sales.credit-notes.create'),
        'label' => 'New Credit Note',
        'icon' => 'fa-plus',
    ] : null,
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Credit Notes'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;"><i class="fa fa-file-text-o"></i> Credit Notes</h3>
        <form method="get" action="{{ route('sales.credit-notes') }}" class="form-inline" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <input type="text" name="q" class="form-control" placeholder="Search number / sale / customer" value="{{ $search ?? '' }}">
            <select name="status" class="form-control">
                <option value="">All statuses</option>
                @foreach(['draft' => 'Draft', 'posted' => 'Posted', 'completed' => 'Completed', 'voided' => 'Voided'] as $value => $label)
                    <option value="{{ $value }}" @if(($status ?? '') === $value) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="from_date" class="form-control" value="{{ $fromDate ?? '' }}" title="From date">
            <input type="date" name="to_date" class="form-control" value="{{ $toDate ?? '' }}" title="To date">
            <button type="submit" class="btn sx-btn-gold"><i class="fa fa-search"></i> Filter</button>
            <a href="{{ route('sales.credit-notes') }}" class="btn btn-default">Reset</a>
        </form>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="cn-length"></div>
            <div id="cn-search"></div>
        </div>

        <div class="table-responsive">
            <table id="cn-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Date</th>
                        <th>Credit Note</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($notes as $note)
                        <tr>
                            <td class="sx-row-num"></td>
                            <td data-order="{{ optional($note->credit_date)->format('Y-m-d') }}">
                                {{ optional($note->credit_date)->format('d-m-Y') }}
                            </td>
                            <td>{{ $note->number }}</td>
                            <td>{{ optional($note->sale)->documentNumber() ?: '-' }}</td>
                            <td>
                                {{ optional($note->customer)->name
                                    ?: (optional($note->sale)->customerDisplayName() ?: 'WALK-IN') }}
                            </td>
                            <td data-order="{{ $note->total }}">Ksh {{ number_format((float) $note->total, 2) }}</td>
                            <td>
                                @if($note->stock_restored)
                                    Restored
                                @elseif($note->restore_stock)
                                    Pending
                                @else
                                    Not restored
                                @endif
                            </td>
                            <td>{{ ucfirst($note->status) }}</td>
                            <td>{{ optional($note->user)->name ?: '-' }}</td>
                            <td>
                                <a href="{{ route('sales.credit-notes.show', $note) }}" class="btn btn-xs btn-info" title="View">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <a href="{{ route('sales.credit-notes.print', $note) }}" class="btn btn-xs btn-default" target="_blank" title="Print">
                                    <i class="fa fa-print"></i>
                                </a>
                                <a href="{{ route('sales.credit-notes.pdf', $note) }}" class="btn btn-xs btn-primary" title="Download PDF">
                                    <i class="fa fa-file-pdf-o"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
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
(function ($) {
    $('#cn-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, 9], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching credit notes found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#cn-length');
            wrap.find('.dataTables_filter').appendTo('#cn-search');
        },
        drawCallback: function () {
            var api = this.api();
            var start = api.page.info().start;
            api.column(0, { page: 'current' }).nodes().each(function (cell, i) {
                cell.innerHTML = (start + i + 1) + '.';
            });
        }
    });
})(jQuery);
</script>
@endpush
