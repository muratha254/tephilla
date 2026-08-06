@extends('layouts.master')

@push('css')
<link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
<style>
    #consignment-filter-loading {
        display: none;
        margin: 0 0 12px;
    }
    #consignment-filter-loading.is-active {
        display: block;
    }
    #consignment-table-wrap.is-loading {
        opacity: 0.45;
        pointer-events: none;
    }
</style>
@endpush

@section('title')
    Consignment Overview
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Consignment Overview</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">All Consignments</h3>
                <div class="box-tools">
                    <button id="btn-print-consignments" class="btn btn-default btn-sm"><i class="fa fa-print"></i> Print</button>
                    <button id="btn-export-consignments" class="btn btn-success btn-sm"><i class="fa fa-file-excel-o"></i> Export Excel</button>
                </div>
            </div>
            <div class="box-body">
                @include('payment.partials.missing_buying_price_fix')
                <div class="row" id="consignment-filter-row" style="margin-bottom: 15px;">
                    <div class="col-md-3">
                        <label>Status</label>
                        <select id="statusFilter" class="form-control input-sm">
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="supplierFilter">Supplier</label>
                        <select id="supplierFilter" class="form-control">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id_supplier }}">{{ $supplier->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="startDateFilter">From Date</label>
                        <input type="text" id="startDateFilter" class="form-control input-sm consignment-datepicker" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="col-md-2">
                        <label for="endDateFilter">To Date</label>
                        <input type="text" id="endDateFilter" class="form-control input-sm consignment-datepicker" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="col-md-2" style="margin-top: 24px;">
                        <button type="button" id="consignmentApplyFilters" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Apply</button>
                        <button type="button" id="consignmentResetFilters" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i></button>
                    </div>
                </div>
                <div class="table-responsive" id="consignment-table-wrap">
                <div id="consignment-filter-loading">
                    <div class="box box-info" style="margin-bottom: 0;">
                        <div class="box-body text-center" style="padding: 16px;">
                            <i class="fa fa-refresh fa-spin fa-2x text-info"></i>
                            <p class="lead" style="margin-top: 12px; margin-bottom: 0; font-weight: bold;">Filtering…</p>
                        </div>
                    </div>
                </div>
                    <table id="consignmentsTable" class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Receipt</th>
                                <th>Supplier</th>
                                <th>Phone</th>
                                <th>Product</th>
                                <th>Invoice</th>
                                <th>Shop</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Transaction Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="box-footer">
                <div class="row">
                    <div class="col-md-4"><strong>Total Amount:</strong> <span id="totalAmount">Ksh 0.00</span></div>
                    <div class="col-md-4"><strong>Total Paid:</strong> <span id="totalPaid">Ksh 0.00</span></div>
                    <div class="col-md-4"><strong>Total Balance:</strong> <span id="totalBalance">Ksh 0.00</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

@includeIf('supplier.consignment')
@includeIf('payment.convert_to_cash_modal')
@endsection

