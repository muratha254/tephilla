@extends('layouts.fleet')

@section('title', 'Sales Return')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sales Return',
    'subtitle' => 'Returned sales',
    'backUrl' => route('sales.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Sales Return'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;"><i class="fa fa-refresh"></i> Sales Return</h3>
        <div class="sx-toolbar-actions">
            <a href="{{ route('sales.index') }}" class="btn sx-btn-gold"><i class="fa fa-list"></i> Sales List</a>
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="sr-length"></div>
            <div id="sr-search"></div>
        </div>

        <div class="table-responsive">
            <table id="sr-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>Date</th>
                        <th>Return No</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Refund</th>
                        <th>Stock</th>
                        <th>Narrative</th>
                        <th>Created By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($returns as $return)
                        <tr>
                            <td class="sx-row-num"></td>
                            <td data-order="{{ optional($return->return_date)->format('Y-m-d H:i:s') }}">
                                {{ optional($return->return_date)->format('d-m-Y H:i') }}
                            </td>
                            <td>{{ $return->number }}</td>
                            <td>{{ optional($return->sale)->documentNumber() ?: '-' }}</td>
                            <td>{{ optional($return->sale)->customerDisplayName() ?: (optional($return->customer)->name ?: 'WALK-IN') }}</td>
                            <td>
                                @if((float) $return->refund_amount > 0)
                                    Ksh {{ number_format((float) $return->refund_amount, 2) }}
                                @else
                                    No
                                @endif
                            </td>
                            <td>{{ $return->restore_stock ? 'Restored' : 'Not restored' }}</td>
                            <td>{{ $return->notes }}</td>
                            <td>{{ optional($return->user)->name ?: '-' }}</td>
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
    $('#sr-table').DataTable({
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
            zeroRecords: 'No matching returns found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#sr-length');
            wrap.find('.dataTables_filter').appendTo('#sr-search');
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
