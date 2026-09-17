@extends('layouts.fleet')

@section('title', 'Purchase Return List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Purchase Return List',
    'subtitle' => 'View/Search Returned Items',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Purchase Return List'],
    ],
])

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Purchase Return List</h3>
        <div class="sx-toolbar-actions">
            @if(!empty($canReturn))
                <button type="button" class="btn sx-btn-gold" id="sx-open-return-purchase">
                    <i class="fa fa-refresh"></i> Return Purchase
                </button>
            @endif
            <a href="{{ route('purchases.index') }}" class="btn btn-default"><i class="fa fa-list"></i> Purchases</a>
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="pr-length"></div>
            <div class="sx-export-btns" id="pr-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="pr-colvis"></ul>
                </div>
            </div>
            <div id="pr-search"></div>
        </div>

        <div class="table-responsive">
            <table id="pr-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="pr-check-all"></th>
                        <th>Purchase code</th>
                        <th>Purchase Date</th>
                        <th>Item Name</th>
                        <th>Purchase Qty</th>
                        <th>Total</th>
                        <th>Purchased Person</th>
                        <th>Returned By</th>
                        <th>Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $return = $row->purchaseReturn;
                            $order = optional($return)->purchaseOrder;
                        @endphp
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="pr-row-check" value="{{ $row->id }}"></td>
                            <td>{{ optional($order)->number ?: optional($return)->number }}</td>
                            <td data-order="{{ optional(optional($order)->order_date ?: optional($return)->return_date)->format('Y-m-d') }}">
                                {{ optional(optional($order)->order_date ?: optional($return)->return_date)->format('d-m-Y') }}
                            </td>
                            <td>{{ optional($row->product)->name ?: '-' }}</td>
                            <td data-order="{{ $row->quantity }}">{{ rtrim(rtrim(number_format((float) $row->quantity, 4, '.', ''), '0'), '.') }}</td>
                            <td data-order="{{ $row->line_total }}">{{ number_format((float) $row->line_total, 2) }}</td>
                            <td>{{ optional(optional($order)->user)->name ?: '-' }}</td>
                            <td>{{ optional(optional($return)->user)->name ?: '-' }}</td>
                            <td>{{ optional(optional($return)->supplier)->name ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if(!empty($canReturn))
<div class="modal fade" id="sx-return-purchase-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-refresh"></i> Return Purchase</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="sx-req">Select Purchase*</label>
                    <select id="sx-return-purchase-select" class="form-control">
                        <option value="">- Select purchase -</option>
                        @foreach($returnablePurchases as $purchase)
                            <option
                                value="{{ $purchase['id'] }}"
                                data-url="{{ $purchase['returnable_url'] }}"
                                data-number="{{ $purchase['number'] }}"
                                data-supplier="{{ $purchase['supplier'] }}">
                                {{ $purchase['number'] }} — {{ $purchase['supplier'] }} ({{ $purchase['order_date'] }})
                            </option>
                        @endforeach
                    </select>
                    @if($returnablePurchases->isEmpty())
                        <p class="help-block">No purchases currently have returnable stock. Receive goods on a purchase first.</p>
                    @endif
                </div>

                <form method="post" action="#" id="sx-return-form" style="display:none;">
                    @csrf
                    <p class="sx-po-modal-invoice">
                        Invoice: <strong id="sx-return-number">-</strong>
                        &nbsp; Supplier: <strong id="sx-return-supplier">-</strong>
                    </p>
                    <div class="form-group">
                        <label class="sx-req">Return Date*</label>
                        <input type="date" name="return_date" class="form-control" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered sx-gold-table" width="100%">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Received</th>
                                    <th>Already returned</th>
                                    <th>Available</th>
                                    <th>Return Qty</th>
                                </tr>
                            </thead>
                            <tbody id="sx-return-rows">
                                <tr><td colspan="5">Select a purchase to load items.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Reason for return"></textarea>
                    </div>
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success" id="sx-return-submit" disabled>Submit Return</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var table = $('#pr-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[2, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching records found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#pr-length');
            wrap.find('.dataTables_filter').appendTo('#pr-search');
        }
    });

    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) {
            return;
        }
        var title = header.clone().children().remove().end().text().trim();
        if (!title) {
            return;
        }
        $('#pr-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });

    $('#pr-colvis').on('click', function (e) {
        e.stopPropagation();
    });
    $('#pr-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });

    $('#pr-check-all').on('change', function () {
        $('.pr-row-check').prop('checked', this.checked);
    });

    function exportRows() {
        var headers = [];
        var skip = {};
        table.columns().every(function () {
            var header = $(this.header());
            if (!this.visible() || header.hasClass('sx-no-export')) {
                skip[this.index()] = true;
                return;
            }
            headers.push(header.clone().children().remove().end().text().trim());
        });
        var rows = [];
        table.rows({ search: 'applied' }).every(function () {
            var row = [];
            $(this.node()).find('td').each(function (i) {
                if (skip[i]) return;
                row.push($(this).text().replace(/\s+/g, ' ').trim());
            });
            rows.push(row);
        });
        return { headers: headers, rows: rows };
    }

    function toCsv(data) {
        function cell(v) {
            v = String(v == null ? '' : v);
            if (/[",\n]/.test(v)) v = '"' + v.replace(/"/g, '""') + '"';
            return v;
        }
        return [data.headers].concat(data.rows).map(function (r) {
            return r.map(cell).join(',');
        }).join('\n');
    }

    function download(filename, content, mime) {
        var blob = new Blob([content], { type: mime });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function printTable(data, title) {
        var html = '<html><head><title>' + title + '</title>';
        html += '<style>body{font-family:sans-serif;font-size:13px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px 8px;text-align:left}th{background:#c9a027;color:#fff}</style></head><body>';
        html += '<h3>' + title + '</h3><table><thead><tr>';
        data.headers.forEach(function (h) { html += '<th>' + h + '</th>'; });
        html += '</tr></thead><tbody>';
        data.rows.forEach(function (r) {
            html += '<tr>' + r.map(function (c) { return '<td>' + c + '</td>'; }).join('') + '</tr>';
        });
        html += '</tbody></table></body></html>';
        var w = window.open('', '_blank');
        w.document.write(html);
        w.document.close();
        w.focus();
        w.print();
    }

    $('#pr-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy') {
            var text = [data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            }
            return;
        }
        if (type === 'csv') {
            download('purchase-return-list.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8');
            return;
        }
        if (type === 'excel') {
            download('purchase-return-list.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel');
            return;
        }
        if (type === 'print' || type === 'pdf') {
            printTable(data, 'Purchase Return List');
        }
    });

    function resetReturnForm() {
        $('#sx-return-purchase-select').val('');
        $('#sx-return-form').hide().attr('action', '#');
        $('#sx-return-number').text('-');
        $('#sx-return-supplier').text('-');
        $('#sx-return-rows').html('<tr><td colspan="5">Select a purchase to load items.</td></tr>');
        $('#sx-return-submit').prop('disabled', true);
        $('#sx-return-form').find('[name="notes"]').val('');
        $('#sx-return-form').find('[name="return_date"]').val('{{ now()->toDateString() }}');
    }

    function loadReturnable(url) {
        $('#sx-return-form').show();
        $('#sx-return-rows').html('<tr><td colspan="5">Loading...</td></tr>');
        $('#sx-return-submit').prop('disabled', true);
        $.ajax({
            url: url,
            headers: { 'Accept': 'application/json' }
        }).done(function (data) {
            $('#sx-return-number').text(data.number || '-');
            $('#sx-return-supplier').text(data.supplier || '-');
            $('#sx-return-form').attr('action', data.store_url || '#');
            if (!data.items || !data.items.length) {
                $('#sx-return-rows').html('<tr><td colspan="5">No returnable items. Receive the purchase first, or all items are already returned.</td></tr>');
                return;
            }
            var html = '';
            data.items.forEach(function (row, i) {
                html += '<tr>';
                html += '<td>' + $('<div>').text(row.name).html() + '<input type="hidden" name="items[' + i + '][product_id]" value="' + row.product_id + '"></td>';
                html += '<td>' + row.received + '</td>';
                html += '<td>' + row.returned + '</td>';
                html += '<td>' + row.available + '</td>';
                html += '<td><input type="number" step="0.0001" min="0" max="' + row.available + '" name="items[' + i + '][quantity]" class="form-control" placeholder="0"></td>';
                html += '</tr>';
            });
            $('#sx-return-rows').html(html);
            $('#sx-return-submit').prop('disabled', false);
        }).fail(function () {
            $('#sx-return-rows').html('<tr><td colspan="5">Could not load returnable items.</td></tr>');
        });
    }

    $('#sx-open-return-purchase').on('click', function () {
        resetReturnForm();
        $('#sx-return-purchase-modal').modal('show');
    });

    $('#sx-return-purchase-select').on('change', function () {
        var option = $(this).find('option:selected');
        var url = option.data('url');
        if (!url) {
            $('#sx-return-form').hide();
            $('#sx-return-submit').prop('disabled', true);
            return;
        }
        loadReturnable(url);
    });
})(jQuery);
</script>
@endpush
