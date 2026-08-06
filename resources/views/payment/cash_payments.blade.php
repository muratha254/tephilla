@extends('layouts.master')

@section('title')
    Cash Payments
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Cash Payments</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Cash Supplier Payments</h3>
            </div>
            <div class="box-body table-responsive">
                <table id="cashPaymentsTable" class="table table-stiped table-bordered table-hover">
                    <thead>
                        <th width="5%">#</th>
                        <th>Supplier Name</th>
                        <th>Phone</th>
                        <th>Outstanding Amount</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('payment.cash_payment_modal')
@endsection

@push('scripts')
<script>
    let cashPaymentsTable;

    $(function () {
        cashPaymentsTable = $('#cashPaymentsTable').DataTable({
            responsive: true,
            processing: true,
            serverSide: false,
            autoWidth: false,
            ajax: {
                url: '{{ route('payment.cash.suppliers.pending') }}',
                dataSrc: function(json) {
                    return json.data || [];
                }
            },
            columns: [
                {data: null, searchable: false, sortable: false, render: function(data, type, row, meta) {
                    return meta.row + 1;
                }},
                {data: 'nama'},
                {data: 'telepon'},
                {data: 'pending_amount', render: function(data) {
                    return 'Ksh ' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }},
                {data: null, searchable: false, sortable: false, render: function(data) {
                    return '<button type="button" onclick="openCashPaymentModal(' + data.id_supplier + ', \'' + data.nama + '\', ' + data.pending_amount + ')" class="btn btn-xs btn-success btn-flat"><i class="fa fa-money"></i> Pay</button>';
                }}
            ],
            order: [[3, 'desc']]
        });
    });

    function openCashPaymentModal(supplierId, supplierName, pendingAmount) {
        $('#cashPaymentModal').modal('show');
        $('#cashPaymentModal .modal-title').text('Pay ' + supplierName);
        $('#cashSupplierId').val(supplierId);
        $('#cashSupplierName').text(supplierName);
        $('#cashPendingAmount').text('Ksh ' + parseFloat(pendingAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#cashPaymentAmount').val(pendingAmount);
        $('#cashPaymentDate').val(new Date().toISOString().split('T')[0]);
        $('#cashPaymentItems').empty();
        $('#cashPaymentItems').append('<tr><td colspan="5" class="text-center">Loading...</td></tr>');

        // Load pending items
        $.ajax({
            url: '{{ url('/payment/cash') }}/' + supplierId + '/pending-items',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#cashPaymentItems').empty();
                if (response.success && response.pending_purchases && response.pending_purchases.length > 0) {
                    let totalOutstanding = 0;
                    response.pending_purchases.forEach(function(purchase) {
                        totalOutstanding += purchase.outstanding;
                        let row = '<tr>';
                        row += '<td><input type="checkbox" class="pembelian-checkbox" value="' + purchase.id_pembelian + '" data-outstanding="' + purchase.outstanding + '" checked></td>';
                        row += '<td>' + (purchase.purchase_date || 'N/A') + '</td>';
                        row += '<td>' + purchase.items.length + ' item(s)</td>';
                        row += '<td>Ksh ' + parseFloat(purchase.total_harga).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                        row += '<td>Ksh ' + parseFloat(purchase.bayar).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                        row += '<td>Ksh ' + parseFloat(purchase.outstanding).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                        row += '</tr>';
                        $('#cashPaymentItems').append(row);
                    });
                    $('#cashTotalOutstanding').text('Ksh ' + parseFloat(totalOutstanding).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    updateCashPaymentAmount();
                } else {
                    $('#cashPaymentItems').append('<tr><td colspan="6" class="text-center">No pending payments</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading pending items:', xhr.responseText);
                $('#cashPaymentItems').empty();
                let errorMsg = 'Error loading pending items';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#cashPaymentItems').append('<tr><td colspan="6" class="text-center text-danger">' + errorMsg + '</td></tr>');
            }
        });
    }

    function updateCashPaymentAmount() {
        let total = 0;
        $('.pembelian-checkbox:checked').each(function() {
            total += parseFloat($(this).data('outstanding'));
        });
        $('#cashPaymentAmount').val(total.toFixed(2));
    }

    $(document).on('change', '.pembelian-checkbox', function() {
        updateCashPaymentAmount();
    });

    $('#cashPaymentForm').on('submit', function(e) {
        e.preventDefault();
        
        let pembelianIds = [];
        $('.pembelian-checkbox:checked').each(function() {
            pembelianIds.push($(this).val());
        });

        if (pembelianIds.length === 0) {
            alert('Please select at least one purchase to pay');
            return;
        }

        let formData = {
            payment_amount: $('#cashPaymentAmount').val(),
            payment_date: $('#cashPaymentDate').val(),
            payment_method: $('#cashPaymentMethod').val(),
            pembelian_ids: pembelianIds,
            _token: '{{ csrf_token() }}'
        };

        let supplierId = $('#cashSupplierId').val();
        let submitBtn = $('#cashPaymentSubmitBtn');
        let originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: '{{ url('/payment/cash') }}/' + supplierId + '/process',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#cashPaymentModal').modal('hide');
                    cashPaymentsTable.ajax.reload();
                    
                    // Show success message with print option
                    if (confirm('Payment processed successfully!\n\nDo you want to print the payment receipt?')) {
                        window.open('{{ url('/payment/cash') }}/' + response.payment_id + '/export-pdf', '_blank');
                    }
                } else {
                    alert('Error: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Payment processing error:', xhr.responseText);
                let errorMsg = 'Error processing payment';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    } else if (xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.errors) {
                        let errors = [];
                        for (let field in xhr.responseJSON.errors) {
                            errors = errors.concat(xhr.responseJSON.errors[field]);
                        }
                        errorMsg = errors.join(' ');
                    }
                } else if (xhr.responseText) {
                    try {
                        let parsed = JSON.parse(xhr.responseText);
                        errorMsg = parsed.error || parsed.message || errorMsg;
                    } catch (e) {
                        // Keep default error message
                    }
                }
                alert(errorMsg);
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
</script>
@endpush

