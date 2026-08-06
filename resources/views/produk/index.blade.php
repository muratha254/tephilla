@extends('layouts.master')

@section('title')
    Stock List
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Stock List</li>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    .out-of-stock-row {
        background-color: #fff5f5 !important;
    }

    .out-of-stock-row td {
        color: #a94442 !important;
        font-weight: 700 !important;
    }

    #produk-stock-table .js-inline-produk {
        font-size: 12px;
        font-weight: 600;
        height: auto;
        padding: 4px 6px;
        color: #1a1a1a;
    }

    #produk-stock-table thead th {
        font-size: 12px;
        font-weight: 700;
        line-height: 1.25;
        padding-top: 7px;
        padding-bottom: 7px;
        vertical-align: middle;
        color: #1a1a1a;
    }

    .produk-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Fit more of the grid on screen — fixed layout + compact cells */
    #produk-stock-table {
        table-layout: fixed;
        width: 100% !important;
        font-size: 12px;
    }
    #produk-stock-table tbody td {
        font-weight: 600;
        color: #1f1f1f;
    }
    #produk-stock-table thead th,
    #produk-stock-table tbody td {
        padding: 5px 6px;
        vertical-align: middle !important;
    }
    #produk-stock-table .produk-cell-ellipsis {
        display: block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 600;
    }
    #produk-stock-table select.js-inline-produk[data-field="id_supplier"],
    #produk-stock-table select.js-inline-produk[data-field="shop_id"] {
        min-width: 0 !important;
        max-width: 100%;
        width: 100%;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 4px;
        height: auto;
        line-height: 1.3;
    }
    #produk-stock-table input.js-inline-produk[data-field="nama_produk"] {
        min-width: 0 !important;
        max-width: 100%;
        width: 100%;
    }
    #produk-stock-table .produk-inline-stok,
    #produk-stock-table .js-inline-remaining {
        font-weight: 600;
        color: #1a1a1a;
    }
    #produk-stock-table .label {
        font-weight: 700;
    }
    #produk-stock-table .label.label-success {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 5px;
    }
    /* Actions — left side next to checkbox */
    #produk-stock-table thead th.produk-col-actions,
    #produk-stock-table tbody td.produk-col-actions {
        min-width: 142px;
        width: 142px;
        vertical-align: middle;
        text-align: center;
        white-space: nowrap;
        background-color: #fff;
    }
    #produk-stock-table thead th.produk-col-actions {
        background-color: #f4f4f4;
    }
    /* Date Created — right side */
    #produk-stock-table thead th.produk-col-date,
    #produk-stock-table tbody td.produk-col-date {
        min-width: 110px;
        white-space: nowrap;
        vertical-align: middle;
        background-color: #fff;
    }
    #produk-stock-table thead th.produk-col-date {
        background-color: #f4f4f4;
    }
    #produk-stock-table.table-hover tbody tr:hover td.produk-col-actions,
    #produk-stock-table.table-hover tbody tr:hover td.produk-col-date {
        background-color: #f5f5f5;
    }
    #produk-stock-table tbody tr.out-of-stock-row td.produk-col-actions,
    #produk-stock-table tbody tr.out-of-stock-row td.produk-col-date {
        background-color: #fff5f5 !important;
    }
    #produk-stock-table tbody tr.out-of-stock-row:hover td.produk-col-actions,
    #produk-stock-table tbody tr.out-of-stock-row:hover td.produk-col-date {
        background-color: #ffecec !important;
    }

    /* Row the user is currently working on — click row to toggle (not inputs/buttons/links) */
    #produk-stock-table tbody tr.produk-row-active td {
        background-color: #d9edf7 !important;
    }
    #produk-stock-table tbody tr.produk-row-active {
        box-shadow: inset 4px 0 0 #3c8dbc;
    }
    #produk-stock-table tbody tr.produk-row-active.out-of-stock-row td {
        background-color: #eddcdc !important;
    }
    #produk-stock-table.table-hover tbody tr.produk-row-active:hover td {
        background-color: #c4e3f3 !important;
    }
    #produk-stock-table.table-hover tbody tr.produk-row-active.out-of-stock-row:hover td {
        background-color: #e8d4d4 !important;
    }
    #stock-filter-row .select2-container--default .select2-selection--single {
        min-height: 34px;
        padding-top: 2px;
    }
    #stock-filter-row .select2-container {
        width: 100% !important;
    }
    .select2-container--loading .select2-selection--single {
        border-color: #3c8dbc;
    }
    .select2-container--loading .select2-selection__arrow b {
        display: none;
    }
    .select2-container--loading .select2-selection__arrow::after {
        font-family: FontAwesome;
        content: "\f110";
        position: absolute;
        top: 50%;
        right: 4px;
        margin-top: -8px;
        animation: fa-spin 1s infinite linear;
        color: #3c8dbc;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <div class="btn-group">
                    <button onclick="addForm('{{ route('produk.store') }}')" class="btn btn-success btn-flat"><i class="fa fa-plus-circle"></i> Enter stock</button>
                    <button onclick="deleteSelected('{{ route('produk.delete_selected') }}')" class="btn btn-danger btn-flat"><i class="fa fa-trash"></i> Delete</button>
                    <button onclick="cetakBarcode('{{ route('produk.cetak_barcode') }}')" class="btn btn-warning btn-flat"><i class="fa fa-barcode"></i> Print Barcode</button>
                    <a href="{{ route('pembelian.import') }}" class="btn btn-info btn-flat"><i class="fa fa-upload"></i> Import Purchases</a>
                    <button type="button" id="btn-export-stock-list-pdf" class="btn btn-danger btn-flat" title="Export current list (respects search &amp; shop filter)"><i class="fa fa-file-pdf-o"></i> Export PDF</button>
                    {{-- Purchase Order button - Currently Inactive
                    <a href="{{ route('purchase-orders.create') }}" class="btn btn-info btn-flat"><i class="fa fa-plus"></i> Create PO to Restock</a>
                    --}}
                </div>
            </div>
            <div class="box-body table-responsive produk-table-scroll">
                <form action="" method="post" class="form-produk">
                    @csrf
                    {{-- Searchable picker + live filter (same server search as table) --}}
                    <div id="stock-filter-row" class="row" style="margin-bottom: 12px;">
                        <div class="col-md-3">
                            <label for="stockProductQuickFilter">Search by product code or name</label>
                            <select id="stockProductQuickFilter" class="form-control" data-placeholder="Type to filter the list or pick a product…">
                                <option value=""></option>
                            </select>
                            <p class="help-block" style="margin-top:6px;font-size:11px;">Type to filter the list; pick a row to jump to one product.</p>
                        </div>

                        <div class="col-md-3">
                            <label for="shopFilter">Filter by shop</label>
                            <select id="shopFilter" class="form-control">
                                <option value="">All Shops</option>
                                @foreach($shop as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="supplierFilter">Filter by supplier</label>
                            <select id="supplierFilter" class="form-control">
                                <option value="">All Suppliers</option>
                                @foreach($supplier as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3" style="padding-top: 24px;">
                            <button type="button" id="applyStockFilter" class="btn btn-primary" style="width: 48%;">
                                <i class="fa fa-search"></i> Search
                            </button>
                            <button type="button" id="clearStockFilter" class="btn btn-default" style="width: 48%; float: right;">
                                <i class="fa fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                    <div class="row" style="margin-bottom: 12px;">
                        <div class="col-md-3 col-sm-6">
                            <label for="filter_start_date">Date created from</label>
                            <input type="text"
                                   id="filter_start_date"
                                   class="form-control stock-list-datepicker"
                                   placeholder="dd/mm/yyyy"
                                   autocomplete="off">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label for="filter_end_date">Date created to</label>
                            <input type="text"
                                   id="filter_end_date"
                                   class="form-control stock-list-datepicker"
                                   placeholder="dd/mm/yyyy"
                                   autocomplete="off">
                        </div>
                        <div class="col-md-6 col-sm-12">
                            <p class="help-block" style="margin-top: 28px; margin-bottom: 0;">
                                Optional — filters products by <strong>Date Created</strong> (date entered in stock).
                            </p>
                        </div>
                    </div>

                    {{-- Preloader when search is running (like import sales) --}}
                    <div id="stock-search-loading" style="display: none; margin: 15px 0;">
                        <div class="box box-info">
                            <div class="box-body text-center" style="padding: 20px;">
                                <i class="fa fa-refresh fa-spin fa-3x text-info"></i>
                                <p class="lead" style="margin-top: 15px; margin-bottom: 0; font-weight: bold;">Searching...</p>
                                <p class="text-muted" style="margin-top: 5px;">Loading stock results</p>
                            </div>
                        </div>
                    </div>

                    <table id="produk-stock-table" class="table table-stiped table-bordered table-hover table-condensed" title="Tip: click a row to highlight the line you are working on; click again on the row (outside fields) to clear.">
                        <thead>
                            <th width="5%">
                                <input type="checkbox" name="select_all" id="select_all">
                            </th>
                            <th class="produk-col-actions text-center" title="Edit, update stock, delete"><i class="fa fa-cog"></i></th>
                            <th width="5%">#</th>
                            <th>Code</th>
                            <th>Product</th>
                            <th>Shop</th>
                            <th title="Supplier">Supp.</th>
                            <th title="Purchase Price">Buy</th>
                            <th title="Selling Price">Sell</th>
                            <th title="Re-Order Level">Re-Ord</th>
                            <th title="Item-In">In</th>
                            <th title="Items Sold">Sold</th>
                            <th title="Remaining stock">Left</th>
                            <th class="produk-col-date" title="Date Created">Date Created</th>
                        </thead>
                    </table>
                </form>
            </div>
        </div>
    </div>
</div>

@includeIf('produk.form')
@includeIf('produk.update_stock_form')

{{-- Stock movement timeline modal (must exist in DOM for View button) --}}
<div class="modal fade" id="modal-stock-movement" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-list-ul"></i> Stock movement — <span id="stock-movement-product-name">—</span></h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="stock-movement-summary" style="margin-bottom: 12px;"></p>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-bordered table-striped table-condensed" id="stock-movement-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;">Date</th>
                                <th style="width: 70px;">Time</th>
                                <th style="width: 120px;">Type</th>
                                <th>Details</th>
                                <th class="text-right" style="width: 72px;">Change</th>
                                <th class="text-right" style="width: 88px;">Balance</th>
                            </tr>
                        </thead>
                        <tbody id="stock-movement-tbody">
                            <tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="stock-movement-current-stock" class="stock-movement-current-stock text-right" style="margin-top: 12px; margin-bottom: 0; font-size: 15px; font-weight: 600; color: #2d7a3e;"></p>
            </div>
            <div class="modal-footer">
                <a href="#" id="btn-stock-movement-export-pdf" class="btn btn-danger" target="_blank" rel="noopener noreferrer" title="Export timeline to PDF">
                    <i class="fa fa-file-pdf-o"></i> Export PDF
                </a>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@include('produk.partials.merge_duplicate_modal')

{{-- Price change: optional retroactive update of past sales / consignment / cash purchases --}}
<div class="modal fade" id="modal-price-retro" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="btn-price-retro-close-x" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-history"></i> Price change — past records</h4>
            </div>
            <div class="modal-body">
                <p id="price-retro-summary" class="text-muted"></p>
                <hr>
                <p><strong>Apply to completed activity in this date range</strong> (sale date on the receipt):</p>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="price_retro_date_from">From</label>
                            <input type="date" class="form-control" id="price_retro_date_from" name="price_retro_date_from">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="price_retro_date_to">To</label>
                            <input type="date" class="form-control" id="price_retro_date_to" name="price_retro_date_to">
                        </div>
                    </div>
                </div>
                <div class="checkbox">
                    <label><input type="checkbox" id="price_retro_chk_sales" value="1"> Past <strong>sale lines</strong> (selling price → line totals &amp; sale payable)</label>
                </div>
                <div class="checkbox">
                    <label><input type="checkbox" id="price_retro_chk_consignment" value="1"> <strong>Consignment</strong> unpaid lines (buying price × qty)</label>
                </div>
                <div class="checkbox">
                    <label><input type="checkbox" id="price_retro_chk_cash" value="1"> <strong>Cash supplier</strong> unpaid purchase lines (buying price × qty)</label>
                </div>
                <p class="text-warning small" style="margin-top: 10px;"><i class="fa fa-warning"></i> Consignment and cash updates only affect <strong>unpaid</strong> rows. Paid amounts are not changed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="btn-price-retro-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-price-retro-future-only"><i class="fa fa-save"></i> Save price — new sales only</button>
                <button type="button" class="btn btn-success" id="btn-price-retro-apply"><i class="fa fa-check"></i> Save &amp; update past records</button>
            </div>
        </div>
    </div>
</div>

{{-- Supplier change: reassign past sales to new supplier consignment / cash ledger --}}
<div class="modal fade" id="modal-supplier-sales" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="btn-supplier-sales-close-x" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-truck"></i> Supplier change — past sales</h4>
            </div>
            <div class="modal-body">
                <p id="supplier-sales-summary" class="text-muted"></p>
                <p id="supplier-sales-detail"></p>
                <p class="text-warning small" style="margin-top: 10px;"><i class="fa fa-warning"></i> Only <strong>unpaid</strong> consignment and cash-generated lines are moved. Paid supplier lines are left unchanged.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="btn-supplier-sales-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-supplier-sales-skip"><i class="fa fa-save"></i> No — keep sales on previous supplier</button>
                <button type="button" class="btn btn-success" id="btn-supplier-sales-apply"><i class="fa fa-check"></i> Yes — assign all sales to new supplier</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    let table;
    let addModeExistingProductId = null;
    let __priceRetroPending = null;
    let __supplierSalesPending = null;

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function formatSupplierSalesReassignedSummary(counts) {
        if (!counts) {
            return '';
        }
        return 'Past sales reassigned: ' + (counts.sale_lines_processed || 0) + ' sale line(s); ' +
            'consignment removed ' + (counts.consignment_removed || 0) + ', created ' + (counts.consignment_created || 0) + '; ' +
            'cash removed ' + (counts.cash_removed || 0) + ', created ' + (counts.cash_created || 0) +
            ((counts.skipped_paid || 0) > 0 ? '; skipped ' + counts.skipped_paid + ' paid line(s)' : '');
    }

    function handleProdukSaveResponse(response, pending, onSuccess) {
        if (response && response.requires_supplier_sales_choice) {
            openSupplierSalesModal(response, pending);
            return true;
        }
        if (response && response.requires_price_retro_choice) {
            openPriceRetroModal(response, pending);
            return true;
        }
        if (typeof onSuccess === 'function') {
            onSuccess(response);
        }
        return false;
    }

    function openSupplierSalesModal(serverResp, pending) {
        __supplierSalesPending = pending;
        var p = serverResp.preview || {};
        $('#supplier-sales-summary').html(
            'Change supplier from <strong>' + escapeHtml(p.old_supplier_name || 'None') + '</strong> to ' +
            '<strong>' + escapeHtml(p.new_supplier_name || '') + '</strong> (' + escapeHtml(p.new_supplier_mop || '') + ')?'
        );
        var lines = [];
        lines.push('<strong>' + (p.sale_lines || 0) + '</strong> completed sale line(s) for this product.');
        if ((p.consignment_unpaid_lines || 0) > 0) {
            lines.push('<strong>' + p.consignment_unpaid_lines + '</strong> unpaid consignment row(s) can be moved.');
        }
        if ((p.cash_unpaid_lines || 0) > 0) {
            lines.push('<strong>' + p.cash_unpaid_lines + '</strong> unpaid cash-generated row(s) can be moved.');
        }
        if ((p.paid_consignment_lines || 0) + (p.paid_cash_lines || 0) > 0) {
            lines.push('<span class="text-warning">' + (p.paid_consignment_lines || 0) + ' paid consignment and ' +
                (p.paid_cash_lines || 0) + ' paid cash row(s) will stay on the old supplier.</span>');
        }
        $('#supplier-sales-detail').html(lines.join('<br>'));
        $('#modal-supplier-sales').modal('show');
    }

    function clearSupplierSalesPending(revertInline) {
        var p = __supplierSalesPending;
        __supplierSalesPending = null;
        if (revertInline && p && p.$inlineInput && p.$inlineInput.length) {
            p.$inlineInput.val(String(p.$inlineInput.attr('data-original')));
        }
    }

    $(document).on('click', '#btn-supplier-sales-cancel, #btn-supplier-sales-close-x', function () {
        $('#modal-supplier-sales').modal('hide');
        clearSupplierSalesPending(true);
    });

    $(document).on('click', '#btn-supplier-sales-skip', function () {
        if (!__supplierSalesPending) {
            return;
        }
        var p = __supplierSalesPending;
        var data = $.extend({}, p.baseData, { supplier_sales_decision: 'skip' });
        $('#modal-supplier-sales').modal('hide');
        p.submitFn(data);
    });

    $(document).on('click', '#btn-supplier-sales-apply', function () {
        if (!__supplierSalesPending) {
            return;
        }
        var p = __supplierSalesPending;
        var data = $.extend({}, p.baseData, { supplier_sales_decision: 'apply' });
        $('#modal-supplier-sales').modal('hide');
        p.submitFn(data);
    });

    /** Parse JSON error body; if the server appended a second JSON object (terminate-phase failure), use the first object only. */
    function produkParseXhrJson(xhr) {
        if (xhr.responseJSON && typeof xhr.responseJSON === 'object') {
            return xhr.responseJSON;
        }
        var raw = xhr.responseText || '';
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e1) {
            var start = raw.indexOf('{');
            if (start === -1) {
                return null;
            }
            var depth = 0;
            for (var i = start; i < raw.length; i++) {
                var c = raw.charAt(i);
                if (c === '{') {
                    depth++;
                } else if (c === '}') {
                    depth--;
                    if (depth === 0) {
                        try {
                            return JSON.parse(raw.substring(start, i + 1));
                        } catch (e2) {
                            return null;
                        }
                    }
                }
            }
            return null;
        }
    }

    /** Human-readable summary of ProductPriceRetroactiveApplyService counts (avoid raw JSON in alerts). */
    function formatPriceRetroAppliedSummary(c) {
        if (!c || typeof c !== 'object') {
            return '';
        }
        var num = function (x) {
            var v = parseInt(x, 10);
            return isNaN(v) ? 0 : v;
        };
        var lines = [];
        if (num(c.sales_lines) > 0) {
            lines.push('POS sale lines updated: ' + num(c.sales_lines));
        }
        if (num(c.penjualan_recalculated) > 0) {
            lines.push('Sale receipts recalculated: ' + num(c.penjualan_recalculated));
        }
        if (num(c.consignment_items) > 0) {
            lines.push('Consignment invoice lines updated: ' + num(c.consignment_items));
        }
        if (num(c.cash_details) > 0) {
            lines.push('Cash purchase lines updated: ' + num(c.cash_details));
        }
        if (num(c.cash_masters) > 0) {
            lines.push('Cash purchase totals refreshed: ' + num(c.cash_masters));
        }
        if (lines.length === 0) {
            return 'No matching past rows were updated in the selected range (they may already use the new price, or fall outside the filters).';
        }
        return lines.join('\n');
    }

    function handleDuplicateMergeFromXhr(xhr, onMerged) {
        var json = produkParseXhrJson(xhr);
        if (!(xhr && xhr.status === 422 && json && json.merge)) {
            return false;
        }
        var m = json.merge;
        var intro = json.message || 'This item code exists on another product.';
        var detail = 'Merge will keep product #' + m.keep_id + ' (' + (m.keep_name || '') + ') and remove incomplete #' + m.remove_id
            + '. All past sales and remaining stock move to the kept product.';
        if (!confirm(intro + '\n\n' + detail + '\n\nProceed with merge?')) {
            return true;
        }
        $.ajax({
            url: '{{ route('produk.merge_incomplete_duplicate') }}',
            type: 'POST',
            dataType: 'json',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                keep_id: m.keep_id,
                remove_id: m.remove_id
            }
        }).done(function (resp) {
            if (typeof onMerged === 'function') {
                onMerged(resp);
                return;
            }
            table.ajax.reload(null, false);
            updateIncompleteCount();
            if (typeof updateIncompleteProductsCount === 'function') {
                updateIncompleteProductsCount();
            }
            alert((resp && resp.message) ? resp.message : 'Merged successfully.');
        }).fail(function (x2) {
            var err = 'Merge failed.';
            if (x2.responseJSON && x2.responseJSON.message) {
                err = x2.responseJSON.message;
            } else if (x2.responseText) {
                try {
                    var p = JSON.parse(x2.responseText);
                    if (p && p.message) err = p.message;
                } catch (e2) {}
            }
            alert(err);
        });
        return true;
    }

    function priceRetroDefaultDates() {
        var now = new Date();
        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        var fd = new Date(now.getFullYear(), now.getMonth(), 1);
        var fromStr = fd.getFullYear() + '-' + pad(fd.getMonth() + 1) + '-' + pad(fd.getDate());
        var toStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
        $('#price_retro_date_from').val(fromStr);
        $('#price_retro_date_to').val(toStr);
    }

    function openPriceRetroModal(serverResp, pending) {
        __priceRetroPending = pending;
        var parts = [];
        if (serverResp.beli_changed) {
            parts.push('Buying price: ' + serverResp.old_harga_beli + ' → <strong>' + serverResp.new_harga_beli + '</strong>');
        }
        if (serverResp.jual_changed) {
            parts.push('Selling price: ' + serverResp.old_harga_jual + ' → <strong>' + serverResp.new_harga_jual + '</strong>');
        }
        $('#price-retro-summary').html(parts.join('<br>'));
        $('#price_retro_chk_sales').prop('checked', !!serverResp.jual_changed).prop('disabled', !serverResp.jual_changed);
        $('#price_retro_chk_consignment').prop('checked', !!serverResp.beli_changed).prop('disabled', !serverResp.beli_changed);
        $('#price_retro_chk_cash').prop('checked', !!serverResp.beli_changed).prop('disabled', !serverResp.beli_changed);
        priceRetroDefaultDates();
        $('#modal-price-retro').modal('show');
    }

    function clearPriceRetroPending(revertInline) {
        var p = __priceRetroPending;
        __priceRetroPending = null;
        if (revertInline && p && p.$inlineInput && p.$inlineInput.length) {
            p.$inlineInput.val(String(p.$inlineInput.attr('data-original')));
        }
    }

    $(document).on('click', '#btn-price-retro-cancel, #btn-price-retro-close-x', function () {
        $('#modal-price-retro').modal('hide');
        clearPriceRetroPending(true);
    });

    $(document).on('click', '#btn-price-retro-future-only', function () {
        if (!__priceRetroPending) {
            return;
        }
        $('#btn-price-retro-future-only, #btn-price-retro-apply').prop('disabled', true);
        var p = __priceRetroPending;
        var data = $.extend({}, p.baseData, { price_retro_decision: 'skip' });
        $('#modal-price-retro').modal('hide');
        try {
            p.submitFn(data);
        } finally {
            $('#btn-price-retro-future-only, #btn-price-retro-apply').prop('disabled', false);
        }
    });

    $(document).on('click', '#btn-price-retro-apply', function () {
        if (!__priceRetroPending) {
            return;
        }
        var from = $('#price_retro_date_from').val();
        var to = $('#price_retro_date_to').val();
        if (!from || !to) {
            alert('Please choose both start and end dates.');
            return;
        }
        var sales = $('#price_retro_chk_sales').is(':checked');
        var cons = $('#price_retro_chk_consignment').is(':checked');
        var cash = $('#price_retro_chk_cash').is(':checked');
        if (!sales && !cons && !cash) {
            alert('Select at least one: past sale lines, consignment, or cash purchases.');
            return;
        }
        var p = __priceRetroPending;
        var data = $.extend({}, p.baseData, {
            price_retro_decision: 'apply',
            retro_date_from: from,
            retro_date_to: to,
            retro_update_sales: sales ? 1 : 0,
            retro_update_consignment: cons ? 1 : 0,
            retro_update_cash_pembelian: cash ? 1 : 0
        });
        $('#btn-price-retro-future-only, #btn-price-retro-apply').prop('disabled', true);
        try {
            p.submitFn(data);
        } finally {
            $('#btn-price-retro-future-only, #btn-price-retro-apply').prop('disabled', false);
        }
    });

    /** Mode of payment: show and submit in UPPERCASE */
    function upperMop(v) {
        if (v === null || typeof v === 'undefined') {
            return '';
        }
        return String(v).toUpperCase();
    }

    /** Re-order level: editable in Edit Stock only; Add Stock shows value but does not allow changes. */
    function setProdukModalReorderReadonly(locked) {
        var $r = $('#reorder');
        if (locked) {
            $r.prop('readonly', true).addClass('reorder-addstock-locked');
        } else {
            $r.prop('readonly', false).removeClass('reorder-addstock-locked');
        }
    }

    function restoreStokRequiredIfAddPost() {
        if (($('#modal-form [name=_method]').val() || '').toLowerCase() === 'post') {
            $('#stok').prop('required', true).attr('required', 'required');
        }
    }

    function resetAddStockDualFieldUi() {
        $('#label-stok-field').text('Stock');
        $('#hint-stok-existing').hide();
        $('#stok').attr('name', 'stok').prop('readonly', false).prop('disabled', false);
        $('#stock_to_add').val('').prop('disabled', true).removeAttr('required').prop('required', false);
        $('#add-stock-to-add-wrap').hide();
        $('#add-mode-badge').hide();
        addModeExistingProductId = null;
        $('#existing_id_produk').val('');
        if (($('#modal-form [name=_method]').val() || '').toLowerCase() === 'post') {
            $('#reorder').val('0');
            setProdukModalReorderReadonly(true);
        }
    }

    /** After a successful Add Stock save: keep modal open; clear all fields except the date (keyboard-editable native date input). */
    function prepareAddStockFormForNextEntry() {
        var dateIn = $('#add_stock_date_in').val();
        if (!dateIn) {
            dateIn = new Date().toISOString().split('T')[0];
        }

        resetAddStockDualFieldUi();

        $('#item_code').val('');
        $('#nama_produk').val('');
        $('#harga_beli').val('');
        $('#harga_jual').val('');
        $('#stok').val('');
        $('#stock_to_add').val('');
        $('#existing_id_produk').val('');
        if ($('#item_code_suggestions').length) {
            $('#item_code_suggestions').empty();
        }
        $('#item-code-match-wrap').hide();
        $('#item_code_match_select').empty().append('<option value="">Select matching item</option>');

        $('#id_kategori').val('1');
        $('#shop_id').val('');
        $('#id_supplier').val(null).trigger('change');
        $('#mop').val('');
        $('#add_stock_date_in').val(dateIn);
        $('#reorder').val('0');
        setProdukModalReorderReadonly(true);

        $('#stok').prop('required', true).attr('required', 'required');
        $('#modal-form .has-error').removeClass('has-error');
        $('#modal-form .has-danger').removeClass('has-danger');
        $('#modal-form .help-block.with-errors').text('');

        setTimeout(function () {
            $('#item_code').trigger('focus');
        }, 0);
    }

    function applyAddStockExistingUi(match) {
        if (!match || !match.id_produk) {
            return;
        }
        var incomingId = parseInt(match.id_produk, 10);
        if (!incomingId) {
            return;
        }
        if (addModeExistingProductId === incomingId && $('#add-stock-to-add-wrap').is(':visible')) {
            return;
        }

        addModeExistingProductId = incomingId;
        $('#existing_id_produk').val(String(incomingId));
        var storeUrl = $('#modal-form').data('produk-store-url');
        if (storeUrl) {
            $('#modal-form form').attr('action', storeUrl);
        }
        $('#modal-form [name=_method]').val('post');

        $('#modal-form .modal-title').text('Add Stock');
        $('#add-mode-badge').show().text('Existing in this shop');

        $('#modal-form [name=shop_id]').val(match.shop_id || '');
        $('#modal-form [name=id_supplier]').val(match.id_supplier || '').trigger('change');
        $('#modal-form [name=mop]').val(upperMop(match.mop || ''));
        $('#modal-form [name=item_code]').val(match.item_code || match.kode_produk || '');
        $('#modal-form [name=nama_produk]').val(match.nama_produk || '');
        $('#modal-form [name=harga_beli]').val(match.harga_beli != null ? match.harga_beli : 0);
        $('#modal-form [name=harga_jual]').val(match.harga_jual != null ? match.harga_jual : 0);
        $('#reorder').val(match.reorder != null && match.reorder !== '' ? match.reorder : 0);
        setProdukModalReorderReadonly(true);

        $('#label-stok-field').text('Current stock (read-only)');
        $('#hint-stok-existing').show();
        $('#stok').val(match.stok != null ? match.stok : 0)
            .prop('readonly', true)
            .removeAttr('name')
            .removeAttr('required')
            .prop('required', false);

        $('#add-stock-to-add-wrap').show();
        $('#stock_to_add').prop('disabled', false).val('').prop('required', true).attr('required', 'required');
    }

    function lookupExistingForAddMode() {
        if (!$('#modal-form').is(':visible')) return;
        if (($('#modal-form [name=_method]').val() || '').toLowerCase() !== 'post') return;

        var itemCode = ($('#item_code').val() || '').trim();
        var selectedShopId = parseInt($('#shop_id').val(), 10) || 0;

        if (itemCode.length < 2 || selectedShopId <= 0) {
            resetAddStockDualFieldUi();
            restoreStokRequiredIfAddPost();
            return;
        }

        $.get('{{ route('produk.quick_lookup') }}', {
            item_code: itemCode,
            shop_id: selectedShopId
        }).done(function(resp) {
            if (!resp || !resp.found) {
                resetAddStockDualFieldUi();
                restoreStokRequiredIfAddPost();
                return;
            }

            var exactShop = null;
            if (Array.isArray(resp.matches) && resp.matches.length) {
                exactShop = resp.matches.find(function(m) {
                    return parseInt(m.shop_id, 10) === selectedShopId;
                });
            }

            if (!exactShop) {
                resetAddStockDualFieldUi();
                restoreStokRequiredIfAddPost();
                return;
            }

            applyAddStockExistingUi(exactShop);
        });
    }


    // Function to update incomplete products count in sidebar
    function updateIncompleteCount() {
        $.get('{{ route('produk.incomplete.count') }}')
            .done(function(response) {
                const count = response.count || 0;
                const container = $('#incomplete-count-container');
                const badge = $('#incomplete-count-badge');
                
                if (count > 0) {
                    if (badge.length) {
                        badge.text(count);
                    } else {
                        container.html('<small class="label label-warning" id="incomplete-count-badge">' + count + '</small>');
                    }
                } else {
                    container.empty();
                }
            })
            .fail(function() {
                // Silently fail - don't disrupt user experience
                console.log('Failed to update incomplete count');
            });
    }

   $(function () {
        table = $('#produk-stock-table').DataTable({
            /* responsive mode hides columns on narrow viewports — actions column must stay usable */
            responsive: false,
            processing: true,
            serverSide: true,
            autoWidth: false,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            ajax: {
                url: '{{ route('produk.data') }}',
                data: function (d) {
                    // Search by product code or name is sent as d.search[value] when using table.search(term).draw()
                    // Add custom dropdown filters here.
                    d.shop_id = $('#shopFilter').val();
                    d.supplier_id = $('#supplierFilter').val();
                    d.start_date = ($('#filter_start_date').val() || '').trim();
                    d.end_date = ($('#filter_end_date').val() || '').trim();
                    var mid = ($('#stockProductQuickFilter').val() || '').trim();
                    if (mid) {
                        d.match_produk_id = mid;
                    }
                },
                error: function (xhr, textStatus, err) {
                    console.error('Stock list load failed:', xhr.status, textStatus, err, xhr.responseText);
                }
            },
            order: [[13, 'desc']],
            columns: [
                {data: 'select_all', searchable: false, sortable: false},
                {data: 'aksi', searchable: false, sortable: false},
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'item_code'},
                {data: 'nama_produk'},
                {data: 'shop_name'},
                {data: 'supplier_name'},
                /* Avoid global search on computed / numeric columns — breaks MySQL WHERE on aliases */
                {data: 'harga_beli', searchable: false},
                {data: 'harga_jual', searchable: false},
                {data: 'reorder_level', searchable: false},
                {data: 'item_in', searchable: false},
                {data: 'items_sold', searchable: false},
                {data: 'remaining', searchable: false},
                {data: 'created_at', searchable: false, sortable: true},
            ],
            columnDefs: [
                { targets: 1, className: 'produk-col-actions' },
                { targets: -1, className: 'produk-col-date text-nowrap' },
                { targets: 0, width: '2.2%', className: 'text-center' },
                { targets: 2, width: '2.5%', className: 'text-center' },
                { targets: 3, width: '7%' },
                { targets: 4, width: '18%' },
                { targets: 5, width: '8%' },
                { targets: 6, width: '8%' },
                { targets: 7, width: '6.5%' },
                { targets: 8, width: '6.5%' },
                { targets: 9, width: '5%' },
                { targets: 10, width: '5%' },
                { targets: 11, width: '5%' },
                { targets: 12, width: '5%' },
                { targets: 13, width: '7%' }
            ],
            rowCallback: function(row, data) {
                var st = parseFloat(data.stok_raw != null ? data.stok_raw : data.stok);
                if (!isNaN(st) && st <= 0) {
                    $(row).addClass('out-of-stock-row');
                }
            }
        });

        // Highlight row you are working on (toggle on row click; ignore form controls & actions)
        $('#produk-stock-table tbody').on('click', 'tr', function (e) {
            var $t = $(e.target);
            if ($t.closest('button, a, input, select, textarea, label, .produk-col-actions').length) {
                return;
            }
            var $tr = $(this);
            if ($tr.hasClass('produk-row-active')) {
                $tr.removeClass('produk-row-active');
                return;
            }
            $('#produk-stock-table tbody tr').removeClass('produk-row-active');
            $tr.addClass('produk-row-active');
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#produk-stock-table').length) {
                $('#produk-stock-table tbody tr.produk-row-active').removeClass('produk-row-active');
            }
        });

        // Quick product search: Select2 suggestions + live table filter while typing in the dropdown search field
        var stockLiveSearchTimer = null;
        if ($('#stockProductQuickFilter').length) {
            $('#stockProductQuickFilter').select2({
                placeholder: $('#stockProductQuickFilter').data('placeholder') || 'Type code or name…',
                allowClear: true,
                width: '100%',
                minimumInputLength: 1,
                dropdownParent: $('body'),
                language: {
                    inputTooShort: function () { return 'Type to search…'; },
                    searching: function () { return 'Searching…'; }
                },
                ajax: {
                    url: '{{ route('produk.stock_quick_search') }}',
                    dataType: 'json',
                    delay: 280,
                    transport: function (params, success, failure) {
                        var $request = $.ajax(params);
                        $('#stockProductQuickFilter').next('.select2-container').addClass('select2-container--loading');
                        $request.then(success);
                        $request.fail(failure);
                        $request.always(function () {
                            $('#stockProductQuickFilter').next('.select2-container').removeClass('select2-container--loading');
                        });
                        return $request;
                    },
                    data: function (params) {
                        return {
                            q: params.term || '',
                            shop_id: $('#shopFilter').val() || ''
                        };
                    },
                    processResults: function (data) {
                        return data;
                    },
                    cache: true
                }
            });
            $('#stockProductQuickFilter').on('select2:open', function () {
                setTimeout(function () {
                    var $field = $('.select2-container--open .select2-search__field');
                    $field.off('input.stockLiveFilter').on('input.stockLiveFilter', function () {
                        clearTimeout(stockLiveSearchTimer);
                        var term = ($field.val() || '').trim();
                        stockLiveSearchTimer = setTimeout(function () {
                            if (($('#stockProductQuickFilter').val() || '').trim() !== '') {
                                return;
                            }
                            showStockSearchLoading();
                            table.search(term).draw();
                        }, 320);
                    });
                }, 0);
            });
            $('#stockProductQuickFilter').on('select2:select', function () {
                clearTimeout(stockLiveSearchTimer);
                table.search('');
                showStockSearchLoading();
                table.draw();
            });
            $('#stockProductQuickFilter').on('select2:clear', function () {
                clearTimeout(stockLiveSearchTimer);
                table.search('');
                showStockSearchLoading();
                table.draw();
            });
        }

        // PDF export — same filters as table (search box + shop dropdown)
        $('#btn-export-stock-list-pdf').on('click', function () {
            var mid = ($('#stockProductQuickFilter').val() || '').trim();
            var q = '';
            if (!mid && typeof table !== 'undefined' && typeof table.search === 'function') {
                q = (table.search() || '').toString().trim();
            }
            var shopId = $('#shopFilter').val() || '';
            var supplierId = $('#supplierFilter').val() || '';
            var startDate = ($('#filter_start_date').val() || '').trim();
            var endDate = ($('#filter_end_date').val() || '').trim();
            var params = [];
            if (mid) {
                params.push('match_produk_id=' + encodeURIComponent(mid));
            } else if (q) {
                params.push('q=' + encodeURIComponent(q));
            }
            if (shopId) {
                params.push('shop_id=' + encodeURIComponent(shopId));
            }
            if (supplierId) {
                params.push('supplier_id=' + encodeURIComponent(supplierId));
            }
            if (startDate) {
                params.push('start_date=' + encodeURIComponent(startDate));
            }
            if (endDate) {
                params.push('end_date=' + encodeURIComponent(endDate));
            }
            var url = '{{ route('produk.export-stock-list-pdf') }}' + (params.length ? '?' + params.join('&') : '');
            window.open(url, '_blank');
        });

        // Inline Remaining: save on blur (same idea as Stock History / Updated Items)
        $(document).on('blur', '.js-inline-remaining', function () {
            var $input = $(this);
            if ($input.prop('disabled')) {
                return;
            }
            var url = $input.data('update-url');
            var orig = parseInt($input.attr('data-original'), 10);
            if (isNaN(orig)) {
                orig = 0;
            }
            var raw = $.trim(String($input.val() || ''));
            var n = (raw === '' || raw === '-') ? NaN : parseInt(raw, 10);
            if (isNaN(n)) {
                $input.val(String(orig));
                return;
            }
            if (n === orig) {
                $input.val(String(n));
                return;
            }
            if (!url) {
                return;
            }
            $input.prop('disabled', true);
            var today = new Date().toISOString().split('T')[0];
            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    _method: 'PUT',
                    stok: n,
                    date_in: today,
                    inline_stock_only: 1
                }
            }).done(function () {
                $input.attr('data-original', String(n));
                $input.val(String(n));
                table.ajax.reload(null, false);
                updateIncompleteCount();
                if (typeof updateIncompleteProductsCount === 'function') {
                    updateIncompleteProductsCount();
                }
            }).fail(function (xhr) {
                var msg = 'Unable to update stock.';
                if (xhr.responseJSON) {
                    if (typeof xhr.responseJSON === 'string') {
                        msg = xhr.responseJSON;
                    } else if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                } else if (xhr.responseText && xhr.responseText.length < 500) {
                    try {
                        var p = JSON.parse(xhr.responseText);
                        msg = typeof p === 'string' ? p : (p.message || msg);
                    } catch (e) {
                        msg = xhr.responseText;
                    }
                }
                alert(msg);
                $input.val(String(orig));
            }).always(function () {
                $input.prop('disabled', false);
            });
        });

        // Inline product fields (name, shop, supplier, prices, reorder, item-in) — save on blur / change
        function submitInlineProdukField($el) {
            var url = $el.data('update-url');
            if (!url || $el.prop('disabled')) {
                return;
            }
            var field = $el.data('field');
            var orig = $el.attr('data-original');
            var val = $el.is('select') ? $el.val() : $.trim(String($el.val() || ''));
            if (field === 'harga_beli' || field === 'harga_jual') {
                if (Math.abs((parseFloat(val) || 0) - (parseFloat(orig) || 0)) < 0.0001) {
                    return;
                }
            } else if (field === 'reorder_level' || $el.data('inline-item-in')) {
                if (parseInt(val, 10) === parseInt(orig, 10)) {
                    return;
                }
            } else if (String(val) === String(orig)) {
                return;
            }
            if (field === 'nama_produk' && val === '') {
                alert('Product name cannot be empty.');
                $el.val(orig);
                return;
            }
            var data = {
                _token: $('meta[name="csrf-token"]').attr('content'),
                _method: 'PUT',
                inline_fields_only: 1
            };
            if ($el.data('inline-item-in')) {
                data.inline_item_in = 1;
                data.item_in = parseInt(val, 10);
                if (isNaN(data.item_in)) {
                    data.item_in = 0;
                }
            } else if (field === 'harga_beli' || field === 'harga_jual') {
                data[field] = parseFloat(val) || 0;
            } else if (field === 'reorder_level') {
                data[field] = parseInt(val, 10) || 0;
            } else if (field === 'shop_id' || field === 'id_supplier') {
                data[field] = parseInt(val, 10);
            } else {
                data[field] = val;
            }
            $el.prop('disabled', true);
            function parseInlineSaveError(xhr) {
                var msg = 'Unable to save.';
                if (xhr.responseJSON) {
                    if (typeof xhr.responseJSON === 'string') {
                        msg = xhr.responseJSON;
                    } else if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                } else if (xhr.responseText && xhr.responseText.length < 500) {
                    try {
                        var p = JSON.parse(xhr.responseText);
                        msg = typeof p === 'string' ? p : (p.message || msg);
                    } catch (e) {
                        msg = xhr.responseText;
                    }
                }
                return msg;
            }
            function finishInlineSave(r) {
                __priceRetroPending = null;
                __supplierSalesPending = null;
                $('#modal-price-retro').modal('hide');
                $('#modal-supplier-sales').modal('hide');
                if ($el.is('select')) {
                    $el.attr('data-original', String($el.val()));
                } else {
                    $el.attr('data-original', String($el.val()));
                }
                var msg = (r && r.message) ? r.message : 'Saved.';
                if (r && r.supplier_sales_reassigned) {
                    msg += '\n\n' + formatSupplierSalesReassignedSummary(r.supplier_sales_reassigned);
                }
                if (r && r.price_retro_applied) {
                    msg += '\n\n' + formatPriceRetroAppliedSummary(r.price_retro_applied);
                }
                if ((r && r.supplier_sales_reassigned) || (r && r.price_retro_applied)) {
                    alert(msg);
                }
                table.ajax.reload(null, false);
                updateIncompleteCount();
                if (typeof updateIncompleteProductsCount === 'function') {
                    updateIncompleteProductsCount();
                }
            }
            function inlineSubmitFn(payload) {
                $el.prop('disabled', true);
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: payload,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).done(function (r2) {
                    if (handleProdukSaveResponse(r2, {
                        baseData: payload,
                        $inlineInput: $el,
                        submitFn: inlineSubmitFn
                    }, finishInlineSave)) {
                        $el.prop('disabled', false);
                        return;
                    }
                    finishInlineSave(r2);
                }).fail(function (xhr) {
                    if (handleDuplicateMergeFromXhr(xhr, function (resp) {
                        finishInlineSave(resp || {});
                    })) {
                        return;
                    }
                    alert(parseInlineSaveError(xhr));
                    if ($el.is('select')) {
                        $el.val(String(orig));
                    } else {
                        $el.val(orig);
                    }
                }).always(function () {
                    $el.prop('disabled', false);
                });
            }
            inlineSubmitFn(data);
        }

        $(document).on('blur', 'input.js-inline-produk', function () {
            submitInlineProdukField($(this));
        });
        $(document).on('change', 'select.js-inline-produk', function () {
            submitInlineProdukField($(this));
        });

        // Preloader when search runs (like import sales)
        var searchInProgress = false;
        function showStockSearchLoading() {
            searchInProgress = true;
            $('#stock-search-loading').show();
            var $btn = $('#applyStockFilter');
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Searching...');
            $('#clearStockFilter').prop('disabled', true);
        }
        function hideStockSearchLoading() {
            if (!searchInProgress) return;
            searchInProgress = false;
            $('#stock-search-loading').hide();
            $('#applyStockFilter').prop('disabled', false).html('<i class="fa fa-search"></i> Search');
            $('#clearStockFilter').prop('disabled', false);
        }
        table.on('preXhr.dt', function () {
            showStockSearchLoading();
        });
        table.on('xhr.dt error.dt', function () {
            hideStockSearchLoading();
        });

        // Update Stock modal (green +): data-* attributes for product names with special characters
        $(document).on('click', '.btn-open-update-stock', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var url = $btn.attr('data-restock-url');
            var idProduk = parseInt($btn.attr('data-produk-id'), 10);
            var productName = $btn.attr('data-product-name') || '';
            var currentStock = parseInt($btn.attr('data-current-stock'), 10);
            if (isNaN(currentStock)) {
                currentStock = 0;
            }
            if (url && idProduk) {
                updateStockForm(url, idProduk, productName, currentStock);
            }
        });

        // Search / refresh table (uses current text search and/or selected product + shop)
        $('#applyStockFilter').on('click', function () {
            var mid = ($('#stockProductQuickFilter').val() || '').trim();
            if (!mid) {
                var $openField = $('.select2-container--open .select2-search__field');
                if ($openField.length) {
                    table.search(($openField.val() || '').trim());
                }
            }
            showStockSearchLoading();
            table.draw();
        });
        $('#clearStockFilter').on('click', function () {
            $('#stockProductQuickFilter').val(null).trigger('change');
            $('#shopFilter').val('').trigger('change');
            $('#supplierFilter').val('').trigger('change');
            $('#filter_start_date').val('');
            $('#filter_end_date').val('');
            showStockSearchLoading();
            table.search('').draw();
        });

        if ($.fn.datepicker) {
            $('.stock-list-datepicker').datepicker({
                format: 'dd/mm/yyyy',
                autoclose: true,
                todayHighlight: true
            });
        }

        // Enable searchable dropdown for shops.
        if ($('#shopFilter').length && !$('#shopFilter').hasClass('select2-hidden-accessible')) {
            $('#shopFilter').select2({
                placeholder: 'All Shops',
                width: '100%',
                allowClear: true
            });
        }
        if ($('#supplierFilter').length && !$('#supplierFilter').hasClass('select2-hidden-accessible')) {
            $('#supplierFilter').select2({
                placeholder: 'All Suppliers',
                width: '100%',
                allowClear: true
            });
        }

        // Destroy Select2 when modal is hidden
        $('#modal-form').on('hidden.bs.modal', function () {
            if ($('#id_supplier').hasClass('select2-hidden-accessible')) {
                $('#id_supplier').select2('destroy');
            }
        });

        // Reset modal: Add Stock = fresh form; Edit = reload saved product from server
        $(document).on('click', '#btn_modal_clear_produk', function (e) {
            e.preventDefault();
            var method = ($('#modal-form [name=_method]').val() || '').toLowerCase();
            var action = $('#modal-form form').attr('action');
            if (!action) {
                return;
            }
            $('#modal-form .has-error').removeClass('has-error');
            $('#modal-form .has-danger').removeClass('has-danger');
            $('#modal-form .help-block.with-errors').text('');
            if (method === 'post') {
                var storeUrl = $('#modal-form').data('produk-store-url') || action;
                addForm(storeUrl);
                return;
            }
            if (method === 'put') {
                editForm(action);
            }
        });

        function handleModalProdukSaved(r2, isAdd) {
            __priceRetroPending = null;
            __supplierSalesPending = null;
            $('#modal-price-retro').modal('hide');
            $('#modal-supplier-sales').modal('hide');
            if (r2 && typeof r2 === 'object' && r2.redirect_url) {
                window.location.href = r2.redirect_url;
                return;
            }
            table.ajax.reload(null, false);
            updateIncompleteCount();
            if (typeof updateIncompleteProductsCount === 'function') {
                updateIncompleteProductsCount();
            }
            var msg = (r2 && r2.message) ? r2.message : 'Saved.';
            if (r2 && r2.supplier_sales_reassigned) {
                msg += '\n\n' + formatSupplierSalesReassignedSummary(r2.supplier_sales_reassigned);
            }
            if (r2 && r2.price_retro_applied) {
                msg += '\n\n' + formatPriceRetroAppliedSummary(r2.price_retro_applied);
            }
            if ((r2 && r2.supplier_sales_reassigned) || (r2 && r2.price_retro_applied)) {
                alert(msg);
            }
            if (isAdd) {
                prepareAddStockFormForNextEntry();
            } else {
                $('#modal-form').modal('hide');
            }
        }

        $('#modal-form').validator().on('submit', function (e) {
            if (! e.preventDefault()) {
                $.ajax({
                    url: $('#modal-form form').attr('action'),
                    type: 'POST',
                    data: $('#modal-form form').serialize(),
                    dataType: 'json',
                    success: function(response) {
                        var isAdd = (($('#modal-form [name=_method]').val() || '').toLowerCase() === 'post');

                        if (response && typeof response === 'object' && response.redirect_url) {
                            window.location.href = response.redirect_url;
                            return;
                        }

                        var baseObj = {};
                        var arr = $('#modal-form form').serializeArray();
                        for (var i = 0; i < arr.length; i++) {
                            baseObj[arr[i].name] = arr[i].value;
                        }
                        function modalSubmitFn(d) {
                            $.ajax({
                                url: $('#modal-form form').attr('action'),
                                type: 'POST',
                                data: d,
                                dataType: 'json',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                }
                            }).done(function (r2) {
                                if (handleProdukSaveResponse(r2, {
                                    baseData: d,
                                    $inlineInput: null,
                                    submitFn: modalSubmitFn
                                }, function (saved) {
                                    handleModalProdukSaved(saved, isAdd);
                                })) {
                                    return;
                                }
                                handleModalProdukSaved(r2, isAdd);
                            }).fail(function (xhr) {
                                if (handleDuplicateMergeFromXhr(xhr, function (resp) {
                                    handleModalProdukSaved(resp || {}, isAdd);
                                })) {
                                    return;
                                }
                                var err = 'Unable to save product. Please try again.';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    err = xhr.responseJSON.message;
                                }
                                alert(err);
                            });
                        }
                        if (handleProdukSaveResponse(response, {
                            baseData: baseObj,
                            $inlineInput: null,
                            submitFn: modalSubmitFn
                        }, function (saved) {
                            handleModalProdukSaved(saved, isAdd);
                        })) {
                            return;
                        }
                        handleModalProdukSaved(response, isAdd);
                    },
                    error: function(xhr) {
                        let json = produkParseXhrJson(xhr);

                        if (xhr.status === 422 && json && json.merge) {
                            let m = json.merge;
                            let intro = json.message || 'This item code exists on another product.';
                            let detail = 'Merge will keep product #' + m.keep_id + ' (' + (m.keep_name || '') + ') and remove incomplete #' + m.remove_id
                                + '. All past sales and remaining stock move to the kept product.';
                            if (!confirm(intro + '\n\n' + detail + '\n\nProceed with merge?')) {
                                return;
                            }
                            $.ajax({
                                url: '{{ route('produk.merge_incomplete_duplicate') }}',
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    _token: $('meta[name="csrf-token"]').attr('content'),
                                    keep_id: m.keep_id,
                                    remove_id: m.remove_id
                                }
                            }).done(function(resp) {
                                $('#modal-form').modal('hide');
                                table.ajax.reload(null, false);
                                updateIncompleteCount();
                                if (typeof updateIncompleteProductsCount === 'function') {
                                    updateIncompleteProductsCount();
                                }
                                alert((resp && resp.message) ? resp.message : 'Merged successfully.');
                            }).fail(function(x2) {
                                let err = 'Merge failed.';
                                if (x2.responseJSON && x2.responseJSON.message) {
                                    err = x2.responseJSON.message;
                                } else if (x2.responseText) {
                                    try {
                                        let p = JSON.parse(x2.responseText);
                                        if (p && p.message) err = p.message;
                                    } catch (e2) {}
                                }
                                alert(err);
                            });
                            return;
                        }

                        let errorMessage = 'Unable to save product. Please try again.';
                        
                        if (json) {
                            if (typeof json === 'string') {
                                errorMessage = json;
                            } else if (json.message) {
                                errorMessage = json.message;
                            } else if (json.errors) {
                                let errors = [];
                                for (let field in json.errors) {
                                    errors = errors.concat(json.errors[field]);
                                }
                                errorMessage = errors.join(' ');
                            } else {
                                errorMessage = JSON.stringify(json);
                            }
                        } else if (xhr.responseText) {
                            try {
                                let parsed = JSON.parse(xhr.responseText);
                                errorMessage = parsed.message || parsed;
                            } catch (e) {
                                if (xhr.responseText.trim().length > 0 && xhr.responseText.trim().length < 500) {
                                    errorMessage = xhr.responseText.trim();
                                }
                            }
                        }
                        
                        alert(errorMessage);
                        console.error('Error details:', xhr.responseText);
                    }
                });
            }
        });

        $('[name=select_all]').on('click', function () {
            $(':checkbox').prop('checked', this.checked);
        });

        // Handle supplier selection change to autofill MOP
        // Works with both regular select and Select2
        $(document).on('change', '#id_supplier', function() {
            var supplierId = $(this).val();
            if (supplierId) {
                // Fetch supplier details
                $.get('{{ url("/supplier") }}/' + supplierId)
                    .done(function(response) {
                        if (response && response.mop) {
                            $('#mop').val(upperMop(response.mop));
                        } else {
                            $('#mop').val('');
                        }
                    })
                    .fail(function() {
                        $('#mop').val('');
                        console.log('Failed to fetch supplier details');
                    });
            } else {
                $('#mop').val('');
            }
        });

        // Also handle Select2 change event
        $(document).on('select2:select', '#id_supplier', function() {
            var supplierId = $(this).val();
            if (supplierId) {
                // Fetch supplier details
                $.get('{{ url("/supplier") }}/' + supplierId)
                    .done(function(response) {
                        if (response && response.mop) {
                            $('#mop').val(upperMop(response.mop));
                        } else {
                            $('#mop').val('');
                        }
                    })
                    .fail(function() {
                        $('#mop').val('');
                        console.log('Failed to fetch supplier details');
                    });
            } else {
                $('#mop').val('');
            }
        });

        $(document).on('blur', '#item_code', lookupExistingForAddMode);
        $(document).on('change', '#shop_id', lookupExistingForAddMode);
    });

    function addForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Add Stock');
        $('#modal-form').data('produk-store-url', url);

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('post');

        resetAddStockDualFieldUi();

        // Stock required when adding (new product path); run after native reset clears fields
        $('#stok').prop('required', true).attr('required', 'required');
        
        // Set category to default value 1
        $('#id_kategori').val('1');
        
        // Clear MOP field
        $('#mop').val('');
        
        // Ensure item_code field is editable
        $('#item_code').prop('readonly', false).prop('disabled', false).removeAttr('readonly').removeAttr('disabled');
        
        // Initialize Select2 for supplier dropdown
        if ($('#id_supplier').hasClass('select2-hidden-accessible')) {
            $('#id_supplier').select2('destroy');
        }
        $('#id_supplier').select2({
            placeholder: 'Select Supplier',
            width: '100%',
            dropdownParent: ($('#modal-form .modal-content').length ? $('#modal-form .modal-content') : $('#modal-form'))
        });

        $('#add_stock_date_in').val(new Date().toISOString().split('T')[0]);

        $('#incomplete-auto-match-banner').hide();
        $('#quickadd-qty-sold-wrap').hide();
        $('#label-stok-field').text('Stock');
        setProdukModalReorderReadonly(true);

        $('#modal-form [name=nama_produk]').focus();
    }

    function editForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Edit Stock');
        $('#add-mode-badge').hide();

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('put');
        resetAddStockDualFieldUi();
        setProdukModalReorderReadonly(false);

        $('#stok').removeAttr('required').prop('required', false);
        $('#stok').closest('.form-group').find('.help-block.with-errors').text('');

        $('#id_kategori').val('1');

        $('#item_code').prop('readonly', false).prop('disabled', false).removeAttr('readonly').removeAttr('disabled');

        if ($('#id_supplier').hasClass('select2-hidden-accessible')) {
            $('#id_supplier').select2('destroy');
        }
        $('#id_supplier').select2({
            placeholder: 'Select Supplier',
            width: '100%',
            dropdownParent: ($('#modal-form .modal-content').length ? $('#modal-form .modal-content') : $('#modal-form'))
        });

        $('#modal-form [name=nama_produk]').focus();

        $.get(url)
            .done(function (response) {
                $('#modal-form [name=shop_id]').val(response.shop_id);
                $('#modal-form [name=id_supplier]').val(response.id_supplier).trigger('change');
                if (response.mop) {
                    $('#modal-form [name=mop]').val(upperMop(response.mop));
                } else if (response.supplier_mop) {
                    $('#modal-form [name=mop]').val(upperMop(response.supplier_mop));
                }
                $('#modal-form [name=item_code]').val(response.kode_produk || response.item_code || '');
                $('#modal-form [name=nama_produk]').val(response.nama_produk);
                $('#modal-form [name=id_kategori]').val('1');
                $('#modal-form [name=merk]').val(response.merk);
                $('#modal-form [name=harga_beli]').val(response.harga_beli);
                $('#modal-form [name=harga_jual]').val(response.harga_jual);
                $('#modal-form [name=diskon]').val(response.diskon);
                var reorderVal = response.reorder_level;
                if (reorderVal === undefined || reorderVal === null) {
                    reorderVal = response.reorder;
                }
                $('#modal-form [name=reorder]').val(reorderVal != null && reorderVal !== '' ? reorderVal : 0);
                var stRaw = parseInt(response.stok, 10);
                if (isNaN(stRaw)) {
                    stRaw = 0;
                }
                var qs = (typeof response.quantity_sold !== 'undefined' && response.quantity_sold !== null)
                    ? parseInt(response.quantity_sold, 10)
                    : 0;
                if (isNaN(qs)) {
                    qs = 0;
                }
                if (response.is_incomplete) {
                    $('#modal-form [name=stok]').val(stRaw + qs);
                    $('#label-stok-field').text('Initial stock (units received)');
                } else {
                    $('#modal-form [name=stok]').val(response.stok);
                    $('#label-stok-field').text('Stock');
                }
                $('#modal-form [name=date_in]').val(response.date_in);
                if ($('#is_incomplete').length) {
                    $('#is_incomplete').val(response.is_incomplete ? '1' : '0');
                }
                if (response.is_incomplete) {
                    $('#quickadd-qty-sold-wrap').show();
                    $('#quickadd_qty_sold_display').text(String(qs));
                    lookupIncompleteAutoMatchForEdit();
                } else {
                    $('#incomplete-auto-match-banner').hide();
                    $('#quickadd-qty-sold-wrap').hide();
                }
            })
            .fail(function () {
                alert('Unable to display data');
            });
    }

    function deleteData(url) {
        if (confirm('Are you sure you want to delete selected data?')) {
            $.post(url, {
                    '_token': $('meta[name="csrf-token"]').attr('content'),
                    '_method': 'delete'
                })
                .done(() => {
                    table.ajax.reload();
                    updateIncompleteCount();
                })
                .fail(() => {
                    alert('Unable to delete data');
                });
        }
    }

    function deleteSelected(url) {
        if ($('input:checked').length > 1) {
            if (confirm('Yakin ingin menghapus data terpilih?')) {
                $.post(url, $('.form-produk').serialize())
                    .done(() => {
                        table.ajax.reload();
                    })
                    .fail(() => {
                        alert('Unable to delete data');
                    });
            }
        } else {
            alert('Select the data to delete');
        }
    }

    function cetakBarcode(url) {
        if ($('input:checked').length < 1) {
            alert('Select the data to print');
            return;
        } else if ($('input:checked').length < 3) {
            alert('Select at least 3 data to print');
            return;
        } else {
            $('.form-produk')
                .attr('target', '_blank')
                .attr('action', url)
                .submit();
        }
    }

     $(document).ready(function() {
      // Add Stock modal uses native <input type="date" id="add_stock_date_in"> (keyboard-friendly). Do not attach jQuery datepicker to it.

      $('#date_in_stock').val(new Date().toISOString().split('T')[0]);
   });

   function updateStockForm(url, idProduk, productName, currentStock) {
       $('#modal-update-stock').modal('show');
       $('#modal-update-stock .modal-title').text('Update Stock - ' + productName);

       $('#form-update-stock')[0].reset();
       $('#form-update-stock').attr('action', url);
       $('#id_produk').val(idProduk);
       $('#product_name').val(productName);
       $('#current_stock').val(currentStock);
       $('#date_in_stock').val(new Date().toISOString().split('T')[0]);

       $.get('/produk/' + idProduk)
           .done(function(response) {
               if (String(response.supplier_mop || '').toUpperCase() === 'CASH') {
                   $('#cashPaymentInfo').show();
               } else {
                   $('#cashPaymentInfo').hide();
               }
           })
           .fail(function() {
               $('#cashPaymentInfo').hide();
           });

       $('#additional_stock').focus();
   }

   $('#form-update-stock').validator().on('submit', function (e) {
       if (! e.preventDefault()) {
           $.ajax({
               url: $('#form-update-stock').attr('action'),
               type: 'POST',
               data: $('#form-update-stock').serialize(),
               success: function(response) {
                   $('#modal-update-stock').modal('hide');
                   table.ajax.reload(null, false);
                   updateIncompleteCount();
                   if (typeof updateIncompleteProductsCount === 'function') {
                       updateIncompleteProductsCount();
                   }
                   alert('Stock updated successfully');
               },
               error: function(xhr) {
                   var errorMessage = 'Unable to update stock. Please try again.';

                   if (xhr.responseJSON) {
                       if (typeof xhr.responseJSON === 'string') {
                           errorMessage = xhr.responseJSON;
                       } else if (xhr.responseJSON.message) {
                           errorMessage = xhr.responseJSON.message;
                       } else if (xhr.responseJSON.errors) {
                           var errors = [];
                           for (var field in xhr.responseJSON.errors) {
                               errors = errors.concat(xhr.responseJSON.errors[field]);
                           }
                           errorMessage = errors.join(' ');
                       }
                   } else if (xhr.responseText) {
                       try {
                           var parsed = JSON.parse(xhr.responseText);
                           errorMessage = parsed.message || parsed;
                       } catch (e) {
                           if (xhr.responseText.trim().length > 0 && xhr.responseText.trim().length < 500) {
                               errorMessage = xhr.responseText.trim();
                           }
                       }
                   }

                   alert(errorMessage);
               }
           });
       }
   });
</script>
@include('produk.partials.stock_movement_script')
@include('produk.partials.merge_duplicate_script')
@include('produk.partials.incomplete_auto_match_script')
@endpush