@extends('layouts.fleet')

@section('title', 'Units List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Units List',
    'subtitle' => 'View/Search Units',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Units List'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Units List</h3>
        <div class="sx-toolbar-actions">
            @if($canManage)
                <button type="button" class="btn sx-btn-aqua" id="sx-add-unit"><i class="fa fa-plus"></i> New Unit</button>
            @endif
            <button type="button" class="btn btn-primary" title="Users"><i class="fa fa-user"></i></button>
            <button type="button" class="btn btn-success" title="Import"><i class="fa fa-user-plus"></i></button>
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="unit-length"></div>
            <div class="sx-export-btns" id="unit-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
                    <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="unit-colvis"></ul>
                </div>
            </div>
            <div id="unit-search"></div>
        </div>

        <div class="table-responsive">
            <table id="unit-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>Unit ID</th>
                        <th>Unit Name</th>
                        <th>Short Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($units as $unit)
                        <tr>
                            <td data-order="{{ $unit->id }}">{{ $unit->id }}</td>
                            <td>{{ $unit->name }}</td>
                            <td>{{ $unit->short_name }}</td>
                            <td>{{ $unit->description }}</td>
                            <td>
                                @if($unit->is_active)
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
                                                <a href="#" class="sx-edit-unit"
                                                    data-url="{{ route('units.update', $unit) }}"
                                                    data-id="{{ $unit->id }}"
                                                    data-name="{{ $unit->name }}"
                                                    data-short="{{ $unit->short_name }}"
                                                    data-description="{{ $unit->description }}"
                                                    data-multiplier="{{ $unit->multiplier }}"
                                                    data-base="{{ $unit->base_unit_id }}">
                                                    <i class="fa fa-pencil"></i> Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a href="#" class="sx-swal-delete"
                                                    data-url="{{ route('units.destroy', $unit) }}"
                                                    data-form="unit-delete-form"
                                                    data-swal-text="This unit will be permanently deleted.">
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
<div class="modal fade" id="sx-unit-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="sx-unit-title"><i class="fa fa-plus"></i> New Unit</h4>
            </div>
            <div class="modal-body">
                <form method="post" id="sx-unit-form">
                    @csrf
                    <input type="hidden" name="_method" id="sx-unit-method" value="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="sx-req">Unit Name*</label>
                                <input type="text" name="name" id="sx-unit-name" class="form-control" placeholder="Unit Name" required>
                            </div>
                            <div class="form-group">
                                <label>Units</label>
                                <input type="number" step="0.0001" min="0" name="multiplier" id="sx-unit-multiplier" class="form-control" placeholder="Times Base Unit">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" id="sx-unit-description" class="form-control" rows="4" placeholder="Description"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Short Name</label>
                                <input type="text" name="short_name" id="sx-unit-short" class="form-control" placeholder="Short Name e.g Pc for Pieces">
                            </div>
                            <div class="form-group">
                                <label>Base Unit</label>
                                <select name="base_unit_id" id="sx-unit-base" class="form-control">
                                    <option value="">-Select base unit-</option>
                                    @foreach($units as $base)
                                        <option value="{{ $base->id }}">{{ $base->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success">Save</button>
                        <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<form id="unit-delete-form" method="post" style="display:none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var table = $('#unit-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'asc']],
        autoWidth: false,
        columnDefs: [
            { targets: [5], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching units found',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#unit-length');
            wrap.find('.dataTables_filter').appendTo('#unit-search');
        }
    });

    table.columns().every(function () {
        var header = $(this.header());
        var title = header.clone().children().remove().end().text().trim();
        if (!title) return;
        $('#unit-colvis').append(
            '<li><label><input type="checkbox" data-col="' + this.index() + '" checked> ' + $('<div>').text(title).html() + '</label></li>'
        );
    });

    $('#unit-colvis').on('click', function (e) { e.stopPropagation(); });
    $('#unit-colvis').on('change', 'input', function () {
        table.column($(this).data('col')).visible(this.checked);
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

    $('#unit-export').on('click', '[data-export]', function () {
        var type = $(this).data('export');
        var data = exportRows();
        if (type === 'copy' && navigator.clipboard) {
            navigator.clipboard.writeText([data.headers.join('\t')].concat(data.rows.map(function (r) { return r.join('\t'); })).join('\n'));
            return;
        }
        if (type === 'csv') download('units.csv', '\ufeff' + toCsv(data), 'text/csv;charset=utf-8');
        if (type === 'excel') download('units.xls', '\ufeff' + toCsv(data), 'application/vnd.ms-excel');
        if (type === 'print' || type === 'pdf') printTable(data, 'Units List');
    });

    var storeUrl = @json(route('units.store'));

    function openUnitModal(edit) {
        $('#sx-unit-title').html(edit ? '<i class="fa fa-pencil"></i> Edit Unit' : '<i class="fa fa-plus"></i> New Unit');
        $('#sx-unit-method').val(edit ? 'PUT' : 'POST');
        $('#sx-unit-modal').modal('show');
    }

    function resetBaseOptions(hideId) {
        $('#sx-unit-base option').prop('disabled', false).show();
        if (hideId) {
            $('#sx-unit-base option[value="' + hideId + '"]').prop('disabled', true).hide();
        }
    }

    $('#sx-add-unit').on('click', function () {
        $('#sx-unit-form').attr('action', storeUrl);
        $('#sx-unit-name').val('');
        $('#sx-unit-short').val('');
        $('#sx-unit-description').val('');
        $('#sx-unit-multiplier').val('');
        resetBaseOptions();
        $('#sx-unit-base').val('');
        openUnitModal(false);
    });

    $(document).on('click', '.sx-edit-unit', function (e) {
        e.preventDefault();
        var btn = $(this);
        var id = String(btn.data('id') || '');
        $('#sx-unit-form').attr('action', btn.data('url'));
        $('#sx-unit-name').val(btn.data('name'));
        $('#sx-unit-short').val(btn.data('short') || '');
        $('#sx-unit-description').val(btn.data('description') || '');
        $('#sx-unit-multiplier').val(btn.data('multiplier') || '');
        resetBaseOptions(id);
        $('#sx-unit-base').val(String(btn.data('base') || ''));
        openUnitModal(true);
    });
})(jQuery);
</script>
@endpush