@push('scripts')
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script>
    let consignmentsTable;

    function toYmd(ddmmyyyy) {
        if (!ddmmyyyy) {
            return '';
        }
        var parts = String(ddmmyyyy).trim().split('/');
        if (parts.length === 3) {
            var d = String(parts[0]).padStart(2, '0');
            var m = String(parts[1]).padStart(2, '0');
            return parts[2] + '-' + m + '-' + d;
        }
        return ddmmyyyy;
    }

        $(function () {
        $('.consignment-datepicker').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true
        });

        consignmentsTable = $('#consignmentsTable').DataTable({
            // serverSide + responsive often breaks column indexes / reload; this page uses many columns + horizontal scroll
            responsive: false,
            processing: true,
            serverSide: true,
            autoWidth: false,
            deferRender: true,
            pageLength: 50,
            lengthMenu: [[25, 50, 100, 200], [25, 50, 100, 200]],
            order: [[11, 'desc']],
            ajax: {
                url: '{{ route('payment.consignments.data') }}',
                data: function (d) {
                    d.status = $('#statusFilter').val();
                    d.supplier_id = $('#supplierFilter').val();
                    d.start_date = toYmd($('#startDateFilter').val());
                    d.end_date = toYmd($('#endDateFilter').val());
                },
                error: function (xhr) {
                    if (consignmentsTable) {
                        consignmentsTable.processing(false);
                    }
                    console.error('Consignments data error', xhr.status, xhr.responseText);
                    alert('Failed to load consignments. Check your connection or try a smaller date range.');
                }
            },
            columns: [
                { data: 'DT_RowIndex', searchable: false, sortable: false },
                { data: 'receipt_number', name: 'receipt_number' },
                { data: 'supplier_name', name: 'supplier_name' },
                { data: 'supplier_phone', name: 'supplier_phone', searchable: false },
                { data: 'product_name', name: 'product_name' },
                { data: 'invoice_number', name: 'invoice_number' },
                { data: 'shop_name', name: 'shop_name' },
                { data: 'quantity', name: 'quantity', className: 'text-center', searchable: false },
                { data: 'total_amount', name: 'total_amount', searchable: false },
                { data: 'amount_paid', name: 'amount_paid', searchable: false },
                { data: 'outstanding_balance', name: 'outstanding_balance', searchable: false, orderable: false },
                { data: 'transaction_date', name: 'transaction_date', searchable: false },
                { data: 'status', searchable: false, sortable: false },
                { data: 'aksi', searchable: false, sortable: false }
            ]
        });

        var consignmentFilterInProgress = false;
        function showConsignmentFilterLoading() {
            consignmentFilterInProgress = true;
            $('#consignment-filter-loading').addClass('is-active');
            $('#consignment-table-wrap').addClass('is-loading');
            var $applyBtn = $('#consignmentApplyFilters');
            var $resetBtn = $('#consignmentResetFilters');
            if (!$applyBtn.data('orig-html')) {
                $applyBtn.data('orig-html', $applyBtn.html());
            }
            if (!$resetBtn.data('orig-html')) {
                $resetBtn.data('orig-html', $resetBtn.html());
            }
            $applyBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Filtering…');
            $resetBtn.prop('disabled', true);
        }
        function hideConsignmentFilterLoading() {
            if (!consignmentFilterInProgress) {
                return;
            }
            consignmentFilterInProgress = false;
            $('#consignment-filter-loading').removeClass('is-active');
            $('#consignment-table-wrap').removeClass('is-loading');
            $('#consignmentApplyFilters').prop('disabled', false).html($('#consignmentApplyFilters').data('orig-html') || '<i class="fa fa-filter"></i> Apply');
            $('#consignmentResetFilters').prop('disabled', false).html($('#consignmentResetFilters').data('orig-html') || '<i class="fa fa-refresh"></i>');
        }
        consignmentsTable.on('preXhr.dt', showConsignmentFilterLoading);
        consignmentsTable.on('xhr.dt error.dt', hideConsignmentFilterLoading);

        $('#consignmentApplyFilters').on('click', function (e) {
            e.preventDefault();
            if (!consignmentsTable || !consignmentsTable.ajax) {
                return;
            }
            // resetPaging true = go to page 1 after filter change
            consignmentsTable.ajax.reload(null, true);
        });

        $('#consignmentResetFilters').on('click', function (e) {
            e.preventDefault();
            $('#statusFilter').val('');
            $('#supplierFilter').val('');
            $('#startDateFilter').val('');
            $('#endDateFilter').val('');
            if (!consignmentsTable || !consignmentsTable.ajax) {
                return;
            }
            consignmentsTable.ajax.reload(null, true);
        });

        $('#consignmentsTable').on('xhr.dt', function (e, settings, json) {
            const totals = json && json.totals ? json.totals : { total_amount: 0, total_paid: 0, total_balance: 0 };
            $('#totalAmount').text(formatCurrency(totals.total_amount));
            $('#totalPaid').text(formatCurrency(totals.total_paid));
            $('#totalBalance').text(formatCurrency(totals.total_balance));
        });

        $('#btn-print-consignments').on('click', function () {
            window.open(buildExportUrl('{{ route('payment.consignments.export-pdf') }}'), '_blank');
        });

        $('#btn-export-consignments').on('click', function () {
            window.location.href = buildExportUrl('{{ route('payment.consignments.export-excel') }}');
        });
    });

    function editConsignmentItem(invoiceItemId) {
        $('#editConsignmentModal').modal('show');
        $('#editConsignmentLoading').show();
        $('#editConsignmentFormWrap').hide();
        $('#saveConsignmentItemBtn').prop('disabled', true);

        $.ajax({
            url: '{{ url("/payment/consignment/item") }}/' + invoiceItemId + '/edit',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#editConsignmentLoading').hide();
                if (response && response.success && response.item) {
                    var item = response.item;
                    $('#editConsignmentItemId').val(item.id);
                    $('#editConsignmentProductName').text(item.product_name);
                    $('#editConsignmentInvoiceNumber').text(item.invoice_number);
                    $('#editConsignmentSupplierName').text(item.supplier_name);
                    $('#editConsignmentQuantity').val(item.quantity);
                    $('#editConsignmentAmount').val(parseFloat(item.amount).toFixed(2));
                    $('#editConsignmentAmountPaid').text(parseFloat(item.amount_paid).toFixed(2));
                    $('#editConsignmentFormWrap').show();
                    $('#saveConsignmentItemBtn').prop('disabled', false);
                } else {
                    alert(response.message || 'Failed to load consignment item.');
                }
            },
            error: function(xhr) {
                $('#editConsignmentLoading').hide();
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to load item.';
                if (xhr.status === 404) msg = 'Consignment item not found.';
                alert(msg);
            }
        });
    }

    $('#saveConsignmentItemBtn').on('click', function() {
        var id = $('#editConsignmentItemId').val();
        var quantity = parseInt($('#editConsignmentQuantity').val(), 10);
        var amount = parseFloat($('#editConsignmentAmount').val());

        if (!id) return;
        if (isNaN(quantity) || quantity < 1) {
            alert('Please enter a valid quantity (at least 1).');
            return;
        }
        if (isNaN(amount) || amount < 0) {
            alert('Please enter a valid amount (0 or more).');
            return;
        }

        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: '{{ url("/payment/consignment/item") }}/' + id,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'PUT',
                quantity: quantity,
                amount: amount
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#editConsignmentModal').modal('hide');
                    consignmentsTable.ajax.reload();
                    alert(response.message || 'Consignment item updated successfully.');
                } else {
                    alert(response.message || 'Update failed.');
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to update.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                }
                alert(msg);
            },
            complete: function() {
                $('#saveConsignmentItemBtn').prop('disabled', false).html('<i class="fa fa-save"></i> Save');
            }
        });
    });

    function viewConsignment(supplierId, invoiceItemId = null, pendingOnly = false) {
        $('#modal-consignment').modal('show');
        
        $('#consignment-loading').show();
        $('#consignment-content').hide();
        $('#paid-items-loading').show();
        $('#paid-items-table-wrapper').hide();
        $('#paid-items-empty').hide();
        $('#btn-export-pdf').hide();
        $('#btn-export-excel').hide();
        resetSelectedConsignment();

        // Always load ALL items without date filters
        let url = '{{ url("/supplier") }}/' + supplierId + '/consignment';
        const params = [];
        if (invoiceItemId) {
            params.push('invoice_item_id=' + invoiceItemId);
        }
        if (pendingOnly) {
            params.push('pending_only=1');
        }
        // Do not add date filters - show all items
        if (params.length) {
            url += '?' + params.join('&');
        }

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#consignment-loading').hide();
                
                if (response && response.supplier && response.consignment) {
                    $('#consignment-supplier-name').text(response.supplier.nama || 'N/A');
                    $('#consignment-supplier-phone').text(response.supplier.telepon || 'N/A');
                    $('#consignment-supplier-address').text(response.supplier.alamat || 'N/A');
                    
                    $('#consignment-total').text('Ksh ' + parseFloat(response.consignment.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#consignment-paid').text('Ksh ' + parseFloat(response.consignment.paid || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#consignment-pending').text('Ksh ' + parseFloat(response.consignment.pending || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    
                    let status = 'Not paid';
                    let statusClass = 'default';
                    if (response.consignment.paid > 0) {
                        if (response.consignment.pending <= 0) {
                            status = 'Paid';
                            statusClass = 'success';
                        } else {
                            status = 'Partially paid';
                            statusClass = 'warning';
                        }
                    }
                    $('#consignment-status-badge').text(status).removeClass('label-default label-success label-warning').addClass('label-' + statusClass);
                    
                    renderSelectedConsignment(response.selected_item || null);

                    // Get items based on pendingOnly flag
                    let allItems = [];
                    if (pendingOnly) {
                        // When pendingOnly is true, only show pending items
                        if (response.pending_items && response.pending_items.length > 0) {
                            allItems = response.pending_items;
                        }
                    } else {
                        // When pendingOnly is false, show all items
                        if (response.all_items && response.all_items.length > 0) {
                            // Use the new all_items array which contains everything
                            allItems = response.all_items;
                        } else {
                            // Fallback: combine paid and pending items (backward compatibility)
                            if (response.paid_items && response.paid_items.length > 0) {
                                allItems = allItems.concat(response.paid_items);
                            }
                            if (response.pending_items && response.pending_items.length > 0) {
                                // Remove duplicates based on id
                                let existingIds = new Set(allItems.map(item => item.id));
                                response.pending_items.forEach(function(item) {
                                    if (!existingIds.has(item.id)) {
                                        allItems.push(item);
                                    }
                                });
                            }
                        }
                    }
                    
                    if (allItems && allItems.length > 0) {
                        loadPaidItems(allItems, supplierId, pendingOnly);
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
                    $('#consignment-content').show();
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
                $('#consignment-content').show();
            }
        });
    }

    function loadPaidItems(items, supplierId, pendingOnly = false) {
        $('#paid-items-loading').hide();
        $('#paid-items-empty').hide();
        $('#paid-items-tbody').empty();
        
        // Sort items by date (most recent first)
        items.sort(function(a, b) {
            let dateA = new Date(a.created_at || a.payment_date || '1900-01-01');
            let dateB = new Date(b.created_at || b.payment_date || '1900-01-01');
            return dateB - dateA;
        });
        
        let totalAmount = 0;
        
        items.forEach(function(item, index) {
            // When showing pending items, use balance instead of total_amount
            if (pendingOnly) {
                totalAmount += parseFloat(item.balance || 0);
            } else {
                totalAmount += parseFloat(item.total_amount || 0);
            }
            
            // Format date for display
            let displayDate = item.created_at || item.payment_date || 'N/A';
            if (displayDate !== 'N/A' && displayDate) {
                let dateObj = new Date(displayDate);
                if (!isNaN(dateObj.getTime())) {
                    displayDate = dateObj.toLocaleDateString('en-GB'); // dd/mm/yyyy format
                }
            }
            
            // Calculate buying price (unit price)
            let buyingPrice = parseFloat(item.unit_price || 0);
            if (buyingPrice === 0 && item.quantity > 0) {
                buyingPrice = parseFloat(item.total_amount || 0) / parseFloat(item.quantity || 1);
            }
            
            // When showing pending items, use balance for display; otherwise use total_amount
            let displayAmount = pendingOnly ? parseFloat(item.balance || 0) : parseFloat(item.total_amount || 0);
            
            let editBtn = (item.id) ? '<button type="button" class="btn btn-xs btn-primary btn-flat" onclick="editConsignmentItem(' + item.id + ')" title="Edit"><i class="fa fa-edit"></i> Edit</button>' : '';
            let row = '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + (item.invoice_number || 'N/A') + '</td>' +
                '<td>' + displayDate + '</td>' +
                '<td>' + (item.product_name || 'N/A') + (item.product_code ? ' (' + item.product_code + ')' : '') + '</td>' +
                '<td>' + (item.quantity || 0) + '</td>' +
                '<td>Ksh ' + buyingPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>Ksh ' + displayAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>' + editBtn + '</td>' +
                '</tr>';
            
            $('#paid-items-tbody').append(row);
        });
        
        $('#paid-items-total').text('Ksh ' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#paid-items-table-wrapper').show();
        $('#btn-export-pdf').attr('data-supplier-id', supplierId).show();
        $('#btn-export-excel').attr('data-supplier-id', supplierId).show();
    }

    $(document).on('click', '#btn-export-pdf', function() {
        let supplierId = $(this).attr('data-supplier-id');
        if (supplierId) {
            window.open('{{ url("/supplier") }}/' + supplierId + '/consignment/export-pdf', '_blank');
        }
    });

    $(document).on('click', '#btn-export-excel', function() {
        let supplierId = $(this).attr('data-supplier-id');
        if (supplierId) {
            window.location.href = '{{ url("/supplier") }}/' + supplierId + '/consignment/export-excel';
        }
    });

    function buildExportUrl(baseUrl) {
        const params = [];
        const status = $('#statusFilter').val();
        const supplier = $('#supplierFilter').val();
        const startDate = toYmd($('#startDateFilter').val());
        const endDate = toYmd($('#endDateFilter').val());

        if (status) params.push('status=' + encodeURIComponent(status));
        if (supplier) params.push('supplier_id=' + encodeURIComponent(supplier));
        if (startDate) params.push('start_date=' + encodeURIComponent(startDate));
        if (endDate) params.push('end_date=' + encodeURIComponent(endDate));

        if (params.length) {
            return baseUrl + '?' + params.join('&');
        }
        return baseUrl;
    }

    function formatCurrency(value) {
        const amount = parseFloat(value || 0);
        return 'Ksh ' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

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

    let currentCashSupplierId = null;

    function convertToCash(supplierId, invoiceItemId) {
        currentCashSupplierId = supplierId;
        
        $('#convertToCashModal').modal('show');
        $('#convertToCashItemsLoading').show();
        $('#convertToCashItemsContent').hide();
        $('#confirmConvertToCashBtn').prop('disabled', true);
        $('#convertToCashItemsTableBody').empty();

        // Load supplier and invoice items
        $.ajax({
            url: '{{ url("/supplier") }}/' + supplierId + '/consignment',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response && response.supplier) {
                    $('#convertToCashSupplierName').text(response.supplier.nama || 'N/A');
                    
                    // Get all items (pending items that can be converted)
                    let items = [];
                    if (response.pending_items && response.pending_items.length > 0) {
                        items = response.pending_items;
                    } else if (response.all_items && response.all_items.length > 0) {
                        // Filter only unpaid items
                        items = response.all_items.filter(function(item) {
                            let balance = parseFloat(item.balance || item.total_amount || 0) - parseFloat(item.amount_paid || 0);
                            return balance > 0;
                        });
                    }

                    if (items.length === 0) {
                        $('#convertToCashItemsLoading').html('<div class="alert alert-warning">No pending items available to convert.</div>');
                        return;
                    }

                    let html = '';
                    items.forEach(function(item) {
                        let unitPrice = parseFloat(item.unit_price || 0);
                        if (unitPrice === 0 && item.quantity > 0) {
                            unitPrice = parseFloat(item.total_amount || 0) / parseFloat(item.quantity || 1);
                        }
                        
                        html += '<tr>';
                        html += '<td><input type="checkbox" class="cash-item-checkbox" data-item-id="' + item.id + '" data-produk-id="' + (item.produk_id || '') + '" data-quantity="' + (item.quantity || 0) + '" data-price="' + unitPrice + '" data-amount="' + (item.total_amount || 0) + '"></td>';
                        html += '<td>' + (item.product_name || 'Unknown') + '</td>';
                        html += '<td>' + (item.quantity || 0) + '</td>';
                        html += '<td>Ksh ' + unitPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                        html += '<td>Ksh ' + parseFloat(item.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                        html += '</tr>';
                    });
                    $('#convertToCashItemsTableBody').html(html);
                    
                    $('#convertToCashItemsLoading').hide();
                    $('#convertToCashItemsContent').show();
                } else {
                    $('#convertToCashItemsLoading').html('<div class="alert alert-danger">Error loading consignment items</div>');
                }
            },
            error: function(xhr) {
                var errorMsg = 'Error loading consignment items';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMsg = 'Supplier not found';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server error occurred';
                }
                console.error('Error loading consignment items:', xhr);
                $('#convertToCashItemsLoading').html('<div class="alert alert-danger">' + errorMsg + '</div>');
            }
        });
    }

    // Select all checkbox
    $(document).on('change', '#selectAllCashItems', function() {
        $('.cash-item-checkbox').prop('checked', $(this).prop('checked'));
        updateConvertToCashButton();
    });

    // Individual checkbox change
    $(document).on('change', '.cash-item-checkbox', function() {
        updateConvertToCashButton();
        // Uncheck select all if any item is unchecked
        if (!$(this).prop('checked')) {
            $('#selectAllCashItems').prop('checked', false);
        } else {
            // Check if all items are checked
            var allChecked = $('.cash-item-checkbox').length === $('.cash-item-checkbox:checked').length;
            $('#selectAllCashItems').prop('checked', allChecked);
        }
    });

    function updateConvertToCashButton() {
        var hasSelection = $('.cash-item-checkbox:checked').length > 0;
        $('#confirmConvertToCashBtn').prop('disabled', !hasSelection);
    }

    // Confirm conversion
    $('#confirmConvertToCashBtn').on('click', function() {
        var selectedItems = [];
        $('.cash-item-checkbox:checked').each(function() {
            selectedItems.push($(this).data('item-id'));
        });

        if (selectedItems.length === 0) {
            alert('Please select at least one item to convert.');
            return;
        }

        if (!confirm('Are you sure you want to convert the selected items to Cash? A purchase record will be created for these items.')) {
            return;
        }

        $.ajax({
            url: '{{ url('/payment/consignment') }}/' + currentCashSupplierId + '/convert-to-cash',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                selected_items: selectedItems
            },
            success: function(response) {
                if (response.success) {
                    alert('Selected items successfully converted to Cash. A purchase record has been created.');
                    $('#convertToCashModal').modal('hide');
                    consignmentsTable.ajax.reload();
                } else {
                    alert('Error: ' + (response.message || 'Failed to convert items'));
                }
            },
            error: function(xhr) {
                var errorMsg = 'Error converting items';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.errors) {
                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                    }
                }
                alert(errorMsg);
            }
        });
    });
</script>
@endpush

