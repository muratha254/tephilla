@extends('layouts.master')

@section('title')
    Supplier List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Supplier List</li>
@endsection

@section('content')
@if(session('success'))
<div class="alert alert-success alert-dismissible">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
    <i class="icon fa fa-check"></i> {{ session('success') }}
</div>
@endif
@if(session('open_import_modal'))
<script>$(function() { $('#modal-import').modal('show'); });</script>
@endif
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <button onclick="addForm('{{ route('supplier.store') }}')" class="btn btn-success btn-flat"><i class="fa fa-plus-circle"></i> Add New Supplier</button>
                <button type="button" class="btn btn-primary btn-flat" data-toggle="modal" data-target="#modal-import"><i class="fa fa-upload"></i> Import from Excel</button>
                @if(auth()->user() && (auth()->user()->hasRole(\App\Models\User::ROLE_ADMIN) || (auth()->user()->can_update ?? false)))
                <button type="button" class="btn btn-warning btn-flat" id="btn-open-supplier-merge-toolbar" data-toggle="modal" data-target="#modal-supplier-merge"><i class="fa fa-compress"></i> Merge suppliers</button>
                <button type="button" class="btn btn-info btn-flat" data-toggle="modal" data-target="#modal-excel-sync"><i class="fa fa-file-excel-o"></i> Upload Supplier Excel</button>
                @endif
                <a href="{{ route('supplier.export-excel') }}" class="btn btn-default btn-flat"><i class="fa fa-file-excel-o"></i> Export to Excel</a>
            </div>
            <div class="box-body table-responsive">
                <table id="supplier-table" class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Name</th>
                        <th>Telephone</th>
                        <th>Address</th>
                         <th>Mode of Payment</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('supplier.form')
@includeIf('supplier.import-form')
@if(auth()->user() && (auth()->user()->hasRole(\App\Models\User::ROLE_ADMIN) || (auth()->user()->can_update ?? false)))
@includeIf('supplier.excel_sync_modal')
@includeIf('supplier.merge_modal')
@endif
@includeIf('supplier.consignment')
@endsection

@push('css')
@if(auth()->user() && (auth()->user()->hasRole(\App\Models\User::ROLE_ADMIN) || (auth()->user()->can_update ?? false)))
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    #modal-supplier-merge .modal-dialog,
    #modal-excel-sync .modal-dialog {
        margin: 20px auto;
    }
    #modal-supplier-merge .supplier-merge-modal-body,
    #modal-excel-sync .modal-body {
        max-height: calc(100vh - 180px);
        overflow-x: hidden;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
</style>
@endif
@endpush

