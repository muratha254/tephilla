@extends('layouts.fleet')

@section('title', 'Customers List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customers List',
    'subtitle' => '',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Customers List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div class="sx-toolbar-actions">
            @if($canCreate)
                <button type="button" class="btn sx-btn-aqua" data-toggle="modal" data-target="#sx-customer-import"><i class="fa fa-upload"></i> Bulk Upload</button>
                <a href="{{ route('customers.template') }}" class="btn sx-btn-aqua"><i class="fa fa-download"></i> Download Template</a>
            @endif
            <button type="button" class="btn sx-btn-aqua" id="sx-customer-statement"><i class="fa fa-file-text-o"></i> Statement</button>
            @if($canUpdate)
                <button type="button" class="btn sx-btn-aqua" id="sx-customer-writeoff"><i class="fa fa-minus-circle"></i> Write Off Balance</button>
            @endif
            <a href="{{ route('customers.download') }}" class="btn sx-btn-aqua"><i class="fa fa-download"></i> Download List</a>
        </div>
        @if($canCreate)
            <a href="{{ route('customers.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New Customer</a>
        @endif
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="cu-length"></div>
            <div class="sx-export-btns" id="cu-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="cu-colvis"></ul>
                </div>
            </div>
            <div id="cu-search"></div>
        </div>

        <div class="table-responsive">
            <table id="cu-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="cu-check-all"></th>
                        <th>Customer Name</th>
                        <th>Phone No.</th>
                        <th>Credit Limit</th>
                        <th>Credit Amount</th>
                        <th>KRA</th>
                        <th>Address</th>
                        <th>Category</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="cu-row-check" value="{{ $customer->id }}"></td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->phone }}</td>
                            <td>Ksh {{ number_format((float) $customer->credit_limit, 2) }}</td>
                            <td>Ksh {{ number_format($customer->creditAmount(), 2) }}</td>
                            <td>{{ $customer->tax_number }}</td>
                            <td>{{ $customer->address }}</td>
                            <td>{{ optional($customer->category)->name ?: 'General' }}</td>
                            <td>{{ optional($customer->branch)->name ?: optional($branch)->name }}</td>
                            <td>{{ $customer->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        @if($canUpdate && ! $customer->is_walk_in)
                                            <li><a href="{{ route('customers.edit', $customer) }}"><i class="fa fa-pencil"></i> Edit</a></li>
                                        @endif
                                        @if($canDelete && ! $customer->is_walk_in)
                                            <li>
                                                <a href="#" class="sx-archive-customer" data-form="sx-arc-{{ $customer->id }}"><i class="fa fa-archive"></i> Archive</a>
                                                <form id="sx-arc-{{ $customer->id }}" action="{{ route('customers.destroy', $customer) }}" method="post" class="hidden">
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

<form id="sx-writeoff-form" method="post" action="{{ route('customers.write-off') }}" class="hidden">
    @csrf
</form>

@if($canCreate)
<div class="modal fade" id="sx-customer-import" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('customers.import') }}" enctype="multipart/form-data" class="modal-content sx-conversion-modal">
            @csrf
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Bulk Upload</h4>
            </div>
            <div class="modal-body">
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
    var table = $('#cu-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        autoWidth: false,
        columnDefs: [{ targets: [0, 10], orderable: false, searchable: false }],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching customers found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#cu-length');
            wrap.find('.dataTables_filter').appendTo('#cu-search');
        }
    });
    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) return;
        var title = header.clone().children().remove().end().text().trim();
        if (!title) return;
        $('#cu-colvis').append('<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>');
    });
    $('#cu-colvis').on('click', function (e) { e.stopPropagation(); });
    $('#cu-colvis').on('change', 'input', function () { table.column($(this).data('col')).visible(this.checked); });
    $('#cu-check-all').on('change', function () { $('.cu-row-check').prop('checked', this.checked); });
    $('.sx-items-body .table-responsive').on('show.bs.dropdown', function () {
        $(this).css('overflow', 'visible');
    }).on('hide.bs.dropdown', function () {
        $(this).css('overflow', 'auto');
    });

    function selectedIds() {
        return $('.cu-row-check:checked').map(function () { return this.value; }).get();
    }
    function exportRows() {
        var headers = [], skip = {};
        table.columns().every(function () {
            var header = $(this.header());
            if (!this.visible() || header.hasClass('sx-no-export')) { skip[this.index()] = true; return; }
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
        var html = '<html><head><title>' + title + '</title><style>body{font-family:sans-serif;font-size:13px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px 8px}th{background:#c9a027;color:#fff}</style></head><body><h3>' + title + '</h3><table><thead><tr>';
        data.headers.forEach(function (h) { html += '<th>' + h + '</th>'; });
        html += '</tr></thead><tbody>';
        data.rows.forEach(function (r) { html += '<tr>' + r.map(function (c) { return '<td>' + c + '</td>'; }).join('') + '</tr>'; });
        html += '</tbody></table></body></html>';
        var w = window.open('', '_blank');
        w.document.write(html); w.document.close(); w.focus(); w.print();
    }
    $('#cu-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy') {
            var text = [data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n');
            if (navigator.clipboard) navigator.clipboard.writeText(text);
            return;
        }
        if (type === 'csv') { download('customers.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8'); return; }
        if (type === 'excel') { download('customers.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel'); return; }
        if (type === 'print' || type === 'pdf') printTable(data, 'Customers List');
    });

    $('#sx-customer-statement').on('click', function () {
        var ids = selectedIds();
        var url = @json(route('customers.statement'));
        if (ids.length) {
            url += '?' + ids.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
        }
        window.location.href = url;
    });
    $('#sx-customer-writeoff').on('click', function () {
        var ids = selectedIds();
        if (!ids.length) {
            if (window.Swal) Swal.fire({ icon: 'warning', title: 'Write Off', text: 'Select at least one customer.' });
            return;
        }
        var go = function () {
            var form = $('#sx-writeoff-form');
            form.find('input[name="ids[]"]').remove();
            ids.forEach(function (id) {
                form.append($('<input type="hidden" name="ids[]">').val(id));
            });
            form.submit();
        };
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Write Off Balance',
                text: 'Clear opening balances for the selected customers?',
                showCancelButton: true,
                confirmButtonColor: '#dd4b39',
                confirmButtonText: 'Write Off'
            }).then(function (result) { if (result.isConfirmed) go(); });
            return;
        }
        if (window.confirm('Write off selected balances?')) go();
    });
    $(document).on('click', '.sx-archive-customer', function (e) {
        e.preventDefault();
        var form = document.getElementById($(this).data('form'));
        if (!form) return;
        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: 'Archive Customer',
                text: 'This customer will move to Archived Customers.',
                showCancelButton: true,
                confirmButtonColor: '#dd4b39',
                confirmButtonText: 'Archive'
            }).then(function (result) { if (result.isConfirmed) form.submit(); });
            return;
        }
        if (window.confirm('Archive this customer?')) form.submit();
    });
})(jQuery);
</script>
@endpush
