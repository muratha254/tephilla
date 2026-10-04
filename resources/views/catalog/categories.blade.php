@extends('layouts.fleet')

@section('title', 'Categories List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Categories List',
    'subtitle' => 'View/Search Items Category',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Categories List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Categories List</h3>
        <div class="sx-toolbar-actions">
            @if($canManage)
                <button type="button" class="btn sx-btn-aqua" id="sx-add-category"><i class="fa fa-plus"></i> Add Category</button>
            @endif
            <button type="button" class="btn btn-primary" title="Users"><i class="fa fa-user"></i></button>
            <button type="button" class="btn btn-success" title="Import"><i class="fa fa-user-plus"></i></button>
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="cat-length"></div>
            <div class="sx-export-btns" id="cat-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="cat-colvis"></ul>
                </div>
            </div>
            <div id="cat-search"></div>
        </div>

        <div class="table-responsive">
            <table id="cat-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="cat-check-all"></th>
                        <th>Category ID</th>
                        <th>Category Code</th>
                        <th>Branch Name</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="cat-row-check" value="{{ $category->id }}"></td>
                            <td data-order="{{ $category->id }}">{{ $category->id }}</td>
                            <td>{{ $category->categoryCode() }}</td>
                            <td>{{ optional($category->branch)->name ?: optional($branch)->name }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->description }}</td>
                            <td>
                                @if($category->is_active)
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
                                        @if($canManage)
                                            <li>
                                                <a href="#" class="sx-edit-category"
                                                    data-url="{{ route('categories.update', $category) }}"
                                                    data-id="{{ $category->id }}"
                                                    data-name="{{ $category->name }}"
                                                    data-description="{{ $category->description }}"
                                                    data-branch="{{ $category->branch_id ?: $selectedBranchId }}"
                                                    data-parent="{{ $category->parent_id }}"
                                                    data-pos="{{ $category->show_on_pos ? 1 : 0 }}">
                                                    <i class="fa fa-pencil"></i> Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a href="#" class="sx-swal-delete"
                                                    data-url="{{ route('categories.destroy', $category) }}"
                                                    data-form="cat-delete-form"
                                                    data-swal-text="This category will be permanently deleted.">
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
<div class="modal fade" id="sx-category-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="sx-category-title"><i class="fa fa-plus"></i> Add Category</h4>
            </div>
            <div class="modal-body">
                <form method="post" id="sx-category-form">
                    @csrf
                    <input type="hidden" name="_method" id="sx-category-method" value="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="sx-req">Branch Name*</label>
                                <select name="branch_id" id="sx-cat-branch" class="form-control" required>
                                    @foreach($branches as $row)
                                        <option value="{{ $row->id }}" @if((string) $selectedBranchId === (string) $row->id) selected @endif>{{ $row->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="sx-req">Show On POS*</label>
                                <select name="show_on_pos" id="sx-cat-pos" class="form-control" required>
                                    <option value="1">YES</option>
                                    <option value="0">NO</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="sx-req">Category Name*</label>
                                <input type="text" name="name" id="sx-cat-name" class="form-control" placeholder="Category Name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Parent</label>
                                <select name="parent_id" id="sx-cat-parent" class="form-control">
                                    <option value="">Select Parent</option>
                                    @foreach($categories as $parent)
                                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" id="sx-cat-description" class="form-control" rows="5" placeholder="Description"></textarea>
                            </div>
                        </div>
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

<form id="cat-delete-form" method="post" style="display:none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var table = $('#cat-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, 7], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching categories found',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#cat-length');
            wrap.find('.dataTables_filter').appendTo('#cat-search');
        }
    });

    table.columns().every(function () {
        var header = $(this.header());
        if (header.hasClass('sx-check-col')) return;
        var title = header.clone().children().remove().end().text().trim();
        if (!title) return;
        $('#cat-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });

    $('#cat-colvis').on('click', function (e) { e.stopPropagation(); });
    $('#cat-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
    });
    $('#cat-check-all').on('change', function () {
        $('.cat-row-check').prop('checked', this.checked);
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

    $('#cat-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy' && navigator.clipboard) {
            navigator.clipboard.writeText([data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n'));
            return;
        }
        if (type === 'csv') download('categories.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8');
        if (type === 'excel') download('categories.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel');
        if (type === 'print' || type === 'pdf') printTable(data, 'Categories List');
    });

    var storeUrl = @json(route('categories.store'));
    var defaultBranch = @json((string) ($selectedBranchId ?: optional($branch)->id));

    function openCategoryModal(edit) {
        $('#sx-category-title').html(edit ? '<i class="fa fa-pencil"></i> Edit Category' : '<i class="fa fa-plus"></i> Add Category');
        $('#sx-category-method').val(edit ? 'PUT' : 'POST');
        $('#sx-category-modal').modal('show');
    }

    function resetParentOptions(hideId) {
        $('#sx-cat-parent option').prop('disabled', false).show();
        if (hideId) {
            $('#sx-cat-parent option[value="' + hideId + '"]').prop('disabled', true).hide();
        }
    }

    $('#sx-add-category').on('click', function () {
        $('#sx-category-form').attr('action', storeUrl);
        $('#sx-cat-name').val('');
        $('#sx-cat-description').val('');
        $('#sx-cat-branch').val(defaultBranch);
        $('#sx-cat-pos').val('1');
        resetParentOptions();
        $('#sx-cat-parent').val('');
        openCategoryModal(false);
    });

    $(document).on('click', '.sx-edit-category', function (e) {
        e.preventDefault();
        var btn = $(this);
        var id = String(btn.data('id') || '');
        $('#sx-category-form').attr('action', btn.data('url'));
        $('#sx-cat-name').val(btn.data('name'));
        $('#sx-cat-description').val(btn.data('description') || '');
        $('#sx-cat-branch').val(String(btn.data('branch') || defaultBranch));
        $('#sx-cat-pos').val(String(btn.data('pos')));
        resetParentOptions(id);
        $('#sx-cat-parent').val(String(btn.data('parent') || ''));
        openCategoryModal(true);
    });
})(jQuery);
</script>
@endpush
