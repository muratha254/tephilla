@extends('layouts.fleet')

@section('title', 'Purchase List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Purchase List',
    'subtitle' => 'View/Search Purchase',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Purchase List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Purchase List</h3>
        <div class="sx-toolbar-actions">
            @if($canCreate)
                <a href="{{ route('purchases.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New Purchase</a>
            @endif
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="po-length"></div>
            <div class="sx-export-btns" id="po-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="po-colvis"></ul>
                </div>
            </div>
            <div id="po-search"></div>
        </div>

        <div class="table-responsive">
            <table id="po-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="po-check-all"></th>
                        <th class="sx-no-export">#</th>
                        <th>Date</th>
                        <th>Invoice No</th>
                        <th>Reference</th>
                        <th>Supplier Name</th>
                        <th>Items</th>
                        <th>Total Amt</th>
                        <th>W/Tax</th>
                        <th>Paid Amt</th>
                        <th>Balance</th>
                        <th>Pay Status</th>
                        <th>Created by</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $purchase)
                        @php
                            $status = $purchase->paymentStatus();
                            $statusClass = [
                                'paid' => 'sx-pay-paid',
                                'partial' => 'sx-pay-partial',
                                'unpaid' => 'sx-pay-unpaid',
                            ][$status];
                        @endphp
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="po-row-check" value="{{ $purchase->id }}"></td>
                            <td class="sx-row-num"></td>
                            <td data-order="{{ optional($purchase->order_date)->format('Y-m-d') }}">{{ optional($purchase->order_date)->format('d-m-Y') }}</td>
                            <td>{{ $purchase->number }}</td>
                            <td>{{ $purchase->reference_no ?: '' }}</td>
                            <td>{{ optional($purchase->supplier)->name }}</td>
                            <td>
                                @php
                                    $itemLabels = $purchase->items->map(function ($item) {
                                        $name = $item->description ?: optional($item->product)->name ?: 'Item';
                                        $qty = (float) $item->quantity;
                                        $qtyLabel = fmod($qty, 1.0) === 0.0
                                            ? (string) (int) $qty
                                            : rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');

                                        return $name . ' × ' . $qtyLabel;
                                    })->filter()->values();
                                @endphp
                                @if($itemLabels->isEmpty())
                                    <span class="text-muted">—</span>
                                @else
                                    <div class="sx-po-items-cell" title="{{ $itemLabels->implode(', ') }}">
                                        {{ $itemLabels->take(3)->implode(', ') }}
                                        @if($itemLabels->count() > 3)
                                            <span class="sx-po-items-more">+{{ $itemLabels->count() - 3 }} more</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td data-order="{{ $purchase->total }}">{{ number_format((float) $purchase->total, 2) }}</td>
                            <td data-order="{{ $purchase->tax_amount }}">{{ number_format((float) $purchase->tax_amount, 2) }}</td>
                            <td data-order="{{ $purchase->paid_amount }}">{{ number_format((float) $purchase->paid_amount, 2) }}</td>
                            <td data-order="{{ $purchase->balance() }}">{{ number_format($purchase->balance(), 2) }}</td>
                            <td><span class="sx-pay-badge {{ $statusClass }}">{{ $purchase->paymentStatusLabel() }}</span></td>
                            <td>{{ optional($purchase->user)->name ?: '-' }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li>
                                            <a href="{{ route('purchases.show', $purchase) }}"><i class="fa fa-eye"></i> View purchase</a>
                                        </li>
                                        @if(!empty($canUpdate) && $purchase->isEditable())
                                            <li>
                                                <a href="{{ route('purchases.edit', $purchase) }}">
                                                    <i class="fa fa-pencil"></i> Edit Purchase Order
                                                </a>
                                            </li>
                                        @endif
                                        <li>
                                            <a href="#" class="sx-view-payments" data-url="{{ route('purchases.payments', $purchase) }}">
                                                <i class="fa fa-list-alt"></i> View Payments
                                            </a>
                                        </li>
                                        <li>
                                            <a href="#" class="sx-open-status"
                                                data-url="{{ route('purchases.status.update', $purchase) }}"
                                                data-number="{{ $purchase->number }}"
                                                data-status="{{ $purchase->status }}"
                                                data-received="{{ $purchase->status === 'received' ? '1' : '0' }}">
                                                <i class="fa fa-refresh"></i> Change Status
                                            </a>
                                        </li>
                                        <li>
                                            <a href="#" class="sx-open-return" data-url="{{ route('purchases.returns.returnable', $purchase) }}">
                                                <i class="fa fa-refresh"></i> Purchase Return
                                            </a>
                                        </li>
                                        <li>
                                            <a href="#" class="sx-open-payment"
                                                data-url="{{ route('purchases.payments.store', $purchase) }}"
                                                data-number="{{ $purchase->number }}"
                                                data-total="{{ number_format((float) $purchase->total, 2) }}"
                                                data-paid="{{ number_format((float) $purchase->paid_amount, 2) }}"
                                                data-balance="{{ number_format($purchase->balance(), 2) }}"
                                                data-balance-raw="{{ number_format($purchase->balance(), 2, '.', '') }}">
                                                <i class="fa fa-money"></i> Add Payment
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('purchases.print', ['purchase' => $purchase, 'mode' => 'po']) }}" target="_blank">
                                                <i class="fa fa-file-text-o"></i> Purchase Order
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('purchases.print', ['purchase' => $purchase, 'mode' => 'noprice']) }}" target="_blank">
                                                <i class="fa fa-file-o"></i> Purchase Order (No Price)
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('purchases.print', ['purchase' => $purchase, 'mode' => 'thermal']) }}" target="_blank">
                                                <i class="fa fa-print"></i> Thermal Printable
                                            </a>
                                        </li>
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

