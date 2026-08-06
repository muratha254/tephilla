@extends('layouts.master')

@section('title')
    Purchase Orders
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Purchase Orders</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Multi-Item Purchase Orders</h3>
                <div class="box-tools pull-right">
                    <a href="{{ route('purchase-orders.receive-page') }}" class="btn btn-success btn-flat">
                        <i class="fa fa-truck"></i> Receive Items
                    </a>
                    <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary btn-flat">
                        <i class="fa fa-plus"></i> Create PO
                    </a>
                    <button class="btn btn-default btn-flat" id="btn-refresh-batches">
                        <i class="fa fa-refresh"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped" id="purchase-order-batches-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>PO Number</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Items</th>
                            <th>Total Ordered</th>
                            <th>Total Received</th>
                            <th>Remaining</th>
                            <th>Status</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="batch-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Purchase Order Details</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-sm-4">
                        <h5>PO Number</h5>
                        <p id="batch-po-number" class="text-bold">-</p>
                    </div>
                    <div class="col-sm-4">
                        <h5>Supplier</h5>
                        <p id="batch-supplier">-</p>
                    </div>
                    <div class="col-sm-4">
                        <h5>Status</h5>
                        <p id="batch-status">-</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-4">
                        <h5>Order Date</h5>
                        <p id="batch-order-date">-</p>
                    </div>
                    <div class="col-sm-4">
                        <h5>Reference</h5>
                        <p id="batch-reference">-</p>
                    </div>
                    <div class="col-sm-4">
                        <h5>Notes</h5>
                        <p id="batch-notes">-</p>
                    </div>
                </div>

                <h5>Items</h5>
                <div class="table-responsive">
                    <table class="table table-condensed table-striped" id="batch-items-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Ordered</th>
                                <th>Received</th>
                                <th>Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No items</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger btn-flat" id="batch-reject-btn">
                    <i class="fa fa-ban"></i> Reject
                </button>
                <a href="#" class="btn btn-success btn-flat" id="batch-receive-btn">
                    <i class="fa fa-truck"></i> Receive
                </a>
                <button type="button" class="btn btn-primary btn-flat" id="batch-print-btn">
                    <i class="fa fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let poBatchTable;
    let currentBatchDetail = null;
    const batchStatusUrlTemplate = '{{ route('purchase-orders.batches.status', ['batch' => '__batch__']) }}';
    const batchDetailUrlTemplate = '{{ route('purchase-orders.batches.show', ['batch' => '__batch__']) }}';
    const receivePageUrl = '{{ route('purchase-orders.receive-page') }}';
    const csrfToken = '{{ csrf_token() }}';

    $(function () {
        $('#btn-refresh-batches').on('click', function () {
            if (poBatchTable) {
                poBatchTable.ajax.reload(null, false);
            }
        });

        poBatchTable = $('#purchase-order-batches-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: '{{ route('purchase-orders.batches.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'po_number_display', name: 'po_number'},
                {data: 'order_date', name: 'order_date'},
                {data: 'supplier_name', name: 'supplier.nama'},
                {data: 'items_count', name: 'items_count', searchable: false},
                {data: 'total_quantity', name: 'total_quantity', searchable: false},
                {data: 'total_received', name: 'total_received', searchable: false},
                {data: 'remaining_quantity', name: 'remaining_quantity', searchable: false},
                {data: 'status_badge', name: 'status', orderable: false, searchable: false},
                {data: 'actions', orderable: false, searchable: false},
            ],
            order: [[2, 'desc']],
        });
    });
    const batchModal = $('#batch-modal');
    const batchItemsBody = $('#batch-items-table tbody');
    const batchReceiveBtn = $('#batch-receive-btn');
    const batchRejectBtn = $('#batch-reject-btn');
    const batchPrintBtn = $('#batch-print-btn');

    $(document).on('click', '.btn-view-batch', function () {
        const batchId = $(this).data('id');
        loadBatchDetail(batchId);
    });

    batchRejectBtn.on('click', function () {
        if (!currentBatchDetail) {
            return;
        }
        const batchId = currentBatchDetail.batch.id;
        const poNumber = currentBatchDetail.batch.po_number;

        if (!confirm(`Reject purchase order ${poNumber}? This cannot be undone.`)) {
            return;
        }

        const reason = prompt('Optional: enter a reason for rejection', '') || '';
        const button = $(this).prop('disabled', true);

        $.ajax({
            url: batchStatusUrlTemplate.replace('__batch__', batchId),
            method: 'POST',
            data: {
                _token: csrfToken,
                status: 'rejected',
                reason,
            },
        })
        .done(response => {
            alert(response.message || 'Purchase order rejected.');
            batchModal.modal('hide');
            if (poBatchTable) {
                poBatchTable.ajax.reload(null, false);
            }
        })
        .fail(xhr => {
            const message = xhr.responseJSON?.message ?? 'Unable to update purchase order.';
            alert(message);
        })
        .always(() => {
            button.prop('disabled', false);
        });
    });

    batchPrintBtn.on('click', function () {
        if (!currentBatchDetail) {
            return;
        }
        const { batch, items } = currentBatchDetail;
        const rows = items.map((item, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${item.product_name}</td>
                <td>${item.supplier_name || 'N/A'}</td>
                <td>${item.quantity}</td>
                <td>${item.received_quantity}</td>
                <td>${item.remaining_quantity}</td>
            </tr>
        `).join('');

        const printable = `
            <html>
                <head>
                    <title>${batch.po_number} - Purchase Order</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                        th { background: #f5f5f5; }
                        h2 { margin-bottom: 5px; }
                    </style>
                </head>
                <body>
                    <h2>Purchase Order ${batch.po_number}</h2>
                    <p><strong>Supplier:</strong> ${batch.supplier}</p>
                    <p><strong>Date:</strong> ${batch.order_date || '-'}</p>
                    <p><strong>Reference:</strong> ${batch.reference_number || '-'}</p>
                    <p><strong>Status:</strong> ${formatStatus(batch.status)}</p>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Ordered</th>
                                <th>Received</th>
                                <th>Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || '<tr><td colspan="5">No items</td></tr>'}
                        </tbody>
                    </table>
                    <p><strong>Notes:</strong> ${batch.notes || '-'}</p>
                </body>
            </html>
        `;

        const printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(printable);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    });

    function loadBatchDetail(batchId) {
        const url = batchDetailUrlTemplate.replace('__batch__', batchId);
        currentBatchDetail = null;
        batchModal.modal('show');
        batchModal.find('.modal-body').css('opacity', 0.5);

        $.get(url)
            .done(response => {
                currentBatchDetail = response;
                renderBatchModal(response);
            })
            .fail(() => {
                alert('Unable to load purchase order details.');
                batchModal.modal('hide');
            })
            .always(() => {
                batchModal.find('.modal-body').css('opacity', 1);
            });
    }

    function renderBatchModal(detail) {
        const { batch, items } = detail;
        const statusClassMap = {
            pending: 'label-warning',
            partially_received: 'label-info',
            completed: 'label-success',
            cancelled: 'label-default',
            rejected: 'label-danger',
        };
        const statusClass = statusClassMap[batch.status] ?? 'label-default';

        // Check if there are multiple suppliers
        const uniqueSuppliers = [...new Set(items.map(item => item.supplier_name || item.supplier_id).filter(s => s && s !== 'N/A'))];
        const hasMultipleSuppliers = uniqueSuppliers.length > 1;
        
        // Show/hide supplier field based on whether there are multiple suppliers
        const supplierRow = $('#batch-supplier').closest('.col-sm-4');
        if (hasMultipleSuppliers) {
            supplierRow.hide();
        } else {
            supplierRow.show();
            $('#batch-supplier').text(batch.supplier || (items.length > 0 && items[0].supplier_name ? items[0].supplier_name : '-'));
        }

        $('#batch-po-number').text(batch.po_number);
        $('#batch-status').html(`<span class="label ${statusClass}">${formatStatus(batch.status)}</span>`);
        $('#batch-order-date').text(batch.order_date || '-');
        $('#batch-reference').text(batch.reference_number || '-');
        $('#batch-notes').text(batch.notes || '-');

        if (!items.length) {
            batchItemsBody.html('<tr><td colspan="6" class="text-center text-muted">No items</td></tr>');
        } else {
            const rows = items.map((item, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.product_name}</td>
                    <td>${item.supplier_name || 'N/A'}</td>
                    <td>${numberFormat(item.quantity)}</td>
                    <td>${numberFormat(item.received_quantity)}</td>
                    <td>${numberFormat(item.remaining_quantity)}</td>
                </tr>
            `).join('');
            batchItemsBody.html(rows);
        }

        const canReceive = ['pending', 'partially_received'].includes(batch.status);
        batchReceiveBtn.toggle(canReceive);
        batchReceiveBtn.attr('href', `${receivePageUrl}?batch=${batch.id}`);
        batchRejectBtn.toggle(batch.status === 'pending');
    }

    function numberFormat(value) {
        const number = parseFloat(value ?? 0);
        return Number.isNaN(number) ? '0' : number.toLocaleString();
    }

    function formatStatus(status) {
        return status ? status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : 'Pending';
    }

</script>
@endpush

