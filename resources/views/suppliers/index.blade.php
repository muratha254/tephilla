@extends('layouts.fleet')

@section('title', 'Suppliers List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Suppliers List',
    'subtitle' => 'View/Search Suppliers',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Suppliers List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div class="sx-toolbar-actions">
            @if($canCreate)
                <button type="button" class="btn sx-btn-aqua" data-toggle="modal" data-target="#sx-supplier-import">
                    <i class="fa fa-upload"></i> Bulk Upload
                </button>
                <a href="{{ route('suppliers.template') }}" class="btn sx-btn-aqua">
                    <i class="fa fa-download"></i> Download Template
                </a>
            @endif
        </div>
        @if($canCreate)
            <a href="{{ route('suppliers.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New Supplier</a>
        @endif
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="su-length"></div>
            <div class="sx-export-btns" id="su-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="su-colvis"></ul>
                </div>
            </div>
            <div id="su-search"></div>
        </div>

        <div class="table-responsive">
            <table id="su-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="su-check-all"></th>
                        <th>Supplier Name</th>
                        <th>Phone No.</th>
                        <th>Email Address</th>
                        <th>Address</th>
                        <th>Cur. Balance</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suppliers as $supplier)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="su-row-check" value="{{ $supplier->id }}"></td>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->phone }}</td>
                            <td>{{ $supplier->email }}</td>
                            <td>{{ $supplier->address }}</td>
                            <td data-order="{{ $supplier->currentBalance() }}">{{ number_format($supplier->currentBalance(), 2) }}</td>
                            <td>{{ optional($supplier->branch)->name ?: optional($branch)->name ?: '-' }}</td>
                            <td>{{ $supplier->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        @if($canUpdate)
                                            <li>
                                                <a href="{{ route('suppliers.edit', $supplier) }}"><i class="fa fa-pencil"></i> Edit</a>
                                            </li>
                                        @endif
                                        @if($canDelete)
                                            <li>
                                                <a href="#" class="sx-delete-supplier" data-form="sx-del-sup-{{ $supplier->id }}">
                                                    <i class="fa fa-trash"></i> Delete
                                                </a>
                                                <form id="sx-del-sup-{{ $supplier->id }}" action="{{ route('suppliers.destroy', $supplier) }}" method="post" class="hidden">
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

@if($canCreate)
<div class="modal fade" id="sx-supplier-import" tabindex="-1">
    <div class="modal-dialog" role="document">
        <form method="post" action="{{ route('suppliers.import') }}" enctype="multipart/form-data" class="modal-content sx-conversion-modal">
            @csrf
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Bulk Upload</h4>
            </div>
            <div class="modal-body">
                <p>Upload the CSV template. Existing names can be imported as new rows.</p>
                <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success">Upload</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var table = $('#su-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, 8], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching suppliers found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#su-length');
            wrap.find('.dataTables_filter').appendTo('#su-search');
        }
    });

    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) return;
        var title = header.clone().children().remove().end().text().trim();
        if (!title) return;
        $('#su-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });
    $('#su-colvis').on('click', function (e) { e.stopPropagation(); });
    $('#su-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });
    $('#su-check-all').on('change', function () {
        $('.su-row-check').prop('checked', this.checked);
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
    $('#su-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy') {
            var text = [data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n');
            if (navigator.clipboard) navigator.clipboard.writeText(text);
            return;
        }
        if (type === 'csv') { download('suppliers.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8'); return; }
        if (type === 'excel') { download('suppliers.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel'); return; }
        if (type === 'print' || type === 'pdf') printTable(data, 'Suppliers List');
    });

    $(document).on('click', '.sx-delete-supplier', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Supplier',
                text: 'This supplier will be removed.',
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
        if (window.confirm('Delete this supplier?')) form.submit();
    });
})(jQuery);
</script>
@endpush