<div class="modal fade" id="sx-po-payments-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-money"></i> View Payments</h4>
            </div>
            <div class="modal-body">
                <div class="sx-po-pay-meta">
                    <div><span>Invoice</span> <strong id="sx-po-pay-number">-</strong></div>
                    <div><span>Supplier</span> <strong id="sx-po-pay-supplier">-</strong></div>
                    <div><span>Total</span> <strong id="sx-po-pay-total">0.00</strong></div>
                    <div><span>Paid</span> <strong id="sx-po-pay-paid">0.00</strong></div>
                    <div><span>Balance</span> <strong id="sx-po-pay-balance">0.00</strong></div>
                    <div><span>Status</span> <strong id="sx-po-pay-status">-</strong></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered sx-gold-table" width="100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Payment No</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Received by</th>
                            </tr>
                        </thead>
                        <tbody id="sx-po-pay-rows">
                            <tr><td colspan="7">No payments found.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="sx-form-actions">
                    <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-change-status-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Change Order Status</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="#" id="sx-list-status-form">
                    @csrf
                    @method('PUT')
                    <p class="sx-po-modal-invoice">Invoice: <strong id="sx-list-status-number">-</strong></p>
                    <div class="form-group">
                        <label class="sx-req">Status*</label>
                        <select name="status" id="sx-list-status" class="form-control" required>
                            @foreach($purchaseStatuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="help-block" id="sx-list-status-help"></p>
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success" id="sx-list-status-submit">Submit</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-add-payment-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Complete Supplier Payment</h4>
            </div>
            <div class="modal-body">
                <div class="sx-po-pay-meta">
                    <div><span>Invoice</span> <strong id="sx-list-pay-number">-</strong></div>
                    <div><span>Total</span> <strong id="sx-list-pay-total">0.00</strong></div>
                    <div><span>Paid</span> <strong id="sx-list-pay-paid">0.00</strong></div>
                    <div><span>Balance</span> <strong id="sx-list-pay-balance">0.00</strong></div>
                </div>
                <form method="post" action="#" id="sx-list-payment-form">
                    @csrf
                    <div class="form-group">
                        <label class="sx-req">Amount*</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="sx-list-pay-amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Payment Type*</label>
                        <select name="method" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($paymentMethods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Voucher No</label>
                        <input type="text" name="reference" class="form-control" placeholder="Voucher No">
                    </div>
                    <div class="form-group">
                        <label>Payment Date*</label>
                        <input type="date" name="paid_at" class="form-control" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="form-group">
                        <label>Payment Note</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success">Submit</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-return-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Purchase Return</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="#" id="sx-return-form">
                    @csrf
                    <p class="sx-po-modal-invoice">Invoice: <strong id="sx-return-number">-</strong> &nbsp; Supplier: <strong id="sx-return-supplier">-</strong></p>
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
                                <tr><td colspan="5">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success">Submit</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var table = $('#po-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[2, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, 1, 13], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching purchases found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#po-length');
            wrap.find('.dataTables_filter').appendTo('#po-search');
        },
        drawCallback: function () {
            var api = this.api();
            var start = api.page.info().start;
            api.column(1, { page: 'current' }).nodes().each(function (cell, i) {
                cell.innerHTML = start + i + 1;
            });
        }
    });

    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) {
            return;
        }
        var title = header.clone().children().remove().end().text().trim();
        if (!title || title === '#') {
            return;
        }
        $('#po-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });

    $('#po-colvis').on('click', function (e) {
        e.stopPropagation();
    });
    $('#po-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });

    $('#po-check-all').on('change', function () {
        $('.po-row-check').prop('checked', this.checked);
    });

    $('.sx-items-body .table-responsive').on('show.bs.dropdown', function () {
        $(this).css('overflow', 'visible');
    }).on('hide.bs.dropdown', function () {
        $(this).css('overflow', 'auto');
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

    $('#po-export').on('click', '[data-export]', function () {
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
            download('purchase-list.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8');
            return;
        }
        if (type === 'excel') {
            download('purchase-list.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel');
            return;
        }
        if (type === 'print' || type === 'pdf') {
            printTable(data, 'Purchase List');
        }
    });

    $(document).on('click', '.sx-view-payments', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        $('#sx-po-pay-rows').html('<tr><td colspan="7">Loading...</td></tr>');
        $('#sx-po-payments-modal').modal('show');
        $.ajax({
            url: url,
            headers: { 'Accept': 'application/json' }
        }).done(function (data) {
            $('#sx-po-pay-number').text(data.number || '-');
            $('#sx-po-pay-supplier').text(data.supplier || '-');
            $('#sx-po-pay-total').text(data.total || '0.00');
            $('#sx-po-pay-paid').text(data.paid || '0.00');
            $('#sx-po-pay-balance').text(data.balance || '0.00');
            $('#sx-po-pay-status').text(data.status || '-');
            if (!data.payments || !data.payments.length) {
                $('#sx-po-pay-rows').html('<tr><td colspan="7">No payments found.</td></tr>');
                return;
            }
            var html = '';
            data.payments.forEach(function (row, i) {
                html += '<tr><td>' + (i + 1) + '</td><td>' + row.number + '</td><td>' + row.date + '</td><td>' + row.method + '</td><td>' + row.reference + '</td><td>' + row.amount + '</td><td>' + row.user + '</td></tr>';
            });
            $('#sx-po-pay-rows').html(html);
        }).fail(function () {
            $('#sx-po-pay-rows').html('<tr><td colspan="7">Could not load payments.</td></tr>');
        });
    });

    $(document).on('click', '.sx-open-status', function (e) {
        e.preventDefault();
        var received = $(this).data('received') === 1 || $(this).data('received') === '1';
        $('#sx-list-status-form').attr('action', $(this).data('url'));
        $('#sx-list-status-number').text($(this).data('number') || '-');
        $('#sx-list-status').val($(this).data('status'));
        $('#sx-list-status-submit').prop('disabled', received);
        $('#sx-list-status-help').text(received
            ? 'This purchase is already received. Status cannot be reversed.'
            : 'Setting status to Received will add stock and create a Goods Received Note.');
        $('#sx-change-status-modal').modal('show');
    });

    $(document).on('click', '.sx-open-payment', function (e) {
        e.preventDefault();
        var balance = parseFloat($(this).data('balance-raw') || '0');
        if (!(balance > 0)) {
            if (window.Swal) {
                Swal.fire({ icon: 'info', title: 'Already paid', text: 'This purchase has no remaining balance.' });
            } else {
                alert('This purchase has no remaining balance.');
            }
            return;
        }
        $('#sx-list-payment-form')[0].reset();
        $('#sx-list-payment-form').attr('action', $(this).data('url'));
        $('#sx-list-pay-number').text($(this).data('number') || '-');
        $('#sx-list-pay-total').text('Ksh ' + ($(this).data('total') || '0.00'));
        $('#sx-list-pay-paid').text('Ksh ' + ($(this).data('paid') || '0.00'));
        $('#sx-list-pay-balance').text('Ksh ' + ($(this).data('balance') || '0.00'));
        $('#sx-list-pay-amount').attr('max', balance.toFixed(2)).val(balance.toFixed(2));
        $('#sx-list-payment-form').find('[name="paid_at"]').val('{{ now()->toDateString() }}');
        $('#sx-add-payment-modal').modal('show');
    });

    $(document).on('click', '.sx-open-return', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        $('#sx-return-rows').html('<tr><td colspan="5">Loading...</td></tr>');
        $('#sx-return-form').attr('action', '#');
        $('#sx-return-modal').modal('show');
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
        }).fail(function () {
            $('#sx-return-rows').html('<tr><td colspan="5">Could not load returnable items.</td></tr>');
        });
    });
})(jQuery);
</script>
@endpush
