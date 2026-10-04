@extends('layouts.fleet')

@section('title', 'Archived Customers')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Archived Customers',
    'subtitle' => '',
    'backUrl' => route('customers.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Archived Customers'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="ca-length"></div>
            <div class="sx-export-btns" id="ca-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="ca-colvis"></ul>
                </div>
            </div>
            <div id="ca-search"></div>
        </div>

        <div class="table-responsive">
            <table id="ca-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="ca-check-all"></th>
                        <th>Customer Name</th>
                        <th>Phone No.</th>
                        <th>Alt. Phone</th>
                        <th>Credit Limit</th>
                        <th>Credit Amount</th>
                        <th>L.Points</th>
                        <th>Estate</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="ca-row-check" value="{{ $customer->id }}"></td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->phone }}</td>
                            <td>{{ $customer->mobile }}</td>
                            <td>Ksh {{ number_format((float) $customer->credit_limit, 2) }}</td>
                            <td>Ksh {{ number_format($customer->creditAmount(), 2) }}</td>
                            <td>{{ number_format((float) $customer->loyalty_points, 2) }}</td>
                            <td>{{ $customer->estate }}</td>
                            <td>{{ $customer->address }}</td>
                            <td>Archived</td>
                            <td>
                                @if($canUpdate)
                                    <form action="{{ route('customers.restore', $customer->id) }}" method="post">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-xs"><i class="fa fa-undo"></i> Restore</button>
                                    </form>
                                @endif
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
    var table = $('#ca-table').DataTable({
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
            wrap.find('.dataTables_length').appendTo('#ca-length');
            wrap.find('.dataTables_filter').appendTo('#ca-search');
        }
    });
    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) return;
        var title = header.clone().children().remove().end().text().trim();
        if (!title) return;
        $('#ca-colvis').append('<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>');
    });
    $('#ca-colvis').on('click', function (e) { e.stopPropagation(); });
    $('#ca-colvis').on('change', 'input', function () { table.column($(this).data('col')).visible(this.checked); });
    $('#ca-check-all').on('change', function () { $('.ca-row-check').prop('checked', this.checked); });

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
        var html = '<html><head><title>' + title + '</title><style>body{font-family:sans-serif;font-size:13px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:6px 8px}th{background:#A2502B;color:#fff}</style></head><body><h3>' + title + '</h3><table><thead><tr>';
        data.headers.forEach(function (h) { html += '<th>' + h + '</th>'; });
        html += '</tr></thead><tbody>';
        data.rows.forEach(function (r) { html += '<tr>' + r.map(function (c) { return '<td>' + c + '</td>'; }).join('') + '</tr>'; });
        html += '</tbody></table></body></html>';
        var w = window.open('', '_blank');
        w.document.write(html); w.document.close(); w.focus(); w.print();
    }
    $('#ca-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy') {
            var text = [data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n');
            if (navigator.clipboard) navigator.clipboard.writeText(text);
            return;
        }
        if (type === 'csv') { download('archived-customers.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8'); return; }
        if (type === 'excel') { download('archived-customers.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel'); return; }
        if (type === 'print' || type === 'pdf') printTable(data, 'Archived Customers');
    });
})(jQuery);
</script>
@endpush
