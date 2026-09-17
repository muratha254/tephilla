@extends('layouts.fleet')

@section('title', 'Cancelled Sales (Voids)')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Cancelled Sales (Voids)',
    'subtitle' => 'Voided invoices',
    'backUrl' => route('sales.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Cancelled Sales (Voids)'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;"><i class="fa fa-times-circle"></i> Cancelled Sales</h3>
        <div class="sx-toolbar-actions">
            <a href="{{ route('sales.index') }}" class="btn sx-btn-gold"><i class="fa fa-list"></i> Sales List</a>
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="sv-length"></div>
            <div id="sv-search"></div>
        </div>

        <div class="table-responsive">
            <table id="sv-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Cancelled</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Refund</th>
                        <th>Flagged</th>
                        <th>Narrative</th>
                        <th>Cancelled By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales as $sale)
                        <tr>
                            <td class="sx-row-num"></td>
                            <td data-order="{{ optional($sale->voided_at)->format('Y-m-d H:i:s') }}">
                                {{ optional($sale->voided_at)->format('d-m-Y H:i') }}
                            </td>
                            <td>{{ $sale->documentNumber() }}</td>
                            <td>{{ $sale->customerDisplayName() }}</td>
                            <td data-order="{{ $sale->total }}">Ksh {{ number_format((float) $sale->total, 2) }}</td>
                            <td>{{ $sale->void_refund ? 'Yes' : 'No' }}</td>
                            <td>{{ $sale->void_flagged ? 'Yes' : 'No' }}</td>
                            <td>{{ $sale->void_reason }}</td>
                            <td>{{ optional($sale->voidedBy)->name ?: '-' }}</td>
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
    $('#sv-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No cancelled sales found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#sv-length');
            wrap.find('.dataTables_filter').appendTo('#sv-search');
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
