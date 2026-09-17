@extends('layouts.fleet')

@section('title', 'Items Price Change')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Items Price Change',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Items Price Change'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Items Price Change</h3>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="price-length"></div>
            <div class="sx-export-btns" id="price-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="price-colvis"></ul>
                </div>
            </div>
            <div id="price-search"></div>
        </div>

        <div class="table-responsive">
            <table id="price-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="price-check-all"></th>
                        <th>Description</th>
                        <th>Old Price</th>
                        <th>New Price</th>
                        <th>User</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        @php
                            $name = $productNames[$log->auditable_id] ?? data_get($log->before_json, 'name') ?? data_get($log->after_json, 'name') ?? 'Item';
                            $oldPrice = (float) data_get($log->before_json, 'selling_price', 0);
                            $newPrice = (float) data_get($log->after_json, 'selling_price', 0);
                            $when = $log->created_at;
                        @endphp
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="price-row-check" value="{{ $log->id }}"></td>
                            <td>{{ $name }}</td>
                            <td data-order="{{ $oldPrice }}">{{ number_format($oldPrice, 2) }}</td>
                            <td data-order="{{ $newPrice }}">{{ number_format($newPrice, 2) }}</td>
                            <td>{{ optional($log->user)->name }}</td>
                            <td data-order="{{ optional($when)->timestamp ?: 0 }}">
                                {{ $when ? $when->format('Y-m-d h:i:s a') : '' }}
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
    var table = $('#price-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[5, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching price changes found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#price-length');
            wrap.find('.dataTables_filter').appendTo('#price-search');
        }
    });

    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) return;
        var title = header.clone().children().remove().end().text().trim();
        if (!title) return;
        $('#price-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });

    $('#price-colvis').on('click', function (e) { e.stopPropagation(); });
    $('#price-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });
    $('#price-check-all').on('change', function () {
        $('.price-row-check').prop('checked', this.checked);
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

    $('#price-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy' && navigator.clipboard) {
            navigator.clipboard.writeText([data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n'));
            return;
        }
        if (type === 'csv') download('price-change.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8');
        if (type === 'excel') download('price-change.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel');
        if (type === 'print' || type === 'pdf') printTable(data, 'Items Price Change');
    });
})(jQuery);
</script>
@endpush
