@extends('layouts.fleet')

@section('title', 'Items List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Items List',
    'subtitle' => 'View/Search Items',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Items List'],
    ],
])

<form method="get" action="{{ route('products.index') }}" class="sx-filter-card" id="items-filter-form">
    <select name="category_id" class="form-control" onchange="this.form.submit()">
        <option value="">All Categories</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" @if(($filters['category_id'] ?? '') == $category->id) selected @endif>{{ $category->name }}</option>
        @endforeach
    </select>
    <div class="form-control" style="display:flex;align-items:center;background:#f7f7f7;">
        Active branch:
        <strong style="margin-left:6px;">{{ optional($branch)->name ?? '—' }}</strong>
    </div>
</form>

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <div class="dropdown">
            <button type="button" class="btn sx-btn-gold dropdown-toggle" data-toggle="dropdown">
                <i class="fa fa-list-alt"></i> Quick Links
            </button>
            <ul class="dropdown-menu">
                @if($canCreate)
                    <li><a href="{{ route('products.create') }}">New Item</a></li>
                @endif
                @if(auth()->user()->hasPermission('categories.view'))
                    <li><a href="{{ route('categories.index') }}">Categories List</a></li>
                @endif
                @if(auth()->user()->hasPermission('brands.view'))
                    <li><a href="{{ route('brands.index') }}">Brands List</a></li>
                @endif
                @if(auth()->user()->hasPermission('units.view'))
                    <li><a href="{{ route('units.index') }}">Unit List (UOM)</a></li>
                @endif
                @if(auth()->user()->hasPermission('inventory.view'))
                    <li><a href="{{ route('stock.manager') }}">Stock Manager</a></li>
                    <li><a href="{{ route('stock.alert') }}">Stock Alert</a></li>
                @endif
                <li><a href="{{ route('products.labels') }}">Print Labels</a></li>
                <li><a href="{{ route('products.prices') }}">Price Change Log</a></li>
            </ul>
        </div>
        @if($canCreate)
            <a href="{{ route('products.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New Item</a>
        @endif
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="items-length"></div>
            <div class="sx-export-btns" id="items-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="items-colvis"></ul>
                </div>
            </div>
            <div id="items-search"></div>
        </div>

        <div class="table-responsive">
            <table id="items-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="items-check-all"></th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Stock</th>
                        <th>Reorder</th>
                        <th>Tax</th>
                        <th>Order Item <i class="fa fa-info-circle" title="Items marked for supplier ordering"></i></th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        @php
                            $reorder = (float) $product->reorder_level;
                            $reorderDisplay = fmod($reorder, 1.0) === 0.0 ? (int) $reorder : $reorder;
                            $stockQty = (float) ($stockByProduct[$product->id] ?? 0);
                            $stockDisplay = fmod($stockQty, 1.0) === 0.0 ? (int) $stockQty : $stockQty;
                        @endphp
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="item-row-check" value="{{ $product->id }}"></td>
                            <td data-order="{{ $product->id }}">{{ $product->item_code }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ optional($product->brand)->name }}</td>
                            <td>{{ optional($product->category)->name }}</td>
                            <td>{{ optional($product->unit)->short_name }}</td>
                            <td data-order="{{ $stockQty }}">{{ $product->manage_stock ? $stockDisplay : '—' }}</td>
                            <td data-order="{{ $reorder }}">{{ $reorderDisplay }}</td>
                            <td>{{ $product->taxLabel() }}</td>
                            <td><span class="label label-danger">No</span></td>
                            <td>
                                @if($product->is_active)
                                    <span class="label label-success">Active</span>
                                @else
                                    <span class="label label-default">Inactive</span>
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
                                                <a href="{{ route('products.edit', $product) }}"><i class="fa fa-pencil-square-o"></i> Edit Item Details</a>
                                            </li>
                                        @endif
                                        <li>
                                            <a href="{{ route('products.show', $product) }}"><i class="fa fa-flag"></i> Item Profile</a>
                                        </li>
                                        @if(!empty($canConvert))
                                            <li>
                                                <a href="#" class="sx-open-conversion"
                                                    data-id="{{ $product->id }}"
                                                    data-name="{{ $product->name }}"
                                                    data-price="{{ $product->selling_price }}"
                                                    data-url="{{ route('products.children.store', $product) }}"
                                                    data-list="{{ route('products.children', $product) }}">
                                                    <i class="fa fa-refresh"></i> Item Conversion
                                                </a>
                                            </li>
                                        @endif
                                        @if($canDelete)
                                            <li>
                                                <a href="#" class="sx-swal-delete"
                                                    data-url="{{ route('products.destroy', $product) }}"
                                                    data-form="items-delete-form"
                                                    data-swal-text="This item will be permanently deleted.">
                                                    <i class="fa fa-trash"></i> Delete
                                                </a>
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

<form id="items-delete-form" method="post" style="display:none;">
    @csrf
    @method('DELETE')
</form>

@include('products.partials.conversion-modal')
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var table = $('#items-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, 11], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching items found',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#items-length');
            wrap.find('.dataTables_filter').appendTo('#items-search');
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
        $('#items-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });

    $('#items-colvis').on('click', function (e) {
        e.stopPropagation();
    });
    $('#items-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });

    $('#items-check-all').on('change', function () {
        $('.item-row-check').prop('checked', this.checked);
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

    $('#items-export').on('click', '[data-export]', function () {
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
            download('items-list.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8');
            return;
        }
        if (type === 'excel') {
            download('items-list.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel');
            return;
        }
        if (type === 'print' || type === 'pdf') {
            printTable(data, 'Items List');
        }
    });
})(jQuery);
</script>
@include('products.partials.conversion-script')
@endpush
