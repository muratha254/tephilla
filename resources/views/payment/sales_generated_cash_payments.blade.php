@extends('layouts.master')

@section('title')
    Sales Generated Cash Payments
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Sales Generated Cash Payments</li>
@endsection

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
<style>
    .select2-container--default .select2-selection--single {
        height: 34px;
        border: 1px solid #d2d6de;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 34px;
        padding-left: 12px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 32px;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Cash Payments Generated from Sales</h3>
                <div class="box-tools">
                    <button type="button" id="exportPdfBtn" class="btn btn-success btn-sm"><i class="fa fa-file-pdf-o"></i> Export PDF</button>
                    <a href="{{ route('payment.cash') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back to Cash Payments</a>
                </div>
            </div>
            <div class="box-body">
                @include('payment.partials.missing_buying_price_fix')
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-3">
                        <label>Supplier</label>
                        <select id="supplierFilter" class="form-control">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id_supplier }}">{{ $supplier->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="startDateFilter">From Date</label>
                        <input type="text" id="startDateFilter" class="form-control sales-generated-datepicker" autocomplete="off" placeholder="dd/mm/yyyy" value="{{ now()->startOfMonth()->format('d/m/Y') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="endDateFilter">To Date</label>
                        <input type="text" id="endDateFilter" class="form-control sales-generated-datepicker" autocomplete="off" placeholder="dd/mm/yyyy" value="{{ now()->format('d/m/Y') }}">
                    </div>
                    <div class="col-md-3" style="margin-top: 24px;">
                        <button id="applyFilters" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Apply</button>
                        <button id="resetFilters" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="salesGeneratedPaymentsTable" class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>Supplier</th>
                                <th>Purchase Date</th>
                                <th>Item Names</th>
                                <th>Shop Names</th>
                                <th>Quantity</th>
                                <th>Selling Price</th>
                                <th>Total Amount</th>
                                <th width="10%">Action</th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <th colspan="7" style="text-align:right">Total:</th>
                                <th id="totalAmountFooter"></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@includeIf('payment.convert_to_consignment_modal')

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>
<script>
    let salesGeneratedPaymentsTable;

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

    function formatDdMmYyyy(date) {
        var d = String(date.getDate()).padStart(2, '0');
        var m = String(date.getMonth() + 1).padStart(2, '0');
        return d + '/' + m + '/' + date.getFullYear();
    }

    function getFilterStartDate() {
        return toYmd($('#startDateFilter').val());
    }

    function getFilterEndDate() {
        return toYmd($('#endDateFilter').val());
    }

    $(function () {
        $('.sales-generated-datepicker').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true
        });

        // Initialize Select2 on supplier dropdown
        $('#supplierFilter').select2({
            placeholder: 'Search and select supplier...',
            allowClear: true,
            width: '100%'
        });
        
        salesGeneratedPaymentsTable = $('#salesGeneratedPaymentsTable').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('payment.cash.sales-generated.data') }}',
                data: function (d) {
                    d.supplier_id = $('#supplierFilter').val();
                    d.start_date = getFilterStartDate();
                    d.end_date = getFilterEndDate();
                },
                dataSrc: function(json) {
                    if (json.error) {
                        alert(json.error);
                    }
                    // Update total amount footer from server response
                    if (json.total_amount) {
                        $('#totalAmountFooter').html(json.total_amount);
                    }
                    return json.data;
                },
                error: function(xhr) {
                    var msg = 'Could not load cash payments. Try narrowing the date range.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    alert(msg);
                }
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'supplier_name'},
                {data: 'purchase_date', searchable: false},
                {data: 'item_names'},
                {data: 'shop_names'},
                {data: 'quantities', searchable: false},
                {data: 'selling_prices', searchable: false},
                {data: 'total_amount', searchable: false},
                {data: 'aksi', searchable: false, sortable: false}
            ],
            order: [[2, 'desc']],
            footerCallback: function (row, data, start, end, display) {
                var api = this.api();
                var total = api.column(7, {page: 'current'}).data().reduce(function (a, b) {
                    // Remove 'Ksh ' and commas, then parse
                    var num = parseFloat(b.replace(/[Ksh ,]/g, ''));
                    return a + num;
                }, 0);
                $('#totalAmountFooter').html('Ksh ' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            }
        });

        $('#applyFilters').on('click', function () {
            salesGeneratedPaymentsTable.ajax.reload();
        });

        $('#resetFilters').on('click', function () {
            $('#supplierFilter').val('').trigger('change');
            var now = new Date();
            var firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            $('#startDateFilter').val(formatDdMmYyyy(firstDay));
            $('#endDateFilter').val(formatDdMmYyyy(now));
            salesGeneratedPaymentsTable.ajax.reload();
        });

        $('#exportPdfBtn').on('click', function () {
            var supplierId = $('#supplierFilter').val() || '';
            var startDate = getFilterStartDate();
            var endDate = getFilterEndDate();
            
            var url = '{{ route('payment.cash.sales-generated.export-pdf') }}';
            url += '?supplier_id=' + encodeURIComponent(supplierId)
                + '&start_date=' + encodeURIComponent(startDate)
                + '&end_date=' + encodeURIComponent(endDate);
            
            window.open(url, '_blank');
        });
    });

    let currentSupplierId = null;
    let currentPembelianId = null;

    function convertToConsignment(supplierId, pembelianId) {
        currentSupplierId = supplierId;
        currentPembelianId = pembelianId;
        
        $('#convertToConsignmentModal').modal('show');
        $('#convertItemsLoading').show();
        $('#convertItemsContent').hide();
        $('#confirmConvertBtn').prop('disabled', true);
        $('#convertItemsTableBody').empty();

        // Load purchase details
        $.get('{{ url('/payment/cash/pembelian') }}/' + pembelianId + '/details')
            .done(function(response) {
                if (response && response.details) {
                    $('#convertSupplierName').text(response.supplier ? response.supplier.nama : 'N/A');
                    $('#convertPurchaseDate').text(response.purchasedate2 || 'N/A');
                    
                    let html = '';
                    response.details.forEach(function(detail, index) {
                        html += '<tr>';
                        html += '<td><input type="checkbox" class="item-checkbox" data-detail-id="' + detail.id_pembelian_detail + '" data-produk-id="' + (detail.produk ? detail.produk.id_produk : '') + '" data-quantity="' + detail.jumlah + '" data-price="' + detail.harga_beli + '"></td>';
                        html += '<td>' + (detail.produk ? detail.produk.nama_produk : 'Unknown') + '</td>';
                        html += '<td>' + detail.jumlah + '</td>';
                        html += '<td>Ksh ' + parseFloat(detail.harga_beli).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                        html += '<td>Ksh ' + parseFloat(detail.subtotal).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                        html += '</tr>';
                    });
                    $('#convertItemsTableBody').html(html);
                    
                    $('#convertItemsLoading').hide();
                    $('#convertItemsContent').show();
                } else {
                    $('#convertItemsLoading').html('<div class="alert alert-danger">Error loading purchase details</div>');
                }
            })
            .fail(function(xhr) {
                var errorMsg = 'Error loading purchase details';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMsg = 'Purchase not found';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server error occurred';
                }
                console.error('Error loading purchase details:', xhr);
                $('#convertItemsLoading').html('<div class="alert alert-danger">' + errorMsg + '</div>');
            });
    }

    // Select all checkbox
    $(document).on('change', '#selectAllItems', function() {
        $('.item-checkbox').prop('checked', $(this).prop('checked'));
        updateConvertButton();
    });

    // Individual checkbox change
    $(document).on('change', '.item-checkbox', function() {
        updateConvertButton();
        // Uncheck select all if any item is unchecked
        if (!$(this).prop('checked')) {
            $('#selectAllItems').prop('checked', false);
        } else {
            // Check if all items are checked
            var allChecked = $('.item-checkbox').length === $('.item-checkbox:checked').length;
            $('#selectAllItems').prop('checked', allChecked);
        }
    });

    function updateConvertButton() {
        var hasSelection = $('.item-checkbox:checked').length > 0;
        $('#confirmConvertBtn').prop('disabled', !hasSelection);
    }

    // Confirm conversion
    $('#confirmConvertBtn').on('click', function() {
        var selectedItems = [];
        $('.item-checkbox:checked').each(function() {
            selectedItems.push({
                detail_id: $(this).data('detail-id'),
                produk_id: $(this).data('produk-id'),
                quantity: $(this).data('quantity'),
                price: $(this).data('price')
            });
        });

        if (selectedItems.length === 0) {
            alert('Please select at least one item to convert.');
            return;
        }

        if (!confirm('Are you sure you want to convert the selected items to Consignment? An invoice will be created for these items.')) {
            return;
        }

        $.ajax({
            url: '{{ url('/payment/cash') }}/' + currentSupplierId + '/convert-to-consignment',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                pembelian_id: currentPembelianId,
                selected_items: selectedItems
            },
            success: function(response) {
                if (response.success) {
                    alert('Selected items successfully converted to Consignment. An invoice has been created.');
                    $('#convertToConsignmentModal').modal('hide');
                    salesGeneratedPaymentsTable.ajax.reload();
                } else {
                    alert('Error: ' + (response.message || 'Failed to convert items'));
                }
            },
            error: function(xhr) {
                var errorMsg = 'Error converting items';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    }
                } else if (xhr.status === 404) {
                    errorMsg = 'Route not found. Please check the server configuration.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Server error occurred. Please check the logs for details.';
                }
                console.error('Conversion error:', xhr);
                alert(errorMsg);
            }
        });
    });

    function deleteCashPurchase(pembelianId) {
        if (!confirm('Are you sure you want to delete this cash purchase? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: '{{ url("/payment/cash/pembelian") }}/' + pembelianId,
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    salesGeneratedPaymentsTable.ajax.reload();
                } else {
                    alert(response.message || 'Error deleting cash purchase.');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error deleting cash purchase.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 403) {
                    errorMsg = 'Unauthorized. Only administrators can delete cash purchases.';
                } else if (xhr.status === 404) {
                    errorMsg = 'Purchase not found.';
                }
                alert(errorMsg);
            }
        });
    }
</script>
@endpush

