@extends('layouts.master')

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
<style>
    #supplierIdFilter + .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--single { min-height: 30px; padding-top: 2px; }
    #startDateFilter,
    #endDateFilter {
        min-height: 40px;
        font-size: 16px;
        padding: 8px 12px;
        line-height: 1.4;
    }
    #pending-filter-row .date-filter-wrap label {
        font-size: 13px;
        font-weight: 600;
    }
    #pending-filter-loading {
        display: none;
        margin: 0 0 12px;
    }
    #pending-filter-loading.is-active {
        display: block;
    }
    #pending-table-wrap.is-loading {
        opacity: 0.45;
        pointer-events: none;
    }
</style>
@endpush

@section('title')
    Pending Consignments
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Pending Consignments</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Pending Consignment Payments</h3>
                <div class="box-tools">
                    <button id="btn-export-pending-pdf" class="btn btn-danger btn-sm"><i class="fa fa-file-pdf-o"></i> Export to PDF</button>
                </div>
            </div>
            <div class="box-body">
                @include('payment.partials.missing_buying_price_fix')
                <div class="row" id="pending-filter-row" style="margin-bottom: 15px;">
                    <div class="col-md-3">
                        <label>Supplier</label>
                        <select id="supplierIdFilter" class="form-control input-sm" data-placeholder="All suppliers">
                            <option value=""></option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id_supplier }}">{{ $s->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 date-filter-wrap">
                        <label for="startDateFilter">From Date</label>
                        <input type="text" id="startDateFilter" class="form-control pending-datepicker" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="col-md-2 date-filter-wrap">
                        <label for="endDateFilter">To Date</label>
                        <input type="text" id="endDateFilter" class="form-control pending-datepicker" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="col-md-2" style="margin-top: 24px;">
                        <button id="applyFilters" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Apply Filters</button>
                        <button id="resetFilters" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</button>
                    </div>
                </div>
                <div id="unconfirmed-receipts-notice" class="alert alert-warning" style="display: none; margin-bottom: 15px;">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong><span id="unconfirmedReceiptsCount">0</span> unconfirmed receipt(s)</strong>
                    in the selected date range with consignment items awaiting confirmation on the sales list.
                    These lines will not appear here until confirmed.
                    <a href="#" id="unconfirmedReceiptsLink" class="alert-link" style="margin-left: 6px;">Open Sales List</a>
                </div>
            </div>
            <div class="box-body table-responsive" id="pending-table-wrap">
                <div id="pending-filter-loading">
                    <div class="box box-info" style="margin-bottom: 0;">
                        <div class="box-body text-center" style="padding: 16px;">
                            <i class="fa fa-refresh fa-spin fa-2x text-info"></i>
                            <p class="lead" style="margin-top: 12px; margin-bottom: 0; font-weight: bold;">Filtering…</p>
                        </div>
                    </div>
                </div>
                <table id="pendingTable" class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="3%"><input type="checkbox" id="selectAllRows"></th>
                        <th width="5%">#</th>
                        <th>Supplier Name</th>
                        <th>Phone</th>
                        <th>Total Pending</th>
                        <th>Items</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                    <tfoot>
                        <tr>
                            <th></th>
                            <th colspan="3">Total Pending</th>
                            <th id="totalPending"></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('supplier.consignment')
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script>
    let table;

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

    function getFilterStartDate() {
        return toYmd($('#startDateFilter').val());
    }

    function getFilterEndDate() {
        return toYmd($('#endDateFilter').val());
    }

    $(function () {
        $('.pending-datepicker').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true
        });

        $('#supplierIdFilter').select2({
            allowClear: true,
            placeholder: 'All suppliers',
            width: '100%'
        });

        table = $('#pendingTable').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            pageLength: 50,
            ajax: {
                url: '{{ route('payment.pending.data') }}',
                data: function (d) {
                    d.supplier_id = $('#supplierIdFilter').val() || '';
                    d.start_date = getFilterStartDate();
                    d.end_date = getFilterEndDate();
                }
            },
            columns: [
                { data: 'checkbox', searchable: false, sortable: false, orderable: false },
                { data: 'DT_RowIndex', searchable: false, sortable: false },
                { data: 'supplier_name' },
                { data: 'supplier_phone' },
                { data: 'total_pending', searchable: false },
                { data: 'item_count', searchable: false },
                { data: 'aksi', searchable: false, sortable: false },
            ],
            footerCallback: function (row, data, start, end, display) {
                var api = this.api(), data;
                var intVal = function (i) {
                    return typeof i === 'string' ?
                        i.replace(/[^\d.-]/g, '') * 1 :
                        typeof i === 'number' ?
                        i : 0;
                };

                totalPending = api.column(4).data().reduce(function (a, b) {
                    var numA = intVal(a);
                    var numB = intVal(b);
                    return numA + numB;
                }, 0);

                $('#totalPending').html('ksh ' + totalPending.toLocaleString('en-US'));
            },
        });

        var pendingFilterInProgress = false;
        function showPendingFilterLoading() {
            pendingFilterInProgress = true;
            $('#pending-filter-loading').addClass('is-active');
            $('#pending-table-wrap').addClass('is-loading');
            var $applyBtn = $('#applyFilters');
            var $resetBtn = $('#resetFilters');
            if (!$applyBtn.data('orig-html')) {
                $applyBtn.data('orig-html', $applyBtn.html());
            }
            if (!$resetBtn.data('orig-html')) {
                $resetBtn.data('orig-html', $resetBtn.html());
            }
            $applyBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Filtering…');
            $resetBtn.prop('disabled', true);
        }
        function hidePendingFilterLoading() {
            if (!pendingFilterInProgress) {
                return;
            }
            pendingFilterInProgress = false;
            $('#pending-filter-loading').removeClass('is-active');
            $('#pending-table-wrap').removeClass('is-loading');
            $('#applyFilters').prop('disabled', false).html($('#applyFilters').data('orig-html') || '<i class="fa fa-filter"></i> Apply Filters');
            $('#resetFilters').prop('disabled', false).html($('#resetFilters').data('orig-html') || '<i class="fa fa-refresh"></i> Reset');
        }
        table.on('preXhr.dt', showPendingFilterLoading);
        table.on('xhr.dt error.dt', hidePendingFilterLoading);
        
        $('#applyFilters').on('click', function () {
            table.ajax.reload();
        });

        $('#resetFilters').on('click', function () {
            $('#supplierIdFilter').val(null).trigger('change');
            $('#startDateFilter').val('');
            $('#endDateFilter').val('');
            $('#unconfirmed-receipts-notice').hide();
            table.ajax.reload();
        });

        $('#pendingTable').on('xhr.dt', function (e, settings, json) {
            const startDate = getFilterStartDate();
            const endDate = getFilterEndDate();
            const unconfirmed = json && typeof json.unconfirmed_receipts !== 'undefined'
                ? parseInt(json.unconfirmed_receipts, 10) || 0
                : 0;
            const $notice = $('#unconfirmed-receipts-notice');

            if (startDate || endDate) {
                if (unconfirmed > 0) {
                    $('#unconfirmedReceiptsCount').text(unconfirmed);
                    const params = new URLSearchParams();
                    if (startDate) params.set('start_date', startDate);
                    if (endDate) params.set('end_date', endDate);
                    $('#unconfirmedReceiptsLink').attr(
                        'href',
                        '{{ route('penjualan.index') }}' + (params.toString() ? '?' + params.toString() : '')
                    );
                    $notice.show();
                } else {
                    $notice.hide();
                }
            } else {
                $notice.hide();
            }
        });

        // Select all rows checkbox
        $('#selectAllRows').on('change', function() {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Handle individual checkbox changes
        $(document).on('change', '.row-checkbox', function() {
            // Update select all checkbox state
            const totalCheckboxes = $('.row-checkbox').length;
            const checkedCheckboxes = $('.row-checkbox:checked').length;
            $('#selectAllRows').prop('checked', totalCheckboxes === checkedCheckboxes);
        });

        // View pending items (with dates) before payment
        window.viewPendingConsignment = function(supplierId) {
            viewConsignment(supplierId);
        };

        $('#btn-export-pending-pdf').on('click', function () {
            const supplierId = $('#supplierIdFilter').val();
            const startDate = getFilterStartDate();
            const endDate = getFilterEndDate();
            const selectedIds = $('.row-checkbox:checked').map(function() {
                return $(this).data('supplier-id');
            }).get();
            if (selectedIds.length === 0 && !supplierId && !startDate && !endDate) {
                alert('Select a supplier, apply date filters, or select one or more rows to export.');
                return;
            }
            window.open(buildPendingExportUrl('{{ route('payment.pending.export-pdf') }}', selectedIds, supplierId), '_blank');
        });
    });
    
    function buildPendingExportUrl(baseUrl, supplierIds, supplierId) {
        const params = [];
        const startDate = getFilterStartDate();
        const endDate = getFilterEndDate();
        const sid = supplierId || $('#supplierIdFilter').val();

        if (supplierIds && supplierIds.length > 0) {
            supplierIds.forEach(function(id) {
                params.push('supplier_ids[]=' + encodeURIComponent(id));
            });
        } else if (sid) {
            params.push('supplier_id=' + encodeURIComponent(sid));
        }
        if (startDate) params.push('start_date=' + encodeURIComponent(startDate));
        if (endDate) params.push('end_date=' + encodeURIComponent(endDate));

        if (params.length) {
            return baseUrl + '?' + params.join('&');
        }
        return baseUrl;
    }

    function viewConsignment(supplierId, invoiceItemId = null, pendingOnly = false) {
        $('#modal-consignment').modal('show');
        
        // Reset modal content
        $('#consignment-loading').show();
        $('#consignment-content').hide();
        $('#paid-items-loading').show();
        $('#paid-items-table-wrapper').hide();
        $('#paid-items-empty').hide();
        $('#btn-export-pdf').hide();
        $('#btn-export-excel').hide();
        resetSelectedConsignment();
        
        // Load supplier consignment data - apply current page filters (date range) when viewing from Pending
        let url = '{{ url("/supplier") }}/' + supplierId + '/consignment';
        const params = [];
        if (invoiceItemId) {
            params.push('invoice_item_id=' + invoiceItemId);
        }
        params.push('pending_only=1');
        var startDate = getFilterStartDate();
        var endDate = getFilterEndDate();
        // Persist current filter range on modal so export buttons can use the same filtered scope.
        $('#modal-consignment').attr('data-export-start-date', startDate || '');
        $('#modal-consignment').attr('data-export-end-date', endDate || '');
        if (startDate) params.push('start_date=' + encodeURIComponent(startDate));
        if (endDate) params.push('end_date=' + encodeURIComponent(endDate));
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
                    // Populate supplier information
                    $('#consignment-supplier-name').text(response.supplier.nama || 'N/A');
                    $('#consignment-supplier-phone').text(response.supplier.telepon || 'N/A');
                    $('#consignment-supplier-address').text(response.supplier.alamat || 'N/A');
                    
                    // Hide total and paid rows (only show pending and status)
                    $('#consignment-total-row').hide();
                    $('#consignment-paid-row').hide();
                    
                    // Populate consignment summary (only pending and status are visible)
                    $('#consignment-total').text('Ksh ' + parseFloat(response.consignment.total || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#consignment-paid').text('Ksh ' + parseFloat(response.consignment.paid || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#consignment-pending').text('Ksh ' + parseFloat(response.consignment.pending || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    
                    // Update status badge - use status from server response
                    let status = response.consignment.status || 'Not paid';
                    let statusClass = 'default';
                    
                    // Map status to appropriate badge class
                    if (status.toLowerCase() === 'paid') {
                        statusClass = 'success';
                    } else if (status.toLowerCase() === 'partially paid') {
                        statusClass = 'warning';
                    } else {
                        // Not paid or any other status
                        statusClass = 'default';
                    }
                    
                    $('#consignment-status-badge').text(status).removeClass('label-default label-success label-warning label-danger').addClass('label-' + statusClass);
                    
                    renderSelectedConsignment(response.selected_item || null);

                    // Get ONLY PENDING items (items with balance > 0) - show dates items were bought
                    // The controller already filters pending_items to only include items with balance > 0
                    let pendingItems = [];
                    
                    // Use pending_items from response (already filtered by controller)
                    if (response.pending_items && response.pending_items.length > 0) {
                        pendingItems = response.pending_items;
                    } else if (response.all_items && response.all_items.length > 0) {
                        // Fallback: filter all_items to get only pending items (balance > 0)
                        pendingItems = response.all_items.filter(function(item) {
                            const balance = parseFloat(item.balance || 0);
                            return balance > 0;
                        });
                    }
                    
                    if (pendingItems && pendingItems.length > 0) {
                        loadPaidItems(pendingItems, supplierId);
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
    
    function loadPaidItems(items, supplierId) {
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
            totalAmount += parseFloat(item.total_amount || 0);
            
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
            
            let editBtn = (item.id) ? '<button type="button" class="btn btn-xs btn-primary btn-flat" onclick="editConsignmentItem(' + item.id + ')" title="Edit"><i class="fa fa-edit"></i> Edit</button>' : '';
            let row = '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + (item.invoice_number || 'N/A') + '</td>' +
                '<td>' + displayDate + '</td>' +
                '<td>' + (item.product_name || 'N/A') + (item.product_code ? ' (' + item.product_code + ')' : '') + '</td>' +
                '<td>' + (item.quantity || 0) + '</td>' +
                '<td>Ksh ' + buyingPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>Ksh ' + parseFloat(item.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>' + editBtn + '</td>' +
                '</tr>';
            
            $('#paid-items-tbody').append(row);
        });
        
        $('#paid-items-total').text('Ksh ' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#paid-items-table-wrapper').show();
        $('#btn-export-pdf').attr('data-supplier-id', supplierId).show();
        $('#btn-export-excel').attr('data-supplier-id', supplierId).show();
        $('#modal-consignment').attr('data-current-supplier-id', supplierId);
    }
    
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
                    var supplierId = $('#modal-consignment').attr('data-current-supplier-id');
                    if (supplierId) viewConsignment(supplierId);
                    table.ajax.reload();
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
    
    // Handle PDF export - export only pending items (what's shown in the view)
    $(document).on('click', '#btn-export-pdf', function() {
        let supplierId = $(this).attr('data-supplier-id');
        if (supplierId) {
            const startDate = $('#modal-consignment').attr('data-export-start-date') || getFilterStartDate();
            const endDate = $('#modal-consignment').attr('data-export-end-date') || getFilterEndDate();
            let exportUrl = '{{ url("/supplier") }}/' + supplierId + '/consignment/export-pdf?pending_only=1';
            if (startDate) {
                exportUrl += '&start_date=' + encodeURIComponent(startDate);
            }
            if (endDate) {
                exportUrl += '&end_date=' + encodeURIComponent(endDate);
            }
            window.open(exportUrl, '_blank');
        }
    });
    
    // Handle Excel export - export only pending items (what's shown in the view)
    $(document).on('click', '#btn-export-excel', function() {
        let supplierId = $(this).attr('data-supplier-id');
        if (supplierId) {
            const startDate = $('#modal-consignment').attr('data-export-start-date') || getFilterStartDate();
            const endDate = $('#modal-consignment').attr('data-export-end-date') || getFilterEndDate();
            let exportUrl = '{{ url("/supplier") }}/' + supplierId + '/consignment/export-excel?pending_only=1';
            if (startDate) {
                exportUrl += '&start_date=' + encodeURIComponent(startDate);
            }
            if (endDate) {
                exportUrl += '&end_date=' + encodeURIComponent(endDate);
            }
            window.location.href = exportUrl;
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

    function deleteConsignmentItems(supplierId) {
        if (!confirm('Are you sure you want to delete all pending consignment items for this supplier? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: '{{ url("/payment/pending") }}/' + supplierId,
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    table.ajax.reload();
                } else {
                    alert(response.message || 'Error deleting consignment items.');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error deleting consignment items.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 403) {
                    errorMsg = 'Unauthorized. Only administrators can delete consignment items.';
                } else if (xhr.status === 404) {
                    errorMsg = 'No pending items found to delete.';
                }
                alert(errorMsg);
            }
        });
    }
</script>
@endpush

