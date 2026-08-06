@extends('layouts.master')

@section('title')
    Quick Add
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Quick Add</li>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/select2/dist/css/select2.min.css') }}">
<style>
    .out-of-stock-row {
        background-color: #fff5f5 !important;
    }

    .out-of-stock-row td {
        color: #a94442 !important;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-warning text-warning"></i> Quick Add</h3>
                <div class="btn-group pull-right">
                    <button onclick="addForm('{{ route('produk.store') }}')" class="btn btn-success btn-flat"><i class="fa fa-plus-circle"></i> Enter stock</button>
                    <button onclick="deleteSelected('{{ route('produk.delete_selected') }}')" class="btn btn-danger btn-flat"><i class="fa fa-trash"></i> Delete</button>
                    <a href="{{ route('produk.index') }}" class="btn btn-info btn-flat"><i class="fa fa-list"></i> View All Products</a>
                </div>
            </div>
            <div class="box-body table-responsive">
                <form action="" method="post" class="form-produk">
                    @csrf
                    <table class="table table-stiped table-bordered table-hover">
                        <thead>
                            <th width="5%">
                                <input type="checkbox" name="select_all" id="select_all">
                            </th>
                            <th width="5%">#</th>
                            <th>Code</th>
                            <th>Product Name</th>
                            <th>Shop</th>
                            <th>Supplier</th>
                            <th>Last Receipt No</th>
                            <th>Purchase Price</th>
                            <th>Selling Price</th>
                            <th>Re-Order Level</th>
                            <th>Stock</th>
                            <th width="15%"><i class="fa fa-cog"></i></th>
                        </thead>
                    </table>
                </form>
            </div>
        </div>
    </div>
</div>

@includeIf('produk.form')
@include('produk.partials.produk_save_modals')

@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/select2/dist/js/select2.min.js') }}"></script>
@include('produk.partials.produk_save_handlers_script')
<script>
    let table;
    window.table = null;
    let addModeExistingProductId = null;
    let quickLookupTimer = null;
    let quickLookupXhr = null;
    let itemCodeSuggestTimer = null;
    let itemCodeSuggestXhr = null;

    /** Mode of payment: show and submit in UPPERCASE */
    function upperMop(v) {
        if (v === null || typeof v === 'undefined') {
            return '';
        }
        return String(v).toUpperCase();
    }

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

    /** Uses quick_lookup JSON (no second request) to toggle stock-to-add UI vs new product. */
    function syncAddStockUiAfterQuickLookup(resp) {
        if (!$('#modal-form').is(':visible')) return;
        if (($('#modal-form [name=_method]').val() || '').toLowerCase() !== 'post') return;

        var selectedShopId = parseInt($('#shop_id').val(), 10) || 0;
        var itemCode = ($('#item_code').val() || '').trim();
        if (itemCode.length < 2 || selectedShopId <= 0) {
            resetAddStockDualFieldUi();
            restoreStokRequiredIfAddPost();
            return;
        }
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
        if (!exactShop && resp.id_produk && parseInt(resp.shop_id, 10) === selectedShopId) {
            exactShop = {
                id_produk: resp.id_produk,
                item_code: resp.item_code,
                kode_produk: resp.kode_produk || resp.item_code,
                shop_id: resp.shop_id,
                nama_produk: resp.nama_produk,
                harga_beli: resp.harga_beli,
                harga_jual: resp.harga_jual,
                stok: resp.stok,
                reorder: resp.reorder,
                id_supplier: resp.id_supplier,
                mop: resp.mop
            };
        }

        if (!exactShop) {
            resetAddStockDualFieldUi();
            restoreStokRequiredIfAddPost();
            return;
        }

        applyAddStockExistingUi(exactShop);
    }

    function fetchItemCodeSuggestions() {
        const term = ($('#item_code').val() || '').trim();
        const $list = $('#item_code_suggestions');

        if (!term) {
            $list.empty();
            return;
        }

        if (itemCodeSuggestXhr && itemCodeSuggestXhr.readyState !== 4) {
            itemCodeSuggestXhr.abort();
        }

        itemCodeSuggestXhr = $.ajax({
            url: '{{ route('produk.item_code_suggestions') }}',
            method: 'GET',
            dataType: 'json',
            data: {
                q: term,
                shop_id: parseInt($('#shop_id').val(), 10) || 0
            }
        }).done(function(resp) {
            $list.empty();
            const rows = (resp && Array.isArray(resp.results)) ? resp.results : [];
            rows.forEach(function(r) {
                // value = code (what user selects), label/text = context (shop/product)
                const value = (r.code || '').toString();
                const label = (r.label || '').toString();
                if (!value) return;
                $list.append('<option value="' + $('<div/>').text(value).html() + '" label="' + $('<div/>').text(label).html() + '"></option>');
            });
        }).fail(function(xhr) {
            if (xhr && xhr.status === 0) return; // aborted
        });
    }

    function isProdukModalAddPost() {
        return $('#modal-form').is(':visible')
            && ($('#modal-form [name=_method]').val() || '').toLowerCase() === 'post';
    }

    function applyQuickLookupProductFields(resp) {
        if (!resp || !resp.found) {
            return;
        }
        if (typeof resp.nama_produk !== 'undefined') {
            $('#nama_produk').val(resp.nama_produk);
        }
        if (typeof resp.harga_beli !== 'undefined') {
            $('#harga_beli').val(resp.harga_beli);
        }
        if (typeof resp.harga_jual !== 'undefined') {
            $('#harga_jual').val(resp.harga_jual);
        }
        if (typeof resp.stok !== 'undefined') {
            $('#stok').val(resp.stok);
        }
        if (typeof resp.shop_id !== 'undefined' && resp.shop_id) {
            $('#shop_id').val(resp.shop_id);
        }
        if (typeof resp.id_supplier !== 'undefined' && resp.id_supplier) {
            var newSid = String(resp.id_supplier);
            if (String($('#id_supplier').val() || '') !== newSid) {
                $('#id_supplier').val(newSid).trigger('change');
            }
        }
        if (typeof resp.mop !== 'undefined') {
            $('#mop').val(upperMop(resp.mop));
        }
    }

    function quickLookupByItemCode() {
        const itemCode = ($('#item_code').val() || '').trim();
        if (!itemCode) {
            $('#item-code-match-wrap').hide();
            $('#item_code_match_select').empty().append('<option value="">Select matching item</option>');
            if ($('#modal-form').is(':visible') && ($('#modal-form [name=_method]').val() || '').toLowerCase() === 'post') {
                resetAddStockDualFieldUi();
                restoreStokRequiredIfAddPost();
            }
            return;
        }
        if (itemCode.length < 2) {
            return;
        }

        if (quickLookupXhr && quickLookupXhr.readyState !== 4) {
            quickLookupXhr.abort();
        }

        quickLookupXhr = $.ajax({
            url: '{{ route('produk.quick_lookup') }}',
            method: 'GET',
            dataType: 'json',
            data: {
                item_code: itemCode,
                shop_id: parseInt($('#shop_id').val(), 10) || 0
            }
        }).done(function(resp) {
            if (!resp) return;

            syncIncompleteEditAutoMatch(resp);

            // Same code may exist across shops: show selector so user chooses the exact item/shop.
            if (resp.multiple && Array.isArray(resp.matches) && resp.matches.length > 1) {
                const $sel = $('#item_code_match_select');
                $sel.empty().append('<option value="">Select matching item</option>');
                resp.matches.forEach(function(m) {
                    const label = (m.shop_code || '-') + ' - ' + (m.shop_name || 'Unknown Shop') + ' | ' + (m.nama_produk || '-') + ' | Sell: ' + (m.harga_jual || 0);
                    $sel.append('<option value="' + (m.id_produk || 0) + '" data-shop-id="' + (m.shop_id || 0) + '">' + label + '</option>');
                });
                $('#item-code-match-wrap').show();
            } else {
                $('#item-code-match-wrap').hide();
                $('#item_code_match_select').empty().append('<option value="">Select matching item</option>');
            }

            // Existing item: auto-populate key fields (skip stock row when "quantity to add" mode is active)
            if (resp.found) {
                syncAddStockUiAfterQuickLookup(resp);
                var inExisting = $('#add-stock-to-add-wrap').is(':visible');
                if (!inExisting) {
                    applyQuickLookupProductFields(resp);
                }
                return;
            }

            if ($('#modal-form').is(':visible') && ($('#modal-form [name=_method]').val() || '').toLowerCase() === 'post') {
                resetAddStockDualFieldUi();
                restoreStokRequiredIfAddPost();
            }

            // New item: only set shop from prefix if found, leave rest editable
            if (typeof resp.shop_id !== 'undefined' && resp.shop_id) {
                $('#shop_id').val(resp.shop_id);
            }
        }).fail(function(xhr) {
            if (xhr && xhr.status === 0) return; // request aborted
            // Silent fail to avoid interrupting typing flow
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

    var __incompleteModalSaveInFlight = false;

    function handleIncompleteProdukSaved(response, isAdd) {
        if (response && response.redirect_url) {
            window.location.href = response.redirect_url;
            return;
        }
        table.ajax.reload(null, false);
        updateIncompleteCount();
        if (typeof updateIncompleteProductsCount === 'function') {
            updateIncompleteProductsCount();
        }
        if (isAdd) {
            prepareAddStockFormForNextEntry();
        } else {
            $('#modal-form').modal('hide');
        }
    }

    function isProdukAlreadyMergedXhr(xhr, errorMessage) {
        if (!xhr) {
            return false;
        }
        if (xhr.status === 404) {
            return true;
        }
        var msg = String(errorMessage || '');
        return msg.indexOf('No query results for model') !== -1
            || msg.indexOf('linked to inventory') !== -1;
    }

    $(function () {
        table = $('.table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            ajax: {
                url: '{{ route('produk.incomplete.data') }}',
            },
            columns: [
                {data: 'select_all', searchable: false, sortable: false},
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'item_code'},
                {data: 'nama_produk'},
                {data: 'shop_name'},
                {data: 'supplier_name'},
                {data: 'last_receipt_no', searchable: true},
                {data: 'harga_beli'},
                {data: 'harga_jual'},
                {data: 'reorder_level'},
                {data: 'stok'},
                {data: 'aksi', searchable: false, sortable: false},
            ],
            rowCallback: function(row, data) {
                if (parseFloat(data.stok_raw) <= 0) {
                    $(row).addClass('out-of-stock-row');
                }
            }
        });
        window.table = table;

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

        // Handle supplier selection change to autofill Mode of Payment
        $(document).on('change', '#id_supplier', function() {
            var supplierId = $(this).val();
            if (supplierId) {
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
                    });
            } else {
                $('#mop').val('');
            }
        });

        $(document).on('change', '#shop_id', function() {
            if ($('#modal-form').is(':visible') && ($('#modal-form [name=_method]').val() || '').toLowerCase() === 'post') {
                quickLookupByItemCode();
            }
        });

        $(document).on('select2:select', '#id_supplier', function() {
            var supplierId = $(this).val();
            if (supplierId) {
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
                    });
            } else {
                $('#mop').val('');
            }
        });

        // Auto-populate commodity, prices, stock when item code exists
        $(document).on('input', '#item_code', function() {
            var code = ($(this).val() || '').trim();
            if (isProdukModalAddPost()) {
                if (itemCodeSuggestTimer) clearTimeout(itemCodeSuggestTimer);
                itemCodeSuggestTimer = setTimeout(fetchItemCodeSuggestions, 280);
            }
            if (quickLookupTimer) clearTimeout(quickLookupTimer);
            if (code.length < 2) {
                return;
            }
            var delay = isProdukModalAddPost() ? 320 : 400;
            quickLookupTimer = setTimeout(quickLookupByItemCode, delay);
        });
        $(document).on('blur', '#item_code', function() {
            quickLookupByItemCode();
        });
        $(document).on('change', '#item_code_match_select', function() {
            const selectedOption = $(this).find('option:selected');
            const productId = parseInt($(this).val() || '0', 10);
            const shopId = parseInt(selectedOption.data('shop-id') || '0', 10);
            const itemCode = ($('#item_code').val() || '').trim();
            if (!productId || !itemCode) return;

            // Re-run lookup with selected shop to populate exact item details.
            if (quickLookupXhr && quickLookupXhr.readyState !== 4) {
                quickLookupXhr.abort();
            }
            quickLookupXhr = $.ajax({
                url: '{{ route('produk.quick_lookup') }}',
                method: 'GET',
                dataType: 'json',
                data: { item_code: itemCode, shop_id: shopId }
            }).done(function(resp) {
                if (!resp || !resp.found) return;
                syncAddStockUiAfterQuickLookup(resp);
                var inExisting = $('#add-stock-to-add-wrap').is(':visible');
                if (!inExisting) {
                    applyQuickLookupProductFields(resp);
                }
            });
        });

        $('#modal-form').validator().on('submit', function (e) {
            if (e.isDefaultPrevented()) {
                return;
            }
            e.preventDefault();

            var isAdd = (($('#modal-form [name=_method]').val() || '').toLowerCase() === 'post');
            var baseObj = {};
            var arr = $('#modal-form form').serializeArray();
            for (var i = 0; i < arr.length; i++) {
                baseObj[arr[i].name] = arr[i].value;
            }
            if (!isAdd) {
                baseObj.supplier_sales_decision = 'skip';
                baseObj.price_retro_decision = 'skip';
            }

            function modalSubmitFn(data) {
                if (__incompleteModalSaveInFlight) {
                    return;
                }
                __incompleteModalSaveInFlight = true;
                var $saveBtn = $('#btn_modal_save_produk').prop('disabled', true);
                $.ajax({
                    url: $('#modal-form form').attr('action'),
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).always(function () {
                    __incompleteModalSaveInFlight = false;
                    $saveBtn.prop('disabled', false);
                }).done(function (r2) {
                    if (window.handleProdukSaveResponse(r2, {
                        baseData: data,
                        $inlineInput: null,
                        submitFn: modalSubmitFn
                    }, function (saved) {
                        handleIncompleteProdukSaved(saved, isAdd);
                    })) {
                        return;
                    }
                    handleIncompleteProdukSaved(r2, isAdd);
                }).fail(function (xhr) {
                    if (window.handleDuplicateMergeFromXhr(xhr, function (resp) {
                        handleIncompleteProdukSaved(resp || {}, isAdd);
                    })) {
                        return;
                    }
                    var json = window.produkParseXhrJson ? window.produkParseXhrJson(xhr) : xhr.responseJSON;
                    var errorMessage = 'Unable to save data';
                    if (json) {
                        if (typeof json === 'string') {
                            errorMessage = json;
                        } else if (json.message) {
                            errorMessage = json.message;
                        } else if (json.errors) {
                            var errors = [];
                            for (var field in json.errors) {
                                errors = errors.concat(json.errors[field]);
                            }
                            errorMessage = errors.join(' ');
                        }
                    }
                    if (!isAdd && isProdukAlreadyMergedXhr(xhr, errorMessage)) {
                        handleIncompleteProdukSaved({ auto_merged: true }, false);
                        return;
                    }
                    alert(errorMessage);
                });
            }

            modalSubmitFn(baseObj);
        });

        $('[name=select_all]').on('click', function () {
            $(':checkbox').prop('checked', this.checked);
        });
    });

    function addForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Add Stock');
        $('#modal-form').data('produk-store-url', url);

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('post');
        resetAddStockDualFieldUi();
        $('#stok').prop('required', true).attr('required', 'required');
        $('#mop').val('');
        $('#item_code').val('');
        $('#item-code-match-wrap').hide();
        $('#item_code_match_select').empty().append('<option value="">Select matching item</option>');
        $('#incomplete-auto-match-banner').hide();
        $('#quickadd-qty-sold-wrap').hide();
        $('#label-stok-field').text('Stock');

        // Initialize Select2 for filterable supplier dropdown
        if ($('#id_supplier').hasClass('select2-hidden-accessible')) {
            $('#id_supplier').select2('destroy');
        }
        $('#id_supplier').select2({
            placeholder: 'Select Supplier',
            width: '100%',
            dropdownParent: ($('#modal-form .modal-content').length ? $('#modal-form .modal-content') : $('#modal-form'))
        });

        $('#add_stock_date_in').val(new Date().toISOString().split('T')[0]);

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

        // Initialize Select2 for filterable supplier dropdown (before loading data)
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
            .done((response) => {
                $('#modal-form [name=nama_produk]').val(response.nama_produk);
                $('#modal-form [name=item_code]').val(response.item_code);
                $('#modal-form [name=id_kategori]').val(response.id_kategori);
                $('#modal-form [name=shop_id]').val(response.shop_id);
                $('#modal-form [name=id_supplier]').val(response.id_supplier).trigger('change');
                // MOP auto-filled by supplier change event; fallback from response
                if (response.mop) {
                    $('#modal-form [name=mop]').val(upperMop(response.mop));
                } else if (response.supplier_mop) {
                    $('#modal-form [name=mop]').val(upperMop(response.supplier_mop));
                }
                $('#modal-form [name=harga_beli]').val(response.harga_beli);
                $('#modal-form [name=harga_jual]').val(response.harga_jual);
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
                $('#modal-form [name=is_incomplete]').val(response.is_incomplete);
                if (response.is_incomplete) {
                    $('#modal-form [name=harga_beli]').attr('required', true);
                    $('#modal-form [name=harga_beli]').attr('min', '0');
                    $('#quickadd-qty-sold-wrap').show();
                    $('#quickadd_qty_sold_display').text(String(qs));
                    resetIncompleteAutoMatchUi();
                    quickLookupByItemCode();
                } else {
                    resetIncompleteAutoMatchUi();
                    $('#quickadd-qty-sold-wrap').hide();
                }
            })
            .fail((xhr) => {
                if (xhr && xhr.status === 404) {
                    $('#modal-form').modal('hide');
                    table.ajax.reload(null, false);
                    updateIncompleteCount();
                    return;
                }
                alert('Unable to display data');
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
                    updateIncompleteCount();
                })
                .fail((errors) => {
                    alert('Unable to delete data');
                    return;
                });
        }
    }

    function deleteSelected(url) {
        if ($('input:checked').length < 1) {
            alert('Select the data to delete');
            return;
        }

        if (confirm('Are you sure you want to delete the selected data?')) {
            $.post(url, $('.form-produk').serialize())
                .done((response) => {
                    table.ajax.reload();
                    updateIncompleteCount();
                })
                .fail((errors) => {
                    alert('Unable to delete data');
                    return;
                });
        }
    }

    function cetakBarcode(url) {
        if ($('input:checked').length < 1) {
            alert('Select the data to print');
            return;
        } else if ($('input:checked').length < 3) {
            alert('Select at least 3 data to print');
            return;
        }

        $('.form-produk')
            .attr('target', '_blank')
            .attr('action', url)
            .submit();
    }
</script>
@include('produk.partials.incomplete_auto_match_script')
@endpush
