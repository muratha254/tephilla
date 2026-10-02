@extends('layouts.fleet')

@section('title', 'Quotations')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Quotations',
    'subtitle' => 'View/Search Quotations',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Quotations'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Quotation List</h3>
        <div class="sx-toolbar-actions">
            @if($canCreate)
                <a href="{{ route('quotations.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New Quotation</a>
            @endif
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="qt-length"></div>
            <div class="sx-export-btns" id="qt-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
            </div>
            <div id="qt-search"></div>
        </div>

        <div class="table-responsive">
            <table id="qt-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Valid Until</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Created by</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quotations as $row)
                        @php
                            $statusClass = [
                                'draft' => 'sx-pay-unpaid',
                                'sent' => 'sx-pay-partial',
                                'accepted' => 'sx-pay-paid',
                                'converted' => 'sx-pay-paid',
                                'expired' => 'sx-pay-unpaid',
                                'cancelled' => 'sx-pay-unpaid',
                            ][$row->status] ?? 'sx-pay-unpaid';
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td data-order="{{ optional($row->quote_date)->format('Y-m-d') }}">{{ optional($row->quote_date)->format('d-m-Y') }}</td>
                            <td>{{ $row->number }}</td>
                            <td>{{ optional($row->customer)->name ?: '-' }}</td>
                            <td data-order="{{ optional($row->valid_until)->format('Y-m-d') }}">{{ optional($row->valid_until)->format('d-m-Y') ?: '-' }}</td>
                            <td data-order="{{ $row->total }}">{{ number_format((float) $row->total, 2) }}</td>
                            <td><span class="sx-pay-badge {{ $statusClass }}">{{ $statuses[$row->status] ?? ucfirst($row->status) }}</span></td>
                            <td>{{ optional($row->user)->name ?: '-' }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li>
                                            <a href="{{ route('quotations.show', $row) }}"><i class="fa fa-eye"></i> View</a>
                                        </li>
                                        @if($canUpdate && in_array($row->status, ['draft', 'sent'], true))
                                            <li>
                                                <a href="{{ route('quotations.edit', $row) }}"><i class="fa fa-pencil"></i> Edit</a>
                                            </li>
                                        @endif
                                        <li>
                                            <a href="{{ route('quotations.print', $row) }}" target="_blank"><i class="fa fa-print"></i> Print</a>
                                        </li>
                                        <li>
                                            <a href="{{ route('quotations.pdf', $row) }}"><i class="fa fa-file-pdf-o"></i> Download PDF</a>
                                        </li>
                                        @if($canConvert && in_array($row->status, ['draft', 'sent', 'accepted'], true) && ! $row->converted_sale_id)
                                            <li>
                                                <a href="#" onclick="event.preventDefault(); if(confirm('Convert this quotation to an unpaid invoice?')) document.getElementById('qt-convert-{{ $row->id }}').submit();">
                                                    <i class="fa fa-exchange"></i> Convert to Invoice
                                                </a>
                                                <form id="qt-convert-{{ $row->id }}" action="{{ route('quotations.convert', $row) }}" method="post" class="hidden">
                                                    @csrf
                                                </form>
                                            </li>
                                        @endif
                                        @if($canDelete && $row->status === 'draft')
                                            <li>
                                                <a href="#" class="text-danger" onclick="event.preventDefault(); if(confirm('Delete this draft quotation?')) document.getElementById('qt-delete-{{ $row->id }}').submit();">
                                                    <i class="fa fa-trash"></i> Delete
                                                </a>
                                                <form id="qt-delete-{{ $row->id }}" action="{{ route('quotations.destroy', $row) }}" method="post" class="hidden">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
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
    var table = $('#qt-table').DataTable({
        pageLength: 25,
        order: [[1, 'desc']],
        dom: 'lBfrtip',
        columnDefs: [{ targets: -1, orderable: false }]
    });
    $('.sx-items-body .table-responsive').on('show.bs.dropdown', function () {
        $(this).css('overflow', 'visible');
    }).on('hide.bs.dropdown', function () {
        $(this).css('overflow', 'auto');
    });

    $('#qt-length').append($('#qt-table_length'));
    $('#qt-search').append($('#qt-table_filter'));
    $('#qt-export [data-export]').on('click', function () {
        var type = $(this).data('export');
        if (type === 'print') {
            window.print();
            return;
        }
        alert('Use browser export / copy from the table for ' + type.toUpperCase() + '.');
    });
})(jQuery);
</script>
@endpush
