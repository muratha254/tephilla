@extends('layouts.master')

@section('title')
    Stock History
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Stock History</li>
@endsection

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    .out-of-stock-row {
        background-color: #fff5f5 !important;
    }

    .out-of-stock-row td {
        color: #a94442 !important;
    }
    
    tfoot {
        background-color: #f5f5f5;
        font-weight: bold;
    }
    
    tfoot th {
        border-top: 2px solid #ddd !important;
        padding: 10px !important;
    }

    /*
     * Editable cells: same font size as other columns (e.g. Product name), bold.
     * 1em = matches parent <td> text size; NOT .input-sm (12px).
     */
    #stock-history-table tbody td .stock-history-edit-field {
        font-size: 1em !important;
        line-height: 1.42857143 !important; /* Bootstrap 3 default, matches table body */
        padding: 6px 10px !important;
        min-height: auto !important;
        height: auto !important;
        font-weight: bold !important;
    }
    #stock-history-table tbody td .stock-history-edit-field:focus {
        font-size: 1em !important;
        font-weight: bold !important;
    }

    #history-search-loading {
        display: none;
        margin: 12px 0;
    }
    #history-search-loading.is-active {
        display: block;
    }
    #history-table-wrap.is-loading {
        opacity: 0.45;
        pointer-events: none;
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
    <!-- Summary Metrics -->
    <div class="col-lg-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-bar-chart"></i> Summary</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box bg-green">
                            <span class="info-box-icon"><i class="fa fa-cubes"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Items Updated</span>
                                <span class="info-box-number" id="total-items-updated">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box bg-blue">
                            <span class="info-box-icon"><i class="fa fa-plus-circle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Quantity Added</span>
                                <span class="info-box-number" id="total-quantity-added">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock History Table -->
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-refresh text-info"></i> Stock History</h3>
                <div class="btn-group pull-right">
                    <button onclick="exportExcel()" class="btn btn-success btn-flat">
                        <i class="fa fa-file-excel-o"></i> Export Excel
                    </button>
                    <button onclick="exportPdf()" class="btn btn-danger btn-flat">
                        <i class="fa fa-file-pdf-o"></i> Export PDF
                    </button>
                    <a href="{{ route('produk.index') }}" class="btn btn-info btn-flat"><i class="fa fa-list"></i> View All Products</a>
                </div>
            </div>
            <div class="box-body">
                <form method="GET" action="{{ route('produk.updated') }}" id="filterForm" class="mb-3">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="product_name">Product code or name</label>
                                <select name="product_name" id="product_name" class="form-control">
                                    <option value="">All products</option>
                                    @if(!empty($selectedProductName))
                                        <option value="{{ $selectedProductName }}" selected>{{ $selectedProductName }}</option>
                                    @endif
                                </select>
                                <small class="text-muted">Type code (e.g. RO50) or name — at least 2 characters.</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="shop_id">Shop</label>
                                <select name="shop_id" id="shop_id" class="form-control">
                                    <option value="">All shops</option>
                                    @foreach($shops as $id => $name)
                                        <option value="{{ $id }}" {{ (string) request('shop_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="supplier_id">Supplier</label>
                                <select name="supplier_id" id="supplier_id" class="form-control">
                                    <option value="">All suppliers</option>
                                    @foreach($suppliers as $id => $name)
                                        <option value="{{ $id }}" {{ (string) request('supplier_id') === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" 
                                    value="{{ request('start_date') }}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" 
                                    value="{{ request('end_date') }}">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fa fa-filter"></i> Filter
                                </button>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <a href="{{ route('produk.updated') }}" class="btn btn-default btn-block">
                                    <i class="fa fa-refresh"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="box-body table-responsive" id="history-table-wrap">
                <div id="history-search-loading">
                    <div class="box box-info" style="margin-bottom: 0;">
                        <div class="box-body text-center" style="padding: 16px;">
                            <i class="fa fa-refresh fa-spin fa-2x text-info"></i>
                            <p class="lead" style="margin-top: 12px; margin-bottom: 0; font-weight: bold;">Searching…</p>
                        </div>
                    </div>
                </div>
                <table id="stock-history-table" class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Shop</th>
                        <th>Previous Stock</th>
                        <th>Quantity Added</th>
                        <th>Stock after update</th>
                        <th>Date</th>
                        <th width="70" class="text-center">Product card</th>
                    </thead>
                    <tfoot>
                        <tr>
                            <th colspan="5" style="text-align: right;"><strong>Total Quantity Added:</strong></th>
                            <th id="footer-total-quantity" style="text-align: left;"><strong>0</strong></th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@include('produk.partials.stock_movement_modal')

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    let table;
    const updateHistoryUrlTemplate = @json(url('/produk/history/__HID__'));
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    $(function () {
        if ($('#product_name').length && !$('#product_name').hasClass('select2-hidden-accessible')) {
            $('#product_name').select2({
                placeholder: 'Type product code or name…',
                width: '100%',
                allowClear: true,
                minimumInputLength: 2,
                language: {
                    inputTooShort: function () { return 'Type at least 2 characters…'; },
                    searching: function () { return 'Searching…'; }
                },
                ajax: {
                    url: '{{ route('produk.updated.product_names') }}',
                    dataType: 'json',
                    delay: 300,
                    transport: function (params, success, failure) {
                        var $request = $.ajax(params);
                        $('#product_name').next('.select2-container').addClass('select2-container--loading');
                        $request.then(success);
                        $request.fail(failure);
                        $request.always(function () {
                            $('#product_name').next('.select2-container').removeClass('select2-container--loading');
                        });
                        return $request;
                    },
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (data) {
                        return data;
                    },
                    cache: true
                }
            });
        }
        if ($('#shop_id').length && !$('#shop_id').hasClass('select2-hidden-accessible')) {
            $('#shop_id').select2({
                placeholder: 'All shops',
                width: '100%',
                allowClear: true
            });
        }
        if ($('#supplier_id').length && !$('#supplier_id').hasClass('select2-hidden-accessible')) {
            $('#supplier_id').select2({
                placeholder: 'All suppliers',
                width: '100%',
                allowClear: true
            });
        }

        var historyDateColIndex = 7;
        function historyTableOrderForFilters() {
            var productName = ($('#product_name').val() || '').trim();
            return productName ? [[historyDateColIndex, 'asc']] : [[historyDateColIndex, 'desc']];
        }

        table = $('#stock-history-table').DataTable({
            responsive: false,
            scrollX: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            // #, code, name, shop, prev, qty, stock after, date
            order: historyTableOrderForFilters(),
            ajax: {
                url: '{{ route('produk.updated.data') }}',
                data: function(d) {
                    d.start_date = $('#start_date').val();
                    d.end_date = $('#end_date').val();
                    d.product_name = $('#product_name').val();
                    d.shop_id = $('#shop_id').val();
                    d.supplier_id = $('#supplier_id').val();
                },
                dataSrc: function(json) {
                    if (!json || json.data === undefined) {
                        console.error('Stock history: invalid JSON', json);
                        $('#total-items-updated').text('0');
                        $('#total-quantity-added').text('0');
                        $('#footer-total-quantity').html('<strong>0</strong>');
                        return [];
                    }
                    $('#total-items-updated').text(json.total_items_updated || 0);
                    $('#total-quantity-added').text(formatNumber(json.total_quantity_added || 0));
                    $('#footer-total-quantity').html('<strong>' + formatNumber(json.total_quantity_added || 0) + '</strong>');
                    return json.data;
                },
                error: function(xhr, textStatus) {
                    console.error('Stock history AJAX error', textStatus, xhr.status, xhr.responseText);
                    var msg = 'Could not load stock history.';
                    if (xhr.status === 401 || xhr.status === 403) {
                        msg = 'Session expired or no permission. Please refresh and log in again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    alert(msg);
                }
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'item_code'},
                {data: 'nama_produk'},
                {data: 'shop_name'},
                {data: 'previous_stock_edit', searchable: false},
                {data: 'quantity_added', searchable: false},
                {data: 'stock_after_update', searchable: false, orderable: true},
                {data: 'stock_date', searchable: false},
                {data: 'product_movement', searchable: false, orderable: false, className: 'text-center'},
            ],
            footerCallback: function (row, data, start, end, display) {
                var api = this.api();
                
                // Get total quantity added from server response (all filtered records)
                var json = api.ajax.json();
                var totalQuantity = json ? (json.total_quantity_added || 0) : 0;
                
                // Column index 5 = Quantity Added (after #, code, name, shop, prev stock)
                $(api.column(5).footer()).html('<strong>' + formatNumber(totalQuantity) + '</strong>');
            },
            drawCallback: function(settings) {
                // Update footer after each draw (when filters are applied)
                var api = this.api();
                var json = api.ajax.json();
                if (json && json.total_quantity_added !== undefined) {
                    $('#footer-total-quantity').html('<strong>' + formatNumber(json.total_quantity_added) + '</strong>');
                }
            },
        });

        var historySearchInProgress = false;
        function showHistorySearchLoading() {
            historySearchInProgress = true;
            $('#history-search-loading').addClass('is-active');
            $('#history-table-wrap').addClass('is-loading');
            var $btn = $('#filterForm button[type="submit"]');
            if (!$btn.data('orig-html')) {
                $btn.data('orig-html', $btn.html());
            }
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Searching…');
        }
        function hideHistorySearchLoading() {
            if (!historySearchInProgress) {
                return;
            }
            historySearchInProgress = false;
            $('#history-search-loading').removeClass('is-active');
            $('#history-table-wrap').removeClass('is-loading');
            var $btn = $('#filterForm button[type="submit"]');
            $btn.prop('disabled', false).html($btn.data('orig-html') || '<i class="fa fa-filter"></i> Filter');
        }
        table.on('preXhr.dt', showHistorySearchLoading);
        table.on('xhr.dt error.dt', hideHistorySearchLoading);

        // Format number function (safe for null/undefined)
        function formatNumber(num) {
            var n = Number(num);
            if (isNaN(n)) {
                n = 0;
            }
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Reload table when filter form is submitted
        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            showHistorySearchLoading();
            table.order(historyTableOrderForFilters()).ajax.reload(null, false);
        });
        
        // Update footer when table is redrawn (after filter changes)
        table.on('draw', function() {
            var json = table.ajax.json();
            if (json && json.total_quantity_added !== undefined) {
                $('#footer-total-quantity').html('<strong>' + formatNumber(json.total_quantity_added) + '</strong>');
            }
        });

        /** Parse DD/MM/YYYY, DD-MM-YYYY, or YYYY-MM-DD → YYYY-MM-DD for the server */
        function parseFlexibleDateToIso(raw) {
            var s = (raw || '').trim();
            if (!s) {
                return null;
            }
            var iso = /^(\d{4})-(\d{2})-(\d{2})$/;
            if (iso.test(s)) {
                return s;
            }
            var dmy = /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/;
            var m = s.match(dmy);
            if (m) {
                var day = parseInt(m[1], 10);
                var month = parseInt(m[2], 10);
                var year = parseInt(m[3], 10);
                if (month < 1 || month > 12 || day < 1 || day > 31) {
                    return null;
                }
                var mm = ('0' + month).slice(-2);
                var dd = ('0' + day).slice(-2);
                return year + '-' + mm + '-' + dd;
            }
            return null;
        }

        function normIntStr(v, minVal) {
            var n = parseInt(v, 10);
            if (isNaN(n)) {
                n = minVal !== undefined ? minVal : 0;
            }
            return String(n);
        }

        function saveStockHistoryRow($sourceInput) {
            var hid = $sourceInput.data('history-id');
            if (!hid) {
                return;
            }
            var $row = $sourceInput.closest('tr');
            if ($row.data('saving')) {
                return;
            }

            var $prev = $row.find('.js-inp-prev-stock[data-history-id="' + hid + '"]');
            var $qty = $row.find('.js-inp-qty-added[data-history-id="' + hid + '"]');
            var $date = $row.find('.js-inp-stock-date[data-history-id="' + hid + '"]');

            var prevOrig = $prev.attr('data-original') || '0';
            var qtyOrig = $qty.attr('data-original') || '1';
            var dateOrig = $date.attr('data-original') || '';

            if ($sourceInput.hasClass('js-inp-stock-date')) {
                var rawEmptyCheck = ($date.val() || '').trim();
                if (!rawEmptyCheck) {
                    $date.val(dateOrig);
                    return;
                }
            }

            var prevNow = normIntStr($prev.val(), 0);
            var qtyNow = normIntStr($qty.val(), 1);
            var rawDate = ($date.val() || '').trim();
            var dateIso = parseFlexibleDateToIso(rawDate);

            var thisChanged = false;
            if ($sourceInput.hasClass('js-inp-prev-stock')) {
                thisChanged = (prevNow !== prevOrig);
            } else if ($sourceInput.hasClass('js-inp-qty-added')) {
                thisChanged = (qtyNow !== qtyOrig);
            } else if ($sourceInput.hasClass('js-inp-stock-date')) {
                var origIso = parseFlexibleDateToIso(dateOrig);
                thisChanged = (dateIso !== origIso);
            }

            if (!thisChanged) {
                return;
            }

            if (parseInt(qtyNow, 10) < 1) {
                alert('Quantity added must be at least 1.');
                $qty.val(qtyOrig);
                return;
            }

            if (rawDate && !dateIso) {
                alert('Invalid date. Use DD/MM/YYYY (e.g. 20/03/2027) or YYYY-MM-DD.');
                $date.val(dateOrig);
                return;
            }

            var dateForServer = dateIso || parseFlexibleDateToIso(dateOrig);
            if (!dateForServer) {
                alert('Date is required (DD/MM/YYYY).');
                return;
            }

            var url = updateHistoryUrlTemplate.replace('__HID__', hid);
            $row.data('saving', true);
            $row.find('input').prop('readonly', true);

            $.ajax({
                url: url,
                method: 'POST',
                dataType: 'json',
                data: {
                    _token: csrfToken,
                    _method: 'PUT',
                    previous_stock: prevNow,
                    quantity_added: qtyNow,
                    stock_date: dateForServer
                }
            }).done(function () {
                table.ajax.reload(null, false);
            }).fail(function (xhr) {
                var msg = 'Save failed.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errs = xhr.responseJSON.errors;
                    msg = Object.keys(errs).map(function (k) { return errs[k].join(' '); }).join(' ');
                }
                alert(msg);
                $prev.val(prevOrig);
                $qty.val(qtyOrig);
                $date.val(dateOrig);
            }).always(function () {
                $row.data('saving', false);
                $row.find('input').prop('readonly', false);
            });
        }

        $(document).on('blur', '.js-inp-prev-stock, .js-inp-qty-added, .js-inp-stock-date', function () {
            saveStockHistoryRow($(this));
        });

        $(document).on('keydown', '.js-inp-prev-stock, .js-inp-qty-added, .js-inp-stock-date', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).blur();
            }
        });

    });
