@extends('layouts.fleet')

@section('title', 'Stock Manager')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Stock Manager',
    'subtitle' => 'View/Search Stock',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Stock Manager'],
    ],
])

@php
    $fmtQty = function ($n) {
        $n = (float) $n;
        return fmod($n, 1.0) === 0.0 ? (string) (int) $n : number_format($n, 2);
    };
    $actionIndex = $canViewCost ? 13 : 12;
@endphp

<form method="get" action="{{ route('stock.manager') }}" class="sx-filter-card">
    <select name="category_id" class="form-control" onchange="this.form.submit()">
        <option value="">All</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" @if(($filters['category_id'] ?? '') == $category->id) selected @endif>{{ $category->optionLabel() }}</option>
        @endforeach
    </select>
    <select name="branch_id" class="form-control" onchange="this.form.submit()">
        <option value="">All Branches</option>
        @foreach($branches as $branch)
            <option value="{{ $branch->id }}" @if(($filters['branch_id'] ?? '') == $branch->id) selected @endif>{{ $branch->name }}</option>
        @endforeach
    </select>
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
                <li><a href="{{ route('products.index') }}">Items List</a></li>
                @if(auth()->user()->hasPermission('inventory.view'))
                    <li><a href="{{ route('stock.alert') }}">Stock Alert</a></li>
                @endif
                @if(auth()->user()->hasPermission('inventory.adjust'))
                    <li><a href="{{ route('stock.issued') }}">Issued/Damaged</a></li>
                    <li><a href="{{ route('stock.conversion') }}">Stock Conversion</a></li>
                @endif
                <li><a href="{{ route('products.labels') }}">Print Labels</a></li>
            </ul>
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="stock-length"></div>
            <div class="sx-export-btns" id="stock-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="stock-colvis"></ul>
                </div>
            </div>
            <div id="stock-search"></div>
        </div>

        <div class="table-responsive">
            <table id="stock-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="stock-check-all"></th>
                        <th>Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="sx-th-stock">Stock</th>
                        <th>Reorder</th>
                        @if($canViewCost)<th>Cost</th>@endif
                        <th>R.Price</th>
                        <th>W.Price</th>
                        <th>Prom. Price</th>
                        <th>Tax</th>
                        <th>Expiry</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        @php
                            $qty = (float) ($stock[$product->id] ?? 0);
                            $reorder = (float) $product->reorder_level;
                        @endphp
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="stock-row-check" value="{{ $product->id }}"></td>
                            <td data-order="{{ $product->id }}">{{ $product->item_code }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ optional($product->category)->name }}</td>
                            <td>{{ optional($product->unit)->short_name }}</td>
                            <td data-order="{{ $qty }}">{{ $fmtQty($qty) }}</td>
                            <td data-order="{{ $reorder }}">{{ $fmtQty($reorder) }}</td>
                            @if($canViewCost)
                                <td data-order="{{ (float) $product->purchase_price }}">{{ number_format((float) $product->purchase_price, 2) }}</td>
                            @endif
                            <td data-order="{{ (float) $product->selling_price }}">{{ number_format((float) $product->selling_price, 2) }}</td>
                            <td data-order="{{ (float) $product->wholesale_price }}">{{ number_format((float) ($product->wholesale_price ?? 0), 2) }}</td>
                            <td data-order="{{ (float) $product->promo_price }}">{{ number_format((float) ($product->promo_price ?? 0), 2) }}</td>
                            <td>{{ $product->taxLabel() }}</td>
                            <td data-order="{{ optional($product->expiry_date)->format('Y-m-d') }}">
                                @if($product->expiry_date)
                                    {{ $product->expiry_date->format('Y-m-d') }} <i class="fa fa-calendar sx-expiry-icon"></i>
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
                                        @if(!empty($canAdjust))
                                            <li>
                                                <a href="#" class="sx-open-adjust" data-url="{{ route('stock.adjust', $product) }}" data-variants="{{ $product->variants->where('is_active', true)->map(fn ($variant) => ['id' => $variant->id, 'name' => $variant->color ?: optional($variant->colour)->name ?: $variant->displayName()])->values()->toJson() }}">
                                                    <i class="fa fa-balance-scale"></i> Adjust Stock
                                                </a>
                                            </li>
                                        @endif
                                        @if($canUpdate)
                                            <li>
                                                <a href="#" class="sx-open-price"
                                                    data-url="{{ route('stock.price', $product) }}"
                                                    data-cost="{{ $product->purchase_price }}"
                                                    data-retail="{{ $product->selling_price }}"
                                                    data-wholesale="{{ $product->wholesale_price ?? 0 }}"
                                                    data-promo="{{ $product->promo_price ?? 0 }}">
                                                    <i class="fa fa-tag"></i> Update Price
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

@include('stock.partials.manager-modals')
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var actionIndex = {{ (int) $actionIndex }};
    var table = $('#stock-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, actionIndex], orderable: false, searchable: false }
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
            wrap.find('.dataTables_length').appendTo('#stock-length');
            wrap.find('.dataTables_filter').appendTo('#stock-search');
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
        $('#stock-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });

    $('#stock-colvis').on('click', function (e) {
        e.stopPropagation();
    });
    $('#stock-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });

    $('#stock-check-all').on('change', function () {
        $('.stock-row-check').prop('checked', this.checked);
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

    $('#stock-export').on('click', '[data-export]', function () {
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
            download('stock-manager.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8');
            return;
        }
        if (type === 'excel') {
            download('stock-manager.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel');
            return;
        }
        if (type === 'print' || type === 'pdf') {
            printTable(data, 'Stock Manager');
        }
    });

    $(document).on('click', '.sx-open-adjust', function (e) {
        e.preventDefault();
        var btn = $(this);
        $('#sx-adjust-form').attr('action', btn.attr('data-url'));
        $('#sx-adjust-date').val('{{ now()->format('Y-m-d') }}');
        $('#sx-adjust-status').val('');
        var variants = [];
        try {
            variants = JSON.parse(btn.attr('data-variants') || '[]');
        } catch (err) {
            variants = [];
        }
        if (!Array.isArray(variants)) {
            variants = [];
        }
        var select = $('#sx-adjust-variant');
        select.empty();
        if (variants.length) {
            select.append('<option value="">Select colour</option>');
            variants.forEach(function (variant) {
                select.append($('<option>', { value: variant.id, text: variant.name }));
            });
            select.prop('required', true).prop('disabled', false);
            $('#sx-adjust-colour-wrap').show();
        } else {
            select.prop('required', false).prop('disabled', true);
            $('#sx-adjust-colour-wrap').hide();
        }
        $('#sx-adjust-modal').modal('show');
    });

    $(document).on('click', '.sx-open-price', function (e) {
        e.preventDefault();
        var btn = $(this);
        $('#sx-price-form').attr('action', btn.data('url'));
        $('#sx-price-cost').val(Number(btn.data('cost') || 0));
        $('#sx-price-retail').val(Number(btn.data('retail') || 0));
        $('#sx-price-wholesale').val(Number(btn.data('wholesale') || 0));
        $('#sx-price-promo').val(Number(btn.data('promo') || 0));
        $('#sx-price-modal').modal('show');
    });
})(jQuery);
</script>
@endpush
