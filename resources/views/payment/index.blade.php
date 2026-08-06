@extends('layouts.master')

@section('title')
    Payment list
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Payment List</li>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') }}">
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <button onclick="updatePeriode()" class="btn btn-primary btn-flat"><i class="fa fa-filter"></i> Filter Payments</button>
                <button class="btn btn-warning btn-flat" id="printButton">Print payment</button>
                @php($u = auth()->user())
                @if($u && $u->can_create)
                <button onclick="addForm()" class="btn btn-success btn-flat"><i class="fa fa-plus-circle"></i> Add New Payment</button>
                @endif
            </div>
            <div class="box-body table-responsive">
                <table id="yourDataTable" class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Date</th>
                        <th>Mop</th>
                        <th>Supplier</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Unique id</th>

                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                    <tfoot>
                        <tr>
                            <th colspan="6">Total Payment</th>
                            <th id="totalBayar"></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('payment.supplier')
@includeIf('payment.detail')
@includeIf('payment.details')
@includeIf('payment.form')
@endsection

@push('scripts')
<script src="{{ asset('/AdminLTE-2/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}"></script>

<script>
    let table, table1;
    let formattedTotalBayar;

    $(function () {
        table = $('#yourDataTable').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('payment.data') }}',
                data: function (d) {
                    d.reference_number = $('#reference_number').val();
                    d.supplier_id = $('#supplier_id').val();
                    d.start_date = $('#start_date').val();
                    d.end_date = $('#end_date').val();
                },
            },
            columns: [
                { data: 'DT_RowIndex', searchable: false, sortable: false },
                { data: 'tanggal' },
                { data: 'payment_method' },
                { data: 'supplier' },
                { data: 'telepon' },
                { data: 'total_harga' },
                { data: 'uniqid' },
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

                totalBayar = api.column(5).data().reduce(function (a, b) {
                    var numA = intVal(a);
                    var numB = intVal(b);
                    if (!isNaN(numA) && !isNaN(numB)) {
                        return numA + numB;
                    } else if (!isNaN(numA)) {
                        return numA;
                    } else if (!isNaN(numB)) {
                        return numB;
                    }
                    return 0;
                }, 0);

                formattedTotalBayar = totalBayar.toLocaleString('en-US');
                $('#totalBayar').html(formattedTotalBayar);
            }
        });

        // Initialize supplier table (will be populated via AJAX)
        let supplierTable;
        
        // Load suppliers with pending payments when modal opens
        $('#modal-supplier').on('shown.bs.modal', function() {
            // Use 'shown' event instead of 'show' to ensure modal is visible first
            // Load via AJAX asynchronously without blocking
            setTimeout(function() {
                try {
                    loadSuppliersWithPending();
                } catch(e) {
                    console.error('Error loading suppliers:', e);
                    // Ensure table is visible even if AJAX fails
                    $('#supplier-table').show();
                    $('#supplier-loading').hide();
                }
            }, 100);
        });
        
        table1 = $('.table-detail').DataTable({
            processing: true,
            bSort: false,
            dom: 'Brt',
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'nama_produk'},
                {data: 'price'},
                {data: 'balance'},
                {data: 'total'},
                
                
            ]
        });

        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true
        });
    });

    function redoPayment(url) {
        if (confirm('Are you sure you want to redo this payment?')) {
            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    '_token': $('[name=csrf-token]').attr('content'),
                    '_method': 'POST'
                },
                success: function(response) {
                    if(response.success) {
                        alert('Payment redone successfully');
                        table.ajax.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    alert('Error: ' + xhr.responseJSON.message);
                }
            });
        }
    }

    function deletePayment(url) {
        if (confirm('Are you sure you want to undo this payment?')) {
            $.ajax({
                url: url,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if(response.success) {
                        alert('Payment undone successfully');
                        table.ajax.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    alert('Error: ' + xhr.responseJSON.message);
                }
            });
        }
    }

    function showDetail(url) {
        $('#modal-detail').modal('show');

        table1.ajax.url(url);
        table1.ajax.reload();
    }

    function viewPaymentDetails(paymentId) {
        $('#modal-payment-details').modal('show');
        
        // Reset modal content
        $('#payment-details-loading').show();
        $('#payment-details-content').hide();
        $('#items-loading').show();
        $('#items-table-wrapper').hide();
        $('#items-empty').hide();
        $('#btn-export-payment-pdf').hide();
        $('#btn-export-payment-excel').hide();
        
        // Load payment details
        $.ajax({
            url: '{{ url("/payment") }}/' + paymentId + '/details',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#payment-details-loading').hide();
                
                if (response && response.payment && response.supplier) {
                    // Populate payment information
                    $('#payment-reference').text(response.payment.reference_number || 'N/A');
                    $('#payment-date').text(response.payment.date || 'N/A');
                    $('#payment-amount').text('Ksh ' + parseFloat(response.payment.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#payment-type').text(response.payment.type || 'Cash');
                    $('#payment-method').text(response.payment.payment_method || 'Cash');
                    
                    // Populate supplier information
                    $('#supplier-name').text(response.supplier.name || 'N/A');
                    $('#supplier-phone').text(response.supplier.phone || 'N/A');
                    $('#supplier-address').text(response.supplier.address || 'N/A');
                    
                    // Populate summary
                    $('#items-paid-count').text(response.total_items_paid || 0);
                    
                    // Load items
                    if (response.items && response.items.length > 0) {
                        loadPaymentItems(response.items, paymentId);
                    } else {
                        $('#items-loading').hide();
                        $('#items-empty').show();
                        $('#items-table-wrapper').hide();
                    }
                    
                    $('#payment-details-content').show();
                } else {
                    alert('Invalid response format. Please try again.');
                    $('#payment-details-loading').hide();
                    $('#payment-details-content').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading payment details:', xhr, status, error);
                $('#payment-details-loading').hide();
                
                let errorMsg = 'Unable to load payment details';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg += ': ' + xhr.responseJSON.message;
                } else if (xhr.status === 404) {
                    errorMsg += ': Payment not found';
                } else if (xhr.status === 500) {
                    errorMsg += ': Server error';
                }
                
                alert(errorMsg);
                $('#payment-details-content').show();
            }
        });
    }
    
    function loadPaymentItems(items, paymentId) {
        $('#items-loading').hide();
        $('#items-empty').hide();
        $('#items-tbody').empty();
        
        let totalPaid = 0;
        
        items.forEach(function(item, index) {
            totalPaid += parseFloat(item.amount_paid || 0);
            
            let row = '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + (item.product_code || 'N/A') + '</td>' +
                '<td>' + (item.product_name || 'N/A') + '</td>' +
                '<td>' + (item.quantity || 0) + '</td>' +
                '<td>Ksh ' + parseFloat(item.invoice_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>Ksh ' + parseFloat(item.amount_paid || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>Ksh ' + parseFloat(item.balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                '<td>' + (item.invoice_number || 'N/A') + '</td>' +
                '</tr>';
            
            $('#items-tbody').append(row);
        });
        
        $('#items-total').text('Ksh ' + totalPaid.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#items-table-wrapper').show();
        $('#btn-export-payment-pdf').attr('data-payment-id', paymentId).show();
        $('#btn-export-payment-excel').attr('data-payment-id', paymentId).show();
    }
    
    // Handle PDF export
    $(document).on('click', '#btn-export-payment-pdf', function() {
        let paymentId = $(this).attr('data-payment-id');
        if (paymentId) {
            window.open('{{ url("/payment") }}/' + paymentId + '/export-pdf', '_blank');
        }
    });
    
    // Handle Excel export
    $(document).on('click', '#btn-export-payment-excel', function() {
        let paymentId = $(this).attr('data-payment-id');
        if (paymentId) {
            window.location.href = '{{ url("/payment") }}/' + paymentId + '/export-excel';
        }
    });

    function addForm() {
        try {
            // Ensure modal opens immediately without waiting for AJAX
            $('#modal-supplier').modal('show');
            
            // Show the original table immediately (from blade)
            $('#supplier-loading').hide();
            $('#supplier-table').show();
            $('#supplier-empty').hide();
            
            // Initialize DataTable if not already done
            if ($('.table-supplier').length && !$.fn.DataTable.isDataTable('.table-supplier')) {
                supplierTable = $('.table-supplier').DataTable({
                    order: [[4, 'desc']],
                    pageLength: 10,
                    language: {
                        emptyTable: "No suppliers with pending payments"
                    }
                });
            }
        } catch(e) {
            console.error('Error opening modal:', e);
            // Still try to show modal even if there's an error
            $('#modal-supplier').modal('show');
        }
    }
    
    function loadSuppliersWithPending() {
        try {
            $('#supplier-loading').show();
            $('#supplier-empty').hide();
            // Don't hide the table initially - show it as fallback
            
            // Clear existing DataTable if it exists
            if (supplierTable && $.fn.DataTable.isDataTable('.table-supplier')) {
                try {
                    supplierTable.destroy();
                } catch(e) {
                    console.warn('Error destroying DataTable:', e);
                }
                supplierTable = null;
            }
            
            $.ajax({
            url: '{{ route("payment.suppliers.pending") }}',
            method: 'GET',
            success: function(response) {
                $('#supplier-loading').hide();
                
                if (response.success && response.data && response.data.length > 0) {
                    // Clear the tbody first
                    $('#supplier-tbody').empty();
                    
                    // Populate the table directly
                    response.data.forEach(function(supplier, index) {
                        let pendingAmount = parseFloat(supplier.pending_amount) || 0;
                        let badgeClass = pendingAmount > 0 ? 'label-warning' : 'label-success';
                        let pendingDisplay = 'Ksh ' + parseFloat(pendingAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        
                        let row = '<tr>' +
                            '<td width="5%">' + (index + 1) + '</td>' +
                            '<td>' + (supplier.nama || '') + '</td>' +
                            '<td>' + (supplier.telepon || '-') + '</td>' +
                            '<td>' + (supplier.alamat || '-') + '</td>' +
                            '<td><span class="label ' + badgeClass + '">' + pendingDisplay + '</span></td>' +
                            '<td><a href="{{ url("/payment") }}/' + supplier.id_supplier + '/create" class="btn btn-primary btn-xs btn-flat"><i class="fa fa-check-circle"></i> Select</a></td>' +
                            '</tr>';
                        
                        $('#supplier-tbody').append(row);
                    });
                    
                    // Initialize DataTable after populating
                    if (!supplierTable || !$.fn.DataTable.isDataTable('.table-supplier')) {
                        supplierTable = $('.table-supplier').DataTable({
                            order: [[4, 'desc']], // Sort by pending amount descending
                            pageLength: 10,
                            language: {
                                emptyTable: "No suppliers with pending payments"
                            }
                        });
                    }
                    
                    $('#supplier-table').show();
                } else {
                    // If response has data but it's empty, show empty message
                    // Otherwise, fallback to showing the original table from blade
                    if (response.data && response.data.length === 0) {
                        $('#supplier-empty').show();
                        $('#supplier-table').hide();
                    } else {
                        // Show the original blade-rendered table as fallback
                        $('#supplier-table').show();
                        if (!$.fn.DataTable.isDataTable('.table-supplier')) {
                            supplierTable = $('.table-supplier').DataTable({
                                order: [[4, 'desc']],
                                pageLength: 10
                            });
                        }
                    }
                }
            },
            error: function(xhr) {
                $('#supplier-loading').hide();
                console.error('Error loading suppliers:', xhr);
                
                // Always show the table as fallback - use original blade-rendered data
                $('#supplier-table').show();
                
                // Initialize DataTable if not already initialized
                if (!$.fn.DataTable.isDataTable('.table-supplier')) {
                    try {
                        supplierTable = $('.table-supplier').DataTable({
                            order: [[4, 'desc']],
                            pageLength: 10,
                            language: {
                                emptyTable: "No suppliers with pending payments"
                            }
                        });
                    } catch(e) {
                        console.error('Error initializing DataTable:', e);
                    }
                } else {
                    // Re-draw the existing table
                    try {
                        supplierTable.draw();
                    } catch(e) {
                        console.error('Error drawing DataTable:', e);
                    }
                }
            }
        });
        } catch(e) {
            console.error('Error in loadSuppliersWithPending:', e);
            $('#supplier-loading').hide();
            $('#supplier-table').show();
        }
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

    function updatePeriode() {
            $('#modal-form').modal('show');
        }

        $('#applyFilters').on('click', function () {
            $('#modal-form').modal('hide');
            table.ajax.reload();
        });

        $('#resetFilters').on('click', function () {
            $('#reference_number').val('');
            $('#supplier_id').val('');
            $('#start_date').val('');
            $('#end_date').val('');
            table.ajax.reload();
        });

        $('#printButton').on('click', function () {
            var startDate = $('#start_date').val() || '{{ date('Y-m-01') }}';
            var endDate = $('#end_date').val() || '{{ date('Y-m-d') }}';
            var url = '{{ route('payments.export-pdf') }}' + '?start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
            window.open(url, '_blank');
        });

</script>
@endpush
