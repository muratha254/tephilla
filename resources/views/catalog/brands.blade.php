@extends('layouts.fleet')

@section('title', 'Brands List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Brands List',
    'subtitle' => 'View/Search Items Brand',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Brands List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Brands List</h3>
        <div class="sx-toolbar-actions">
            @if($canManage)
                <button type="button" class="btn sx-btn-aqua" id="sx-add-brand"><i class="fa fa-plus"></i> Add Brand</button>
            @endif
            <button type="button" class="btn btn-primary" title="Users"><i class="fa fa-user"></i></button>
            <button type="button" class="btn btn-success" title="Import"><i class="fa fa-user-plus"></i></button>
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="brand-length"></div>
            <div class="sx-export-btns" id="brand-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="brand-colvis"></ul>
                </div>
            </div>
            <div id="brand-search"></div>
        </div>

        <div class="table-responsive">
            <table id="brand-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="brand-check-all"></th>
                        <th>Brand ID</th>
                        <th>Brand Code</th>
                        <th>Brand Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($brands as $brand)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="brand-row-check" value="{{ $brand->id }}"></td>
                            <td data-order="{{ $brand->id }}">{{ $brand->id }}</td>
                            <td>{{ $brand->brandCode() }}</td>
                            <td>{{ $brand->name }}</td>
                            <td>{{ $brand->description }}</td>
                            <td>
                                @if($brand->is_active)
                                    <span class="sx-status-active"><i class="fa fa-check-circle"></i> Active</span>
                                @else
                                    <span class="sx-status-inactive"><i class="fa fa-times-circle"></i> Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        @if($canManage)
                                            <li>
                                                <a href="#" class="sx-edit-brand"
                                                    data-url="{{ route('brands.update', $brand) }}"
                                                    data-name="{{ $brand->name }}"
                                                    data-description="{{ $brand->description }}"
                                                    data-active="{{ $brand->is_active ? 1 : 0 }}">
                                                    <i class="fa fa-pencil"></i> Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a href="#" class="sx-swal-delete"
                                                    data-url="{{ route('brands.destroy', $brand) }}"
                                                    data-form="brand-delete-form"
                                                    data-swal-text="This brand will be permanently deleted.">
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

@if($canManage)
<div class="modal fade" id="sx-brand-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="sx-brand-title"><i class="fa fa-plus"></i> Add Brand</h4>
            </div>
            <div class="modal-body">
                <form method="post" id="sx-brand-form">
                    @csrf
                    <input type="hidden" name="_method" id="sx-brand-method" value="POST">
                    <div class="form-group">
                        <label class="sx-req">Brand Name*</label>
                        <input type="text" name="name" id="sx-brand-name" class="form-control" placeholder="Brand Name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="sx-brand-description" class="form-control" rows="4" placeholder="Description"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Status*</label>
                        <select name="is_active" id="sx-brand-active" class="form-control" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="sx-conv-footer">
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<form id="brand-delete-form" method="post" style="display:none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var table = $('#brand-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, 6], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching brands found',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#brand-length');
            wrap.find('.dataTables_filter').appendTo('#brand-search');
        }
    });

    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) return;
        var title = header.clone().children().remove().end().text().trim();
        if (!title) return;
        $('#brand-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });

    $('#brand-colvis').on('click', function (e) { e.stopPropagation(); });
    $('#brand-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });
    $('#brand-check-all').on('change', function () {
        $('.brand-row-check').prop('checked', this.checked);
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

    $('#brand-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy' && navigator.clipboard) {
            navigator.clipboard.writeText([data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n'));
            return;
        }
        if (type === 'csv') download('brands.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8');
        if (type === 'excel') download('brands.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel');
        if (type === 'print' || type === 'pdf') printTable(data, 'Brands List');
    });

    var storeUrl = @json(route('brands.store'));

    function openBrandModal(edit) {
        $('#sx-brand-title').html(edit ? '<i class="fa fa-pencil"></i> Edit Brand' : '<i class="fa fa-plus"></i> Add Brand');
        $('#sx-brand-method').val(edit ? 'PUT' : 'POST');
        $('#sx-brand-modal').modal('show');
    }

    $('#sx-add-brand').on('click', function () {
        $('#sx-brand-form').attr('action', storeUrl);
        $('#sx-brand-name').val('');
        $('#sx-brand-description').val('');
        $('#sx-brand-active').val('1');
        openBrandModal(false);
    });

    $(document).on('click', '.sx-edit-brand', function (e) {
        e.preventDefault();
        var btn = $(this);
        $('#sx-brand-form').attr('action', btn.data('url'));
        $('#sx-brand-name').val(btn.data('name'));
        $('#sx-brand-description').val(btn.data('description') || '');
        $('#sx-brand-active').val(String(btn.data('active')));
        openBrandModal(true);
    });
})(jQuery);
</script>
@endpush