</script>
@include('produk.partials.stock_movement_script')
<script>
    // Export Excel function (defined outside jQuery ready to be accessible globally)
    function exportExcel() {
        try {
            var startDate = $('#start_date').val();
            var endDate = $('#end_date').val();
            var productName = $('#product_name').val();
            var url = '{{ route('produk.updated.export-excel') }}';
            var params = [];
            
            if (productName) {
                params.push('product_name=' + encodeURIComponent(productName));
            }
            var shopId = $('#shop_id').val();
            if (shopId) {
                params.push('shop_id=' + encodeURIComponent(shopId));
            }
            var supplierId = $('#supplier_id').val();
            if (supplierId) {
                params.push('supplier_id=' + encodeURIComponent(supplierId));
            }
            if (startDate) {
                params.push('start_date=' + encodeURIComponent(startDate));
            }
            if (endDate) {
                params.push('end_date=' + encodeURIComponent(endDate));
            }
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            console.log('Exporting Excel with URL:', url);
            window.location.href = url;
        } catch (error) {
            console.error('Error exporting Excel:', error);
            alert('Error exporting Excel: ' + error.message);
        }
    }

    // Export PDF function (defined outside jQuery ready to be accessible globally)
    function exportPdf() {
        try {
            var startDate = $('#start_date').val();
            var endDate = $('#end_date').val();
            var productName = $('#product_name').val();
            var url = '{{ route('produk.updated.export-pdf') }}';
            var params = [];
            
            if (productName) {
                params.push('product_name=' + encodeURIComponent(productName));
            }
            var shopIdPdf = $('#shop_id').val();
            if (shopIdPdf) {
                params.push('shop_id=' + encodeURIComponent(shopIdPdf));
            }
            var supplierIdPdf = $('#supplier_id').val();
            if (supplierIdPdf) {
                params.push('supplier_id=' + encodeURIComponent(supplierIdPdf));
            }
            if (startDate) {
                params.push('start_date=' + encodeURIComponent(startDate));
            }
            if (endDate) {
                params.push('end_date=' + encodeURIComponent(endDate));
            }
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            console.log('Exporting PDF with URL:', url);
            window.open(url, '_blank');
        } catch (error) {
            console.error('Error exporting PDF:', error);
            alert('Error exporting PDF: ' + error.message);
        }
    }
</script>
@endpush

