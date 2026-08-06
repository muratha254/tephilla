@extends('layouts.master')

@section('title')
    Cash Payment History
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Cash Payment History</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Cash Payment History</h3>
                <div class="box-tools">
                    <button id="btn-export-pdf" class="btn btn-success btn-sm"><i class="fa fa-file-pdf-o"></i> Export PDF</button>
                    <a href="{{ route('payment.cash') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back to Cash Payments</a>
                </div>
            </div>
            <div class="box-body">
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
                        <label>Payment Method</label>
                        <select id="paymentMethodFilter" class="form-control">
                            <option value="">All Methods</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="M-Pesa">M-Pesa</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>From Date</label>
                        <input type="date" id="startDateFilter" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label>To Date</label>
                        <input type="date" id="endDateFilter" class="form-control">
                    </div>
                    <div class="col-md-3" style="margin-top: 24px;">
                        <button id="applyFilters" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Apply</button>
                        <button id="resetFilters" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="cashPaymentHistoryTable" class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>Reference Number</th>
                                <th>Supplier</th>
                                <th>Phone</th>
                                <th>Amount</th>
                                <th>Payment Date</th>
                                <th>Payment Method</th>
                                <th>Purchases</th>
                                <th width="10%">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@includeIf('payment.cash_payment_details_modal')
@endsection

@push('scripts')
<script>
    let cashPaymentHistoryTable;

    $(function () {
        cashPaymentHistoryTable = $('#cashPaymentHistoryTable').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('payment.cash.history.data') }}',
                data: function (d) {
                    d.supplier_id = $('#supplierFilter').val();
                    d.payment_method = $('#paymentMethodFilter').val();
                    d.start_date = $('#startDateFilter').val();
                    d.end_date = $('#endDateFilter').val();
                }
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'reference_number'},
                {data: 'supplier_name'},
                {data: 'supplier_phone'},
                {data: 'amount', searchable: false},
                {data: 'date_formatted', searchable: false},
                {data: 'payment_method'},
                {data: 'pembelian_count', searchable: false, sortable: false},
                {data: 'aksi', searchable: false, sortable: false}
            ],
            order: [[5, 'desc']]
        });

        $('#applyFilters').on('click', function () {
            cashPaymentHistoryTable.ajax.reload();
        });

        $('#resetFilters').on('click', function () {
            $('#supplierFilter').val('');
            $('#paymentMethodFilter').val('');
            $('#startDateFilter').val('');
            $('#endDateFilter').val('');
            cashPaymentHistoryTable.ajax.reload();
        });

        $('#btn-export-pdf').on('click', function () {
            let url = '{{ route('payment.cash.history.export-pdf') }}?';
            let params = [];
            
            if ($('#supplierFilter').val()) {
                params.push('supplier_id=' + $('#supplierFilter').val());
            }
            if ($('#paymentMethodFilter').val()) {
                params.push('payment_method=' + $('#paymentMethodFilter').val());
            }
            if ($('#startDateFilter').val()) {
                params.push('start_date=' + $('#startDateFilter').val());
            }
            if ($('#endDateFilter').val()) {
                params.push('end_date=' + $('#endDateFilter').val());
            }
            
            window.open(url + params.join('&'), '_blank');
        });
    });

    function viewPaymentDetails(paymentId) {
        $('#cashPaymentDetailsModal').modal('show');
        $('#cashPaymentDetailsBody').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');

        $.get('{{ url('/payment/cash') }}/' + paymentId + '/details')
            .done(function(response) {
                if (response.success) {
                    let html = '<div class="row">';
                    html += '<div class="col-md-6"><strong>Reference Number:</strong> ' + response.payment.reference_number + '</div>';
                    html += '<div class="col-md-6"><strong>Payment Date:</strong> ' + response.payment.date + '</div>';
                    html += '</div><hr>';
                    html += '<div class="row">';
                    html += '<div class="col-md-6"><strong>Supplier:</strong> ' + response.payment.supplier_name + '</div>';
                    html += '<div class="col-md-6"><strong>Phone:</strong> ' + response.payment.supplier_phone + '</div>';
                    html += '</div><hr>';
                    html += '<div class="row">';
                    html += '<div class="col-md-6"><strong>Amount:</strong> Ksh ' + parseFloat(response.payment.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</div>';
                    html += '<div class="col-md-6"><strong>Payment Method:</strong> ' + response.payment.payment_method + '</div>';
                    html += '</div><hr>';
                    html += '<div class="row">';
                    html += '<div class="col-md-12"><strong>Created At:</strong> ' + response.payment.created_at + '</div>';
                    html += '</div><hr>';

                    if (response.pembelian_details && response.pembelian_details.length > 0) {
                        html += '<h4>Purchase Details</h4>';
                        html += '<div class="table-responsive">';
                        html += '<table class="table table-bordered table-striped">';
                        html += '<thead><tr><th>Purchase Date</th><th>Total Amount</th><th>Paid</th><th>Items</th></tr></thead>';
                        html += '<tbody>';
                        response.pembelian_details.forEach(function(pembelian) {
                            html += '<tr>';
                            html += '<td>' + pembelian.purchase_date + '</td>';
                            html += '<td>Ksh ' + parseFloat(pembelian.total_harga).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                            html += '<td>Ksh ' + parseFloat(pembelian.bayar).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
                            html += '<td>';
                            pembelian.items.forEach(function(item) {
                                html += item.product_name + ' (Qty: ' + item.quantity + ', Price: Ksh ' + parseFloat(item.unit_price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ')<br>';
                            });
                            html += '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                        html += '</div>';
                    }

                    $('#cashPaymentDetailsBody').html(html);
                } else {
                    $('#cashPaymentDetailsBody').html('<div class="alert alert-danger">Error loading payment details</div>');
                }
            })
            .fail(function(xhr) {
                let errorMsg = 'Error loading payment details';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                $('#cashPaymentDetailsBody').html('<div class="alert alert-danger">' + errorMsg + '</div>');
            });
    }
</script>
@endpush

