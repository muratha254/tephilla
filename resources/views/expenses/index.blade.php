@extends('layouts.fleet')

@section('title', 'Expenses List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Expenses List',
    'subtitle' => 'View/Search Expenses',
    'backUrl' => route('dashboard'),
    'headerAction' => $canCreate ? [
        'url' => route('expenses.create'),
        'label' => 'New Direct Expense',
        'icon' => 'fa-plus',
    ] : null,
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Expenses List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <form method="get" action="{{ route('expenses.index') }}" class="sx-expense-form-of">
            <label for="sx-expense-form">Expense Form of</label>
            <select name="form" id="sx-expense-form" class="form-control" onchange="this.form.submit()">
                <option value="all" @if($form === 'all') selected @endif>All Expenses</option>
                <option value="direct" @if($form === 'direct') selected @endif>Direct Expense</option>
                <option value="bill" @if($form === 'bill') selected @endif>Bill / Expense</option>
            </select>
        </form>
        @if($canCreate)
            <a href="{{ route('expenses.create', ['type' => 'bill']) }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> Add Bill/Expense</a>
        @endif
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="ex-length"></div>
            <div class="sx-export-btns" id="ex-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="ex-colvis"></ul>
                </div>
            </div>
            <div id="ex-search"></div>
        </div>

        <div class="table-responsive">
            <table id="ex-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="ex-check-all"></th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Vendor</th>
                        <th>Expense</th>
                        <th>Paid</th>
                        <th>Pay Mode</th>
                        <th>Note</th>
                        <th>Created by</th>
                        <th>Status</th>
                        <th class="sx-no-export">Pay</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenses as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="ex-row-check" value="{{ $row->id }}"></td>
                            <td data-order="{{ optional($row->expense_date)->format('Y-m-d') }}">{{ optional($row->expense_date)->format('d-m-Y') }}</td>
                            <td>{{ optional($row->category)->name ?: '-' }}</td>
                            <td>{{ optional($row->vendor)->name ?: '-' }}</td>
                            <td data-order="{{ $row->amount }}">{{ number_format($row->amount, 2) }}</td>
                            <td data-order="{{ $row->paid_amount }}">{{ number_format($row->paid_amount, 2) }}</td>
                            <td>{{ $row->payModeLabel() }}</td>
                            <td>{{ $row->notes }}</td>
                            <td>{{ optional($row->user)->name ?: '-' }}</td>
                            <td>
                                <span class="{{ $row->isPaid() ? 'sx-status-active' : 'sx-status-inactive' }}">{{ $row->statusLabel() }}</span>
                            </td>
                            <td>
                                @if(!$row->isPaid() && $canUpdate)
                                    <form method="post" action="{{ route('expenses.pay', $row) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-xs">Pay</button>
                                    </form>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        @if($canUpdate)
                                            <li>
                                                <a href="{{ route('expenses.edit', $row) }}"><i class="fa fa-pencil"></i> Edit</a>
                                            </li>
                                        @endif
                                        @if($canDelete)
                                            <li>
                                                <a href="#" class="sx-delete-expense" data-form="sx-del-ex-{{ $row->id }}">
                                                    <i class="fa fa-trash"></i> Delete
                                                </a>
                                                <form id="sx-del-ex-{{ $row->id }}" action="{{ route('expenses.destroy', $row) }}" method="post" class="hidden">
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
    var table = $('#ex-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, 10, 11], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching expenses found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#ex-length');
            wrap.find('.dataTables_filter').appendTo('#ex-search');
        }
    });

    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) return;
        var title = header.clone().children().remove().end().text().trim();
        if (!title) return;
        $('#ex-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });
    $('#ex-colvis').on('click', function (e) { e.stopPropagation(); });
    $('#ex-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });
    $('#ex-check-all').on('change', function () {
        $('.ex-row-check').prop('checked', this.checked);
    });
    $('.sx-items-body .table-responsive').on('show.bs.dropdown', function () {
        $(this).css('overflow', 'visible');
    }).on('hide.bs.dropdown', function () {
        $(this).css('overflow', 'auto');
    });

    function exportRows() {
        var headers = [], skip = {};
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
        return [data.headers].concat(data.rows).map(function (r) { return r.map(cell).join(','); }).join('\n');
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
        html += '<style>body{font-family:sans-serif;font-size:13px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px 8px;text-align:left}th{background:#A2502B;color:#fff}</style></head><body>';
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
    $('#ex-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy') {
            var text = [data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n');
            if (navigator.clipboard) navigator.clipboard.writeText(text);
            return;
        }
        if (type === 'csv') { download('expenses.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8'); return; }
        if (type === 'excel') { download('expenses.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel'); return; }
        if (type === 'print' || type === 'pdf') printTable(data, 'Expenses List');
    });

    $(document).on('click', '.sx-delete-expense', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Expense',
                text: 'This expense will be removed.',
                showCancelButton: true,
                confirmButtonColor: '#dd4b39',
                cancelButtonColor: '#00a65a',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Close'
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
            return;
        }
        if (window.confirm('Delete this expense?')) form.submit();
    });
})(jQuery);
</script>
@endpush
