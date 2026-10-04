@extends('layouts.fleet')

@section('title', 'Stock conversion Report')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Stock conversion Report',
    'backUrl' => route('stock.manager'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Stock conversion Report'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Stock conversion Report</h3>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="conv-length"></div>
            <div class="sx-export-btns" id="conv-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="conv-colvis"></ul>
                </div>
            </div>
            <div id="conv-search"></div>
        </div>

        <div class="table-responsive">
            <table id="conv-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="conv-check-all"></th>
                        <th>Parent Product</th>
                        <th>Qty Converted</th>
                        <th>Child Product</th>
                        <th>Qty Produced</th>
                        <th>Description</th>
                        <th>Date/Time</th>
                        <th>Created By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="conv-row-check" value="{{ $row['id'] }}"></td>
                            <td>{{ $row['parent'] }}</td>
                            <td data-order="{{ $row['qty_converted'] }}">{{ rtrim(rtrim(number_format($row['qty_converted'], 2, '.', ''), '0'), '.') }}</td>
                            <td>{{ $row['child'] }}</td>
                            <td data-order="{{ $row['qty_produced'] }}">{{ rtrim(rtrim(number_format($row['qty_produced'], 2, '.', ''), '0'), '.') }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td data-order="{{ optional($row['when'])->timestamp ?: 0 }}">
                                {{ $row['when'] ? $row['when']->format('Y-m-d h:i:s a') : '' }}
                            </td>
                            <td>{{ $row['user'] }}</td>
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
    var table = $('#conv-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[6, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching conversions found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#conv-length');
            wrap.find('.dataTables_filter').appendTo('#conv-search');
        }
    });

    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) return;
        var title = header.clone().children().remove().end().text().trim();
        if (!title) return;
        $('#conv-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });

    $('#conv-colvis').on('click', function (e) { e.stopPropagation(); });
    $('#conv-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });
    $('#conv-check-all').on('change', function () {
        $('.conv-row-check').prop('checked', this.checked);
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

    $('#conv-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy' && navigator.clipboard) {
            navigator.clipboard.writeText([data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n'));
            return;
        }
        if (type === 'csv') download('stock-conversion.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8');
        if (type === 'excel') download('stock-conversion.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel');
        if (type === 'print' || type === 'pdf') printTable(data, 'Stock conversion Report');
    });
})(jQuery);
</script>
@endpush