@push('scripts')
@if(auth()->user() && (auth()->user()->hasRole(\App\Models\User::ROLE_ADMIN) || (auth()->user()->can_update ?? false)))
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endif
<script>
    let table;

    $(function () {
        // Only initialize DataTable on the main supplier table, not modal tables
        table = $('#supplier-table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('supplier.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'nama'},
                {data: 'telepon'},
                {data: 'alamat'},
                {data: 'mop'},
                {data: 'aksi', searchable: false, sortable: false},
            ]
        });

        $('#modal-form').validator().on('submit', function (e) {
            if (! e.preventDefault()) {
                $.post($('#modal-form form').attr('action'), $('#modal-form form').serialize())
                    .done((response) => {
                        $('#modal-form').modal('hide');
                        table.ajax.reload();
                    })
                    .fail((errors) => {
                        alert('Unable to save data');
                        return;
                    });
            }
        });

        var __excelSyncLastMatched = [];
        var __excelSyncLastMopMismatches = [];
        var __excelSyncSourceFile = '';
        var __excelSyncLastSummary = null;
        var __mergeFromExcelSync = false;
        function escHtml(s) {
            return $('<div/>').text(s == null ? '' : String(s)).html();
        }
        function excelSyncShowAlert(type, msg) {
            var $a = $('#excel-sync-alert');
            $a.removeClass('alert-success alert-danger alert-warning').addClass('alert-' + (type || 'info')).html(msg).show();
        }
        function excelSyncResetReport() {
            $('#excel-sync-summary, #excel-sync-report, #btn-excel-sync-apply').hide();
            $('#excel-sync-summary-cards, #excel-sync-matched-body, #excel-sync-mop-groups, #excel-sync-missing-list, #excel-sync-extra-body, #excel-sync-dup-groups').empty();
            $('#excel-sync-update-table, #excel-sync-extra-table').hide();
            $('#excel-sync-update-empty, #excel-sync-mop-empty, #excel-sync-missing-empty, #excel-sync-extra-empty').show();
            $('#excel-sync-mop-help').hide();
            $('#excel-sync-check-all').prop('checked', true);
            $('#excel-sync-meta').hide().empty();
        }
        function excelSyncSupplierGroupIdKey(suppliers) {
            return (suppliers || []).map(function (s) {
                return parseInt(s.id_supplier, 10);
            }).filter(function (n) { return !isNaN(n); }).sort(function (a, b) {
                return a - b;
            }).join(',');
        }
        function excelSyncUpdateApplyButtonVisibility() {
            var updateCount = $('#excel-sync-matched-body tr').length;
            var mopApplyCount = $('#excel-sync-mop-groups .excel-sync-mop-check').length;
            if (updateCount || mopApplyCount) {
                $('#btn-excel-sync-apply').show();
            } else {
                $('#btn-excel-sync-apply').hide();
            }
        }
        function excelSyncRemoveMopGroups(removedIds, mergedGroupIdKey) {
            var removed = {};
            (removedIds || []).forEach(function (id) { removed[parseInt(id, 10)] = true; });
            $('#excel-sync-mop-groups .well').each(function () {
                var $box = $(this);
                var groupKey = $box.attr('data-supplier-ids') || '';
                var orphanId = parseInt($box.attr('data-orphan-id'), 10);
                if ((mergedGroupIdKey && groupKey === mergedGroupIdKey) || (!isNaN(orphanId) && removed[orphanId])) {
                    $box.remove();
                }
            });
            var mopCount = $('#excel-sync-mop-groups .well').length;
            $('#excel-sync-count-mop').text(mopCount);
            if (!mopCount) {
                $('#excel-sync-mop-empty').show();
                $('#excel-sync-mop-help').hide();
            }
            __excelSyncLastMopMismatches = __excelSyncLastMopMismatches.filter(function (m) {
                var orphanId = parseInt(m.id_supplier, 10);
                if (!isNaN(orphanId) && removed[orphanId]) {
                    return false;
                }
                var groupKey = excelSyncSupplierGroupIdKey((m.merge_group && m.merge_group.suppliers) ? m.merge_group.suppliers : []);
                return !mergedGroupIdKey || groupKey !== mergedGroupIdKey;
            });
            if (__excelSyncLastSummary) {
                __excelSyncLastSummary.mop_mismatches = mopCount;
            }
            excelSyncUpdateSummaryCard('MOP mismatches', mopCount);
            excelSyncUpdateApplyButtonVisibility();
        }
        function excelSyncRenderSummary(summary) {
            if (!summary) { return; }
            __excelSyncLastSummary = $.extend({}, summary);
            var cards = [
                { label: 'Excel rows', value: summary.excel_rows_processed, cls: 'bg-aqua' },
                { label: 'To update', value: summary.to_update, cls: 'bg-yellow' },
                { label: 'MOP mismatches', value: summary.mop_mismatches, cls: 'bg-red' },
                { label: 'Missing in system', value: summary.missing_from_system, cls: 'bg-red' },
                { label: 'Extra in system', value: summary.extra_in_system, cls: 'bg-gray' },
                { label: 'Duplicate groups', value: summary.duplicate_groups, cls: 'bg-purple' },
                { label: 'Unchanged', value: summary.unchanged, cls: 'bg-green' }
            ];
            var html = '';
            cards.forEach(function (c) {
                html += '<div class="col-xs-6 col-sm-4 col-md-3" style="margin-bottom:10px;">' +
                    '<div class="small-box ' + c.cls + '"><div class="inner"><h3>' + escHtml(c.value) + '</h3><p>' + escHtml(c.label) + '</p></div></div></div>';
            });
            $('#excel-sync-summary-cards').html(html);
            $('#excel-sync-summary').show();
        }
        function excelSyncUpdateSummaryCard(label, value) {
            $('#excel-sync-summary-cards .small-box').each(function () {
                if ($(this).find('p').text() === label) {
                    $(this).find('h3').text(value);
                }
            });
        }
        function excelSyncRenderDuplicateSection(dups) {
            $('#excel-sync-dup-groups').empty();
            $('#excel-sync-count-dup').text(dups.length);
            if (!dups.length) {
                $('#excel-sync-dup-groups').html('<p class="text-muted small">None detected.</p>');
                return;
            }
            dups.forEach(function (g) {
                if (typeof renderDuplicateGroupBox === 'function') {
                    $('#excel-sync-dup-groups').append(renderDuplicateGroupBox(g));
                    return;
                }
                var names = (g.suppliers || []).map(function (s) {
                    return escHtml(s.nama) + ' (#' + s.id_supplier + ', ' + escHtml(s.mop || '') + ')';
                }).join('; ');
                var notes = [];
                if (g.different_mop) { notes.push('Different payment methods'); }
                if (g.different_name) { notes.push('Different name spelling'); }
                var noteHtml = notes.length ? ' <span class="label label-warning">' + escHtml(notes.join('; ')) + '</span>' : '';
                var $box = $('<div class="well well-sm" style="margin-bottom:8px;"></div>');
                $box.append('<strong>' + escHtml(g.label || 'Duplicate group') + '</strong>' + noteHtml + '<br><small>' + names + '</small>');
                $('#excel-sync-dup-groups').append($box);
            });
        }
        function excelSyncRefreshDuplicateGroups() {
            return $.get('{{ route('supplier.duplicate_groups') }}').done(function (res) {
                var dups = (res.name_groups || []).concat(res.phone_groups || []);
                excelSyncRenderDuplicateSection(dups);
                if (__excelSyncLastSummary) {
                    __excelSyncLastSummary.duplicate_groups = dups.length;
                }
                excelSyncUpdateSummaryCard('Duplicate groups', dups.length);
            });
        }
        function excelSyncRemoveRowsBySupplierIds($tbody, removedIds) {
            var removed = {};
            removedIds.forEach(function (id) { removed[parseInt(id, 10)] = true; });
            $tbody.find('tr').each(function () {
                var $tr = $(this);
                var id = parseInt($tr.data('id'), 10);
                if (isNaN(id)) {
                    id = parseInt($tr.find('td:first').text(), 10);
                }
                if (!isNaN(id) && removed[id]) {
                    $tr.remove();
                }
            });
        }
        function excelSyncRefreshAfterMerge(keepId, mergeIds, mergedGroupIdKey) {
            mergeIds = (mergeIds || []).map(function (x) { return parseInt(x, 10); })
                .filter(function (n) { return !isNaN(n) && n > 0; });

            if (mergedGroupIdKey) {
                $('#excel-sync-dup-groups .well').each(function () {
                    if ($(this).attr('data-supplier-ids') === mergedGroupIdKey) {
                        $(this).remove();
                    }
                });
                var dupCount = $('#excel-sync-dup-groups .well').length;
                if (!dupCount && !$('#excel-sync-dup-groups .text-muted').length) {
                    $('#excel-sync-dup-groups').html('<p class="text-muted small">None detected.</p>');
                }
                $('#excel-sync-count-dup').text(dupCount);
                if (__excelSyncLastSummary) {
                    __excelSyncLastSummary.duplicate_groups = dupCount;
                }
                excelSyncUpdateSummaryCard('Duplicate groups', dupCount);
            }

            excelSyncRemoveRowsBySupplierIds($('#excel-sync-extra-body'), mergeIds);
            var extraCount = $('#excel-sync-extra-body tr').length;
            $('#excel-sync-count-extra').text(extraCount);
            if (!extraCount) {
                $('#excel-sync-extra-empty').show();
                $('#excel-sync-extra-table').hide();
            }
            if (__excelSyncLastSummary) {
                __excelSyncLastSummary.extra_in_system = extraCount;
            }
            excelSyncUpdateSummaryCard('Extra in system', extraCount);

            excelSyncRemoveRowsBySupplierIds($('#excel-sync-matched-body'), mergeIds);
            var updateCount = $('#excel-sync-matched-body tr').length;
            $('#excel-sync-count-update').text(updateCount);
            if (!updateCount) {
                $('#excel-sync-update-empty').show();
                $('#excel-sync-update-table').hide();
            } else {
                $('#excel-sync-update-empty').hide();
                $('#excel-sync-update-table').show();
            }
            __excelSyncLastMatched = __excelSyncLastMatched.filter(function (m) {
                return mergeIds.indexOf(parseInt(m.id_supplier, 10)) < 0;
            });
            if (__excelSyncLastSummary) {
                __excelSyncLastSummary.to_update = updateCount;
            }
            excelSyncUpdateSummaryCard('To update', updateCount);

            excelSyncRemoveMopGroups(mergeIds, mergedGroupIdKey);
            excelSyncUpdateApplyButtonVisibility();

            excelSyncShowAlert('success', 'Suppliers merged successfully. The duplicate group has been removed from the report below.');
            if (!mergedGroupIdKey) {
                excelSyncRefreshDuplicateGroups().fail(function () {
                    excelSyncShowAlert('warning', 'Suppliers merged. Duplicate list could not be refreshed — click Compare with system to reload the full report.');
                });
            }
        }
        function excelSyncRenderPreview(res, successMessage) {
            excelSyncShowAlert('success', successMessage || 'Comparison complete. Review the reconciliation report below before applying changes.');
            if (res.meta) {
                var c = res.meta.columns_detected || {};
                var metaHtml = '<strong>Columns detected</strong> — Header row: <code>' + escHtml(res.meta.header_sheet_row) + '</code>, data from row: <code>' + escHtml(res.meta.data_starts_sheet_row) + '</code><br>' +
                    'Supplier Code: ' + escHtml(c.supplier_code || '(none)') + ' &nbsp;|&nbsp; ' +
                    'Name: ' + escHtml(c.name || '(none)') + ' &nbsp;|&nbsp; ' +
                    'Payment Method: ' + escHtml(c.payment_method || '(none)');
                $('#excel-sync-meta').html(metaHtml).show();
            }

            excelSyncRenderSummary(res.summary || {});

            __excelSyncLastMatched = res.to_update || res.matched || [];
            var updateCount = __excelSyncLastMatched.length;
            $('#excel-sync-count-update').text(updateCount);
            if (!updateCount) {
                $('#excel-sync-update-empty').show();
            } else {
                $('#excel-sync-update-empty').hide();
                $('#excel-sync-update-table').show();
                __excelSyncLastMatched.forEach(function (m) {
                    var otherParts = [];
                    if (m.changes) {
                        Object.keys(m.changes).forEach(function (k) {
                            if (k === 'mop') { return; }
                            otherParts.push(escHtml(k) + ': "' + escHtml(m.changes[k].old) + '" → "' + escHtml(m.changes[k].new) + '"');
                        });
                    }
                    var curMop = m.current ? m.current.mop : '';
                    var newMop = m.proposed ? m.proposed.mop : '';
                    var tr = '<tr data-id="' + m.id_supplier + '">' +
                        '<td><input type="checkbox" class="excel-sync-row-check" checked></td>' +
                        '<td>' + m.row + '</td>' +
                        '<td>' + escHtml(m.supplier_code || m.id_supplier) + '</td>' +
                        '<td>' + escHtml(m.current.nama) + '</td>' +
                        '<td>' + escHtml(curMop) + '</td>' +
                        '<td><strong>' + escHtml(newMop) + '</strong></td>' +
                        '<td><small>' + (otherParts.length ? otherParts.join('<br>') : '—') + '</small></td></tr>';
                    $('#excel-sync-matched-body').append(tr);
                });
            }

            __excelSyncLastMopMismatches = res.mop_mismatches || [];
            var mopRows = __excelSyncLastMopMismatches;
            $('#excel-sync-count-mop').text(mopRows.length);
            if (!mopRows.length) {
                $('#excel-sync-mop-empty').show();
                $('#excel-sync-mop-help').hide();
            } else {
                $('#excel-sync-mop-empty').hide();
                $('#excel-sync-mop-help').show();
                mopRows.forEach(function (m) {
                    $('#excel-sync-mop-groups').append(renderMopMismatchGroupBox(m));
                });
            }
            excelSyncUpdateApplyButtonVisibility();

            var missing = res.missing_from_system || [];
            $('#excel-sync-count-missing').text(missing.length);
            if (!missing.length) {
                $('#excel-sync-missing-empty').show();
            } else {
                $('#excel-sync-missing-empty').hide();
                missing.forEach(function (u) {
                    $('#excel-sync-missing-list').append(
                        '<li><strong>Row ' + u.row + '</strong>: ' + escHtml(u.nama || '(no name)') +
                        (u.payment_method ? ' — ' + escHtml(u.payment_method) : '') +
                        (u.supplier_code ? ' — Code ' + escHtml(u.supplier_code) : '') +
                        '<br><span class="text-muted">' + escHtml(u.reason) + '</span></li>'
                    );
                });
            }

            var extra = res.extra_in_system || [];
            $('#excel-sync-count-extra').text(extra.length);
            if (!extra.length) {
                $('#excel-sync-extra-empty').show();
            } else {
                $('#excel-sync-extra-empty').hide();
                $('#excel-sync-extra-table').show();
                extra.forEach(function (s) {
                    $('#excel-sync-extra-body').append(
                        '<tr><td>' + s.id_supplier + '</td><td>' + escHtml(s.nama) + '</td><td>' + escHtml(s.mop) + '</td></tr>'
                    );
                });
            }

            var dups = res.duplicate_suppliers || res.phone_duplicate_groups || [];
            excelSyncRenderDuplicateSection(dups);

            $('#excel-sync-report').show();
        }
        function excelSyncRunPreview(successMessage) {
            var f = $('#excel-sync-file')[0].files[0];
            if (!f) {
                excelSyncShowAlert('warning', 'Choose an Excel file first to refresh the comparison.');
                return $.Deferred().reject().promise();
            }
            __excelSyncSourceFile = f.name || '';
            var fd = new FormData();
            fd.append('file', f);
            fd.append('_token', $('meta[name="csrf-token"]').attr('content'));
            excelSyncShowAlert('info', '<i class="fa fa-spinner fa-spin"></i> ' + (successMessage ? 'Refreshing comparison report…' : 'Comparing uploaded file with system suppliers…'));
            excelSyncResetReport();
            return $.ajax({
                url: '{{ route('supplier.excel_sync.preview') }}',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false
            }).done(function (res) {
                excelSyncRenderPreview(res, successMessage);
            }).fail(function (xhr) {
                excelSyncResetReport();
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Preview failed.';
                excelSyncShowAlert('danger', msg);
            });
        }
        $('#btn-excel-sync-preview').on('click', function () {
            var f = $('#excel-sync-file')[0].files[0];
            if (!f) {
                alert('Choose an Excel file first.');
                return;
            }
            excelSyncRunPreview();
        });
        $('#excel-sync-check-all').on('change', function () {
            $('.excel-sync-row-check').prop('checked', $(this).prop('checked'));
        });
        $('#btn-excel-sync-apply').on('click', function () {
            var rows = [];
            var seenIds = {};
            function pushRow(payload) {
                var id = parseInt(payload.id_supplier, 10);
                if (isNaN(id) || id <= 0 || seenIds[id]) {
                    return;
                }
                seenIds[id] = true;
                rows.push(payload);
            }
            $('#excel-sync-matched-body tr').each(function () {
                if (!$(this).find('.excel-sync-row-check').prop('checked')) {
                    return;
                }
                var id = parseInt($(this).data('id'), 10);
                var m = __excelSyncLastMatched.find(function (x) { return x.id_supplier === id; });
                if (m) {
                    pushRow({
                        id_supplier: m.id_supplier,
                        nama: m.proposed.nama,
                        mop: m.proposed.mop,
                        alamat: m.proposed.alamat,
                        telepon: m.proposed.telepon
                    });
                }
            });
            $('#excel-sync-mop-groups .excel-sync-mop-check:checked').each(function () {
                var id = parseInt($(this).data('id'), 10);
                var m = __excelSyncLastMopMismatches.find(function (x) { return x.id_supplier === id; });
                if (m && m.proposed) {
                    pushRow({
                        id_supplier: m.id_supplier,
                        nama: m.proposed.nama,
                        mop: m.proposed.mop,
                        alamat: m.proposed.alamat,
                        telepon: m.proposed.telepon
                    });
                }
            });
            if (!rows.length) {
                alert('Select at least one row to apply.');
                return;
            }
            if (!confirm('Apply changes to ' + rows.length + ' supplier(s) to match the Excel file?\n\nThis updates payment method (and name where shown) and writes an audit log for each change.')) {
                return;
            }
            $.ajax({
                url: '{{ route('supplier.excel_sync.apply') }}',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
                data: JSON.stringify({ rows: rows, source_file: __excelSyncSourceFile })
            }).done(function (r) {
                alert(r.message || 'Saved.');
                table.ajax.reload();
                $('#modal-excel-sync').modal('hide');
            }).fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Apply failed.');
            });
        });
        function runSupplierMerge(keepId, mergeIds, finalNama, finalMop, onSuccess) {
            var mergePayload = { keep_id: keepId, merge_ids: mergeIds };
            if (finalNama) { mergePayload.final_nama = finalNama; }
            if (finalMop) { mergePayload.final_mop = finalMop; }
            $.ajax({
                url: '{{ route('supplier.merge') }}',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
                data: JSON.stringify(mergePayload)
            }).done(function (r) {
                table.ajax.reload();
                if (typeof onSuccess === 'function') {
                    onSuccess(r);
                } else {
                    alert(r.message || 'Merged.');
                }
            }).fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Merge failed.');
            });
        }

        @if(auth()->user() && (auth()->user()->hasRole(\App\Models\User::ROLE_ADMIN) || (auth()->user()->can_update ?? false)))
        var __mergeGroupSuppliers = [];
        var supplierMergeSelectUrl = '{{ route('supplier.select_options') }}';
        function supplierSelect2Config($el, multiple) {
            return {
                dropdownParent: $('#modal-supplier-merge'),
                placeholder: multiple ? 'Search suppliers to merge…' : 'Search supplier to keep…',
                allowClear: true,
                width: '100%',
                ajax: {
                    url: supplierMergeSelectUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term || '' }; },
                    processResults: function (data) { return data; }
                },
                minimumInputLength: 0
            };
        }
        function setSupplierSelectOption($sel, id, text) {
            if ($sel.find('option[value="' + id + '"]').length === 0) {
                $sel.append(new Option(text, id, true, true));
            }
            $sel.val(String(id)).trigger('change');
        }
        function setSupplierSelectOptions($sel, items) {
            $sel.empty();
            items.forEach(function (it) {
                $sel.append(new Option(it.text, it.id, true, true));
            });
            $sel.val(items.map(function (it) { return String(it.id); })).trigger('change');
        }
        function pickKeeperFromGroup(suppliers) {
            var sorted = suppliers.slice().sort(function (a, b) {
                var pc = (b.product_count || 0) - (a.product_count || 0);
                if (pc !== 0) { return pc; }
                return a.id_supplier - b.id_supplier;
            });
            return { keep: sorted[0], merge: sorted.slice(1) };
        }
        function mopMatchesExcel(mop, excelMop) {
            var ml = (mop || '').toLowerCase();
            var el = (excelMop || '').toLowerCase();
            if (el.indexOf('cash') >= 0 && el.indexOf('cons') < 0) {
                return ml.indexOf('cash') >= 0 && ml.indexOf('cons') < 0;
            }
            return ml.indexOf('cons') >= 0;
        }
        function pickKeeperForExcelReconcile(suppliers, excelMop) {
            var matching = (suppliers || []).filter(function (s) {
                return mopMatchesExcel(s.mop, excelMop);
            });
            if (matching.length >= 1) {
                var pick = pickKeeperFromGroup(matching);
                return {
                    keep: pick.keep,
                    merge: suppliers.filter(function (s) {
                        return parseInt(s.id_supplier, 10) !== parseInt(pick.keep.id_supplier, 10);
                    })
                };
            }
            return pickKeeperFromGroup(suppliers);
        }
        function supplierLabel(s) {
            var t = '#' + s.id_supplier + ' — ' + (s.nama || '');
            if (s.mop) { t += ' (' + s.mop + ')'; }
            if (s.product_count != null) { t += ' · ' + s.product_count + ' product(s)'; }
            return t;
        }
        function populateMergeChoices(suppliers) {
            var names = [];
            var mops = [];
            (suppliers || []).forEach(function (s) {
                var n = (s.nama || '').trim();
                if (n && names.indexOf(n) === -1) { names.push(n); }
                var m = (s.mop || '').trim();
                if (m) {
                    var ml = m.toLowerCase();
                    var cm = (ml.indexOf('cash') >= 0 && ml.indexOf('cons') < 0) ? 'Cash'
                        : (ml.indexOf('cons') >= 0 ? 'Consignment' : m);
                    if (mops.indexOf(cm) === -1) { mops.push(cm); }
                }
            });
            if (!mops.length) { mops = ['Consignment']; }

            var $nr = $('#merge-name-radios').empty();
            names.forEach(function (n, i) {
                $nr.append(
                    '<label class="radio" style="margin-top:0;margin-bottom:6px;">' +
                    '<input type="radio" name="merge_final_name_choice" value="' + escHtml(n) + '"' + (i === 0 ? ' checked' : '') + '> ' +
                    escHtml(n) + '</label>'
                );
            });
            $('#merge-name-choices').toggle(names.length > 1);
            $('#merge-modal-final-nama-custom').val('');

            var $mr = $('#merge-mop-radios').empty();
            mops.forEach(function (m, i) {
                $mr.append(
                    '<label class="radio-inline" style="margin-right:16px;">' +
                    '<input type="radio" name="merge_final_mop_choice" value="' + escHtml(m) + '"' + (i === 0 ? ' checked' : '') + '> ' +
                    escHtml(m) + '</label>'
                );
            });
            $('#merge-mop-choices').toggle(mops.length > 1);
        }
        function getSelectedMergeFinals() {
            var custom = ($('#merge-modal-final-nama-custom').val() || '').trim();
            var fn = custom;
            if (!fn) {
                fn = $('input[name="merge_final_name_choice"]:checked').val() || '';
            }
            var fm = $('input[name="merge_final_mop_choice"]:checked').val() || '';
            return { finalNama: fn || null, finalMop: fm || null };
        }
        function fillMergeModalFromGroup(suppliers, excelHints) {
            excelHints = excelHints || {};
            __mergeGroupSuppliers = suppliers || [];
            var pick = excelHints.excelMop
                ? pickKeeperForExcelReconcile(__mergeGroupSuppliers, excelHints.excelMop)
                : pickKeeperFromGroup(__mergeGroupSuppliers);
            $('#merge-keep-select').empty();
            $('#merge-into-select').empty();
            __mergeGroupSuppliers.forEach(function (s) {
                $('#merge-keep-select').append(new Option(supplierLabel(s), s.id_supplier, false, false));
            });
            setSupplierSelectOption($('#merge-keep-select'), pick.keep.id_supplier, supplierLabel(pick.keep));
            setSupplierSelectOptions($('#merge-into-select'), pick.merge.map(function (s) {
                return { id: s.id_supplier, text: supplierLabel(s) };
            }));
            populateMergeChoices(__mergeGroupSuppliers);
            if (excelHints.excelMop) {
                var $mopRadio = $('input[name="merge_final_mop_choice"][value="' + excelHints.excelMop + '"]');
                if ($mopRadio.length) {
                    $mopRadio.prop('checked', true);
                }
            }
            if (excelHints.excelNama) {
                var $nameRadio = $('input[name="merge_final_name_choice"][value="' + excelHints.excelNama + '"]');
                if ($nameRadio.length) {
                    $nameRadio.prop('checked', true);
                    $('#merge-modal-final-nama-custom').val('');
                } else {
                    $('#merge-modal-final-nama-custom').val(excelHints.excelNama);
                }
            }
            $('#merge-preview-box').hide().empty();
        }
        function syncMergeIntoFromKeeper() {
            var keepId = parseInt($('#merge-keep-select').val(), 10);
            if (!keepId || !__mergeGroupSuppliers.length) { return; }
            var others = __mergeGroupSuppliers.filter(function (s) {
                return parseInt(s.id_supplier, 10) !== keepId;
            });
            setSupplierSelectOptions($('#merge-into-select'), others.map(function (s) {
                return { id: s.id_supplier, text: supplierLabel(s) };
            }));
        }
        function openMergeModalForGroup(suppliers) {
            __mergeFromExcelSync = true;
            fillMergeModalFromGroup(suppliers);
            $('#modal-excel-sync').modal('hide');
            $('#modal-supplier-merge').modal('show');
        }
        function openMergeModalForMopMismatch(m) {
            var suppliers = (m.merge_group && m.merge_group.suppliers) ? m.merge_group.suppliers : [];
            if (suppliers.length < 2) {
                alert('Need at least two supplier records to merge.');
                return;
            }
            __mergeFromExcelSync = true;
            fillMergeModalFromGroup(suppliers, {
                excelMop: m.proposed ? m.proposed.mop : '',
                excelNama: m.proposed ? m.proposed.nama : ''
            });
            $('#modal-excel-sync').modal('hide');
            $('#modal-supplier-merge').modal('show');
        }
        function renderMopMismatchGroupBox(m) {
            var g = m.merge_group || {};
            var suppliers = g.suppliers || [];
            var groupIdKey = excelSyncSupplierGroupIdKey(suppliers);
            var title = 'Excel row ' + m.row + ': ' + escHtml((m.proposed && m.proposed.nama) ? m.proposed.nama : (m.current ? m.current.nama : ''))
                + ' — expects <strong>' + escHtml((m.proposed && m.proposed.mop) ? m.proposed.mop : '') + '</strong>';
            var hint = g.match_hint
                ? ('<br><span class="text-info"><i class="fa fa-info-circle"></i> ' + escHtml(g.match_hint) + '</span>')
                : '';
            var orphanId = parseInt(m.id_supplier, 10);
            var lines = suppliers.map(function (s) {
                var sid = parseInt(s.id_supplier, 10);
                var line = escHtml(supplierLabel(s));
                if (sid === orphanId) {
                    line += ' <span class="label label-danger">' + escHtml(m.changes.mop.old) + ' → ' + escHtml(m.changes.mop.new) + '</span>';
                } else if (mopMatchesExcel(s.mop, m.proposed ? m.proposed.mop : '')) {
                    line += ' <span class="label label-success">matches Excel</span>';
                }
                return line;
            }).join('<br>');
            if (!lines) {
                lines = escHtml(supplierLabel({
                    id_supplier: m.id_supplier,
                    nama: m.current ? m.current.nama : '',
                    mop: m.current ? m.current.mop : '',
                    product_count: 0
                })) + ' <span class="label label-danger">' + escHtml(m.changes.mop.old) + ' → ' + escHtml(m.changes.mop.new) + '</span>';
            }
            var $box = $('<div class="well well-sm" style="margin-bottom:8px;"></div>');
            if (groupIdKey) {
                $box.attr('data-supplier-ids', groupIdKey);
            }
            $box.attr('data-orphan-id', orphanId);
            $box.append('<strong>' + title + '</strong>' + hint + '<br><small>' + lines + '</small><br>');
            var $actions = $('<div style="margin-top:6px;"></div>');
            $actions.append(
                '<label class="checkbox-inline" style="margin-right:12px;">' +
                '<input type="checkbox" class="excel-sync-mop-check" data-id="' + orphanId + '" checked> ' +
                'Apply Excel payment method to #' + orphanId +
                '</label>'
            );
            if (suppliers.length >= 2) {
                var $btn = $('<button type="button" class="btn btn-xs btn-primary"><i class="fa fa-cog"></i> Configure merge</button>');
                $btn.on('click', function () {
                    openMergeModalForMopMismatch(m);
                });
                $actions.append(' ').append($btn);
            }
            $box.append($actions);
            return $box;
        }
        function renderDuplicateGroupBox(g) {
            var title = g.match_type === 'same_phone'
                ? ('Same phone: ' + (g.phone_digits || g.label))
                : (g.match_type === 'exact_name' ? 'Same name' : 'Similar name') + ': ' + escHtml(g.label || '');
            var hint = g.match_hint ? ('<br><span class="text-info"><i class="fa fa-info-circle"></i> ' + escHtml(g.match_hint) + '</span>') : '';
            var lines = (g.suppliers || []).map(function (s) {
                return escHtml(supplierLabel(s));
            }).join('<br>');
            var $box = $('<div class="well well-sm" style="margin-bottom:8px;"></div>');
            var groupIdKey = excelSyncSupplierGroupIdKey(g.suppliers || []);
            if (groupIdKey) {
                $box.attr('data-supplier-ids', groupIdKey);
            }
            $box.append('<strong>' + title + '</strong>' + hint + '<br><small>' + lines + '</small><br>');
            var $btn = $('<button type="button" class="btn btn-xs btn-primary" style="margin-top:6px;"><i class="fa fa-cog"></i> Configure merge</button>');
            $btn.on('click', function () {
                openMergeModalForGroup(g.suppliers || []);
            });
            $box.append($btn);
            return $box;
        }
        function getMergeModalSelection() {
            var keep = parseInt($('#merge-keep-select').val(), 10);
            var mergeIds = ($('#merge-into-select').val() || []).map(function (x) { return parseInt(x, 10); })
                .filter(function (n) { return !isNaN(n) && n > 0 && n !== keep; });
            return { keep: keep, mergeIds: mergeIds };
        }
        $('#btn-open-supplier-merge-toolbar').on('click', function () {
            __mergeFromExcelSync = false;
        });
        $('#btn-excel-sync-open-merge').on('click', function () {
            __mergeFromExcelSync = true;
        });
        $('#modal-supplier-merge').on('shown.bs.modal', function () {
            if (!$('#merge-keep-select').hasClass('select2-hidden-accessible')) {
                $('#merge-keep-select').select2(supplierSelect2Config($('#merge-keep-select'), false));
                $('#merge-into-select').select2(supplierSelect2Config($('#merge-into-select'), true));
            }
        });
        $('#merge-keep-select').on('change', function () {
            if (__mergeGroupSuppliers.length) {
                syncMergeIntoFromKeeper();
            }
        });
        $('#btn-load-duplicate-suppliers').on('click', function () {
            var $status = $('#supplier-dup-scan-status');
            $status.html('<i class="fa fa-spinner fa-spin"></i> Scanning…');
            $('#supplier-dup-groups-wrap').empty();
            $.get('{{ route('supplier.duplicate_groups') }}')
                .done(function (res) {
                    var groups = (res.name_groups || []).concat(res.phone_groups || []);
                    $status.text(groups.length ? ('Found ' + groups.length + ' possible duplicate group(s).') : 'No duplicate groups found.');
                    var $wrap = $('#supplier-dup-groups-wrap');
                    if (!groups.length) {
                        $wrap.html('<p class="text-muted">No groups with the same name, similar name (&amp; vs and), or phone.</p>');
                        return;
                    }
                    groups.forEach(function (g) {
                        $wrap.append(renderDuplicateGroupBox(g));
                    });
                })
                .fail(function (xhr) {
                    $status.text((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Scan failed.');
                });
        });
        $('#btn-supplier-merge-preview').on('click', function () {
            var sel = getMergeModalSelection();
            if (!sel.keep || !sel.mergeIds.length) {
                alert('Choose the supplier to keep and at least one supplier to merge into it.');
                return;
            }
            var finals = getSelectedMergeFinals();
            $.ajax({
                url: '{{ route('supplier.merge.preview') }}',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
                data: JSON.stringify({ keep_id: sel.keep, merge_ids: sel.mergeIds })
            }).done(function (res) {
                var html = '<strong>Keep:</strong> ' + escHtml(supplierLabel(res.keep || { id_supplier: sel.keep, nama: '', product_count: 0 })) + '<br>';
                if (finals.finalNama) {
                    html += '<strong>Final name:</strong> ' + escHtml(finals.finalNama) + '<br>';
                }
                if (finals.finalMop) {
                    html += '<strong>Final payment method:</strong> ' + escHtml(finals.finalMop) + '<br>';
                }
                html += '<strong>Will merge:</strong><ul style="margin:6px 0 0 16px;">';
                (res.merge || []).forEach(function (m) {
                    html += '<li>' + escHtml('#' + m.id_supplier + ' ' + m.nama + ' (' + (m.mop || '') + ') — ' + m.product_count + ' product(s)') + '</li>';
                });
                html += '</ul><strong>Products moving to keeper:</strong> ' + (res.products_moving || 0);
                $('#merge-preview-box').html(html).show();
            }).fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Preview failed.');
            });
        });
        $('#btn-supplier-merge-confirm').on('click', function () {
            var sel = getMergeModalSelection();
            if (!sel.keep || !sel.mergeIds.length) {
                alert('Choose the supplier to keep and at least one supplier to merge into it.');
                return;
            }
            var finals = getSelectedMergeFinals();
            var confirmMsg = 'Merge supplier(s) ' + sel.mergeIds.join(', ') + ' INTO #' + sel.keep + '?';
            if (finals.finalNama) { confirmMsg += '\nFinal name: ' + finals.finalNama; }
            if (finals.finalMop) { confirmMsg += '\nFinal payment method: ' + finals.finalMop; }
            confirmMsg += '\n\nAll products and ledger data will move to the keeper. This cannot be undone.';
            if (!confirm(confirmMsg)) {
                return;
            }
            runSupplierMerge(sel.keep, sel.mergeIds, finals.finalNama, finals.finalMop, function () {
                var mergedKeepId = sel.keep;
                var mergedIds = sel.mergeIds.slice();
                var mergedGroupIdKey = excelSyncSupplierGroupIdKey(__mergeGroupSuppliers || []);
                __mergeGroupSuppliers = [];
                $('#merge-into-select').val(null).trigger('change');
                $('#merge-preview-box').hide().empty();
                $('#merge-name-choices, #merge-mop-choices').hide();
                $('#supplier-dup-groups-wrap').empty();
                $('#supplier-dup-scan-status').text('');

                var returnToExcelSync = __mergeFromExcelSync;
                __mergeFromExcelSync = false;

                $('#modal-supplier-merge').one('hidden.bs.modal', function () {
                    if (returnToExcelSync) {
                        $('#modal-excel-sync').modal('show');
                        excelSyncRefreshAfterMerge(mergedKeepId, mergedIds, mergedGroupIdKey);
                    }
                }).modal('hide');
            });
        });
        @endif
    });

    function addForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Add Supplier');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('post');
        $('#modal-form [name=nama]').focus();
    }

    function editForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Edit Supplier');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('put');
        $('#modal-form [name=nama]').focus();

        $.get(url)
            .done((response) => {
                $('#modal-form [name=nama]').val(response.nama);
                $('#modal-form [name=telepon]').val(response.telepon);
                $('#modal-form [name=alamat]').val(response.alamat);
                // Set MOP value - trim and match case-insensitively
                if (response.mop) {
                    var mopValue = response.mop.toString().trim();
                    // Try to find matching option (case-insensitive)
                    var $mopSelect = $('#modal-form [name=mop]');
                    var found = false;
                    $mopSelect.find('option').each(function() {
                        if ($(this).val().toString().trim().toLowerCase() === mopValue.toLowerCase()) {
                            $mopSelect.val($(this).val());
                            found = true;
                            return false; // break loop
                        }
                    });
                    // If exact match not found, try direct value assignment
                    if (!found) {
                        $mopSelect.val(mopValue);
                    }
                    $mopSelect.trigger('change');
                } else {
                    $('#modal-form [name=mop]').val('').trigger('change');
                }
                $('#modal-form [name=opening_balance]').val(response.opening_balance || 0);
            })
            .fail((errors) => {
                alert('Unable to display data');
                return;
            });
    }

    function deleteData(url) {
        if (confirm('Are you sure you want to delete selected data?')) {
            $.post(url, {
                    '_token': $('[name=csrf-token]').attr('content'),
                    '_method': 'delete'
                })
                .done((response) => {
                    table.ajax.reload();
                })
                .fail((errors) => {
                    alert('Unable to delete data');
                    return;
                });
        }
    }

    // Store the current consignment URL for filtering
    var currentConsignmentUrl = '';
    
    function viewConsignment(url) {
        // Store the base URL for filtering
        currentConsignmentUrl = url;
        
        // Prevent DataTables from initializing on modal tables
        // Make sure no DataTable is initialized on modal tables
        if ($.fn.DataTable.isDataTable('#consignment-info-table')) {
            $('#consignment-info-table').DataTable().destroy();
        }
        if ($.fn.DataTable.isDataTable('#consignment-summary-table')) {
            $('#consignment-summary-table').DataTable().destroy();
        }
        
        $('#modal-consignment').modal('show');
        $('#consignment-loading').show();
        $('#consignment-content').hide();
        
        // Reset date filters
        $('#consignment-start-date').val('');
        $('#consignment-end-date').val('');
        
        // Reset content
        $('#consignment-supplier-name').text('-');
        $('#consignment-supplier-phone').text('-');
        $('#consignment-supplier-address').text('-');
        $('#consignment-total').text('Ksh 0.00');
        $('#consignment-paid').text('Ksh 0.00');
        $('#consignment-pending').text('Ksh 0.00');
        resetSelectedConsignment();
        
        loadConsignmentData(url);
    }
    
    function loadConsignmentData(url) {
        // Get date filter values
        var startDate = $('#consignment-start-date').val();
        var endDate = $('#consignment-end-date').val();
        
        // Build URL with date parameters
        var urlWithParams = url;
        var params = [];
        if (startDate) {
            params.push('start_date=' + encodeURIComponent(startDate));
        }
        if (endDate) {
            params.push('end_date=' + encodeURIComponent(endDate));
        }
        if (params.length > 0) {
            urlWithParams += (url.indexOf('?') > -1 ? '&' : '?') + params.join('&');
        }
        
        $.ajax({
            url: urlWithParams,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Consignment data received:', response);
                
                $('#consignment-loading').hide();
                
                if (response && response.supplier && response.consignment) {
                    // Populate supplier information
                    $('#consignment-supplier-name').text(response.supplier.nama || 'N/A');
                    $('#consignment-supplier-phone').text(response.supplier.telepon || 'N/A');
                    $('#consignment-supplier-address').text(response.supplier.alamat || 'N/A');
                    
                    // Populate consignment information
                    let total = parseFloat(response.consignment.total || 0);
                    let paid = parseFloat(response.consignment.paid || 0);
                    let pending = parseFloat(response.consignment.pending || 0);
                    
                    $('#consignment-total').text('Ksh ' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#consignment-paid').text('Ksh ' + paid.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#consignment-pending').text('Ksh ' + pending.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    
                    // Update badge color based on pending amount
                    let pendingBadge = $('#consignment-pending-badge');
                    if (pending > 0) {
                        pendingBadge.removeClass('label-success').addClass('label-warning');
                    } else {
                        pendingBadge.removeClass('label-warning').addClass('label-success');
                    }

                    // Update overall status badge
                    let statusText = (response.consignment.status || '').toString();
                    let statusBadge = $('#consignment-status-badge');
                    statusBadge.removeClass('label-success label-warning label-danger label-default');
                    if (statusText.toLowerCase() === 'paid') {
                        statusBadge.addClass('label-success');
                    } else if (statusText.toLowerCase() === 'partially paid') {
                        statusBadge.addClass('label-warning');
                    } else {
                        statusBadge.addClass('label-danger');
                        statusText = statusText || 'Not paid';
                    }
                    statusBadge.text(statusText);
                    
                    renderSelectedConsignment(response.selected_item || null);

                    // Load paid items
                    if (response.paid_items && response.paid_items.length > 0) {
                        loadPaidItems(response.paid_items, response.supplier.id);
                    } else {
                        $('#paid-items-loading').hide();
                        $('#paid-items-empty').show();
                        $('#paid-items-table-wrapper').hide();
                        $('#btn-export-pdf').hide();
                        $('#btn-export-excel').hide();
                    }
                    
                    $('#consignment-content').show();
                } else {
                    alert('Invalid response format. Please try again.');
                    $('#consignment-loading').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading consignment:', xhr, status, error);
                $('#consignment-loading').hide();
                
                let errorMsg = 'Unable to load consignment data';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += ': ' + xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMsg += ': Supplier not found';
                } else if (xhr.status === 500) {
                    errorMsg += ': Server error';
                }
                
                alert(errorMsg);
                $('#consignment-content').show(); // Show empty content
            }
        });
    }
    
    // Handle filter button click
    $(document).on('click', '#btn-filter-consignment', function() {
        if (currentConsignmentUrl) {
            $('#consignment-loading').show();
            $('#consignment-content').hide();
            loadConsignmentData(currentConsignmentUrl);
        }
    });
    
    // Handle clear filter button click
    $(document).on('click', '#btn-clear-filter', function() {
        $('#consignment-start-date').val('');
        $('#consignment-end-date').val('');
        if (currentConsignmentUrl) {
            $('#consignment-loading').show();
            $('#consignment-content').hide();
            loadConsignmentData(currentConsignmentUrl);
        }
    });
    
    function loadPaidItems(items, supplierId) {
        $('#paid-items-loading').hide();
        $('#paid-items-empty').hide();
        $('#paid-items-tbody').empty();
        
        let totalPaid = 0;
        
        items.forEach(function(item, index) {
            totalPaid += parseFloat(item.amount_paid || 0);
            
            let row = '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + (item.product_code || 'N/A') + '</td>' +
                '<td>' + (item.product_name || 'N/A') + '</td>' +
                '<td>' + (item.quantity || 0) + '</td>' +
                '<td>Ksh ' + parseFloat(item.unit_price || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>Ksh ' + parseFloat(item.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>Ksh ' + parseFloat(item.amount_paid || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>Ksh ' + parseFloat(item.balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td><span class="label label-' + (item.balance == 0 ? 'success' : 'warning') + '">' + (item.status || 'N/A') + '</span></td>' +
                '<td>' + (item.payment_date || 'N/A') + '</td>' +
                '</tr>';
            
            $('#paid-items-tbody').append(row);
        });
        
        $('#paid-items-total').text('Ksh ' + totalPaid.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#paid-items-table-wrapper').show();
        $('#btn-export-pdf').attr('data-supplier-id', supplierId).show();
        $('#btn-export-excel').attr('data-supplier-id', supplierId).show();
    }
    
    // Handle PDF export
    $(document).on('click', '#btn-export-pdf', function() {
        let supplierId = $(this).attr('data-supplier-id');
        if (supplierId) {
            // Get current date filter values
            let startDate = $('#consignment-start-date').val();
            let endDate = $('#consignment-end-date').val();
            
            // Build URL with date parameters
            let url = '{{ url("/supplier") }}/' + supplierId + '/consignment/export-pdf';
            let params = [];
            if (startDate) {
                params.push('start_date=' + encodeURIComponent(startDate));
            }
            if (endDate) {
                params.push('end_date=' + encodeURIComponent(endDate));
            }
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.open(url, '_blank');
        }
    });
    
    // Handle Excel export
    $(document).on('click', '#btn-export-excel', function() {
        let supplierId = $(this).attr('data-supplier-id');
        if (supplierId) {
            // Get current date filter values
            let startDate = $('#consignment-start-date').val();
            let endDate = $('#consignment-end-date').val();
            
            // Build URL with date parameters
            let url = '{{ url("/supplier") }}/' + supplierId + '/consignment/export-excel';
            let params = [];
            if (startDate) {
                params.push('start_date=' + encodeURIComponent(startDate));
            }
            if (endDate) {
                params.push('end_date=' + encodeURIComponent(endDate));
            }
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            window.location.href = url;
        }
    });

    function resetSelectedConsignment() {
        $('#selected-consignment-box').hide();
        $('#selected-consignment-product').text('-');
        $('#selected-consignment-invoice').text('-');
        $('#selected-consignment-quantity').text('-');
        $('#selected-consignment-total').text('-');
        $('#selected-consignment-paid').text('-');
        $('#selected-consignment-balance').text('-');
        $('#selected-consignment-status').text('-').removeClass('label-success label-warning label-danger').addClass('label-default');
    }

    function renderSelectedConsignment(item) {
        if (!item) {
            resetSelectedConsignment();
            return;
        }

        $('#selected-consignment-box').show();
        $('#selected-consignment-product').text((item.product_name || 'N/A') + (item.product_code ? ' (' + item.product_code + ')' : ''));
        $('#selected-consignment-invoice').text(item.invoice_number || 'N/A');
        $('#selected-consignment-quantity').text(item.quantity || 0);
        $('#selected-consignment-total').text('Ksh ' + parseFloat(item.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#selected-consignment-paid').text('Ksh ' + parseFloat(item.amount_paid || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#selected-consignment-balance').text('Ksh ' + parseFloat(item.balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

        const statusBadge = $('#selected-consignment-status');
        statusBadge.removeClass('label-success label-warning label-danger label-default');
        let statusClass = 'label-danger';
        const statusText = (item.status || 'Not paid').toString();
        if (statusText.toLowerCase() === 'paid') {
            statusClass = 'label-success';
        } else if (statusText.toLowerCase() === 'partially paid') {
            statusClass = 'label-warning';
        }
        statusBadge.addClass(statusClass).text(statusText);
    }
</script>
@endpush