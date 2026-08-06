@extends('layouts.master')

@section('title')
    Receive Purchase Orders
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
    <li class="active">Receive</li>
@endsection

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
    .receive-table td,
    .receive-table th {
        vertical-align: middle !important;
    }
    .table-wrapper {
        min-height: 180px;
    }
    .receive-table input[type="number"] {
        min-width: 120px;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Receive Orders</h3>
                <div class="box-tools pull-right">
                    <a href="{{ route('purchase-orders.create') }}" class="btn btn-default btn-flat">
                        <i class="fa fa-plus"></i> Create PO
                    </a>
                </div>
            </div>
            <div class="box-body">
                <div class="alert alert-info" id="receive-alert" style="display:none;"></div>

                <div class="form-group">
                    <label for="batch_id">Select Purchase Order</label>
                    <select id="batch_id" class="form-control">
                        <option value="">Search PO number...</option>
                        @foreach($openBatches as $batch)
                            <option value="{{ $batch->id }}" {{ (string) ($selectedBatchId ?? '') === (string) $batch->id ? 'selected' : '' }}>
                                {{ $batch->po_number }} @if($batch->reference_number) (Ref: {{ $batch->reference_number }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="batch-summary" class="well well-sm" style="display:none;">
                    <div class="row">
                        <div class="col-sm-4">
                            <strong>PO Number:</strong>
                            <div id="summary-po-number">-</div>
                        </div>
                        <div class="col-sm-4">
                            <strong>Supplier:</strong>
                            <div id="summary-supplier">-</div>
                        </div>
                        <div class="col-sm-4">
                            <strong>Order Date:</strong>
                            <div id="summary-order-date">-</div>
                        </div>
                    </div>
                </div>

                <form id="receive-form">
                    @csrf
                    <div class="table-responsive table-wrapper">
                        <table class="table table-bordered table-striped receive-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Ordered Qty</th>
                                    <th>Received Qty</th>
                                    <th>Remaining Qty</th>
                                    <th width="18%">Receive Now</th>
                                    <th width="10%">Confirm</th>
                                </tr>
                            </thead>
                            <tbody id="receive-items-body">
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        Select a purchase order to load items.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label for="received_date">Received Date</label>
                                <input type="date" class="form-control" id="received_date" name="received_date" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-sm-8">
                            <div class="form-group">
                                <label for="receive-notes">Notes</label>
                                <textarea id="receive-notes" name="notes" class="form-control" rows="2" placeholder="Notes about this delivery (optional)"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-success btn-flat" id="btn-receive" disabled>
                            <i class="fa fa-truck"></i> Receive Items
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const batchShowUrlTemplate = '{{ route('purchase-orders.batches.show', ['batch' => '__batch__']) }}';
    const batchReceiveUrlTemplate = '{{ route('purchase-orders.batches.receive', ['batch' => '__batch__']) }}';
    const defaultBatchId = '{{ $selectedBatchId ?? '' }}';

    const batchSelect = $('#batch_id');
    const summaryBox = $('#batch-summary');
    const summaryFields = {
        po: $('#summary-po-number'),
        supplier: $('#summary-supplier'),
        date: $('#summary-order-date'),
    };
    const itemsBody = $('#receive-items-body');
    const receiveForm = $('#receive-form');
    const receiveAlert = $('#receive-alert');
    const receiveButton = $('#btn-receive');

    let currentBatchId = null;

    function showAlert(message, type = 'info') {
        receiveAlert
            .removeClass('alert-info alert-success alert-danger')
            .addClass('alert-' + type)
            .text(message)
            .slideDown();
        setTimeout(() => receiveAlert.slideUp(), 5000);
    }

    function renderItems(items) {
        if (!items.length) {
            itemsBody.html('<tr><td colspan="6" class="text-center text-muted">All items have been fully received.</td></tr>');
            receiveButton.prop('disabled', true);
            return;
        }

        const rows = items.map((item, index) => {
            const noStock = item.remaining_quantity <= 0;
            const qtyInput = noStock
                ? '<span class="text-muted">Completed</span>'
                : `
                    <input type="hidden" name="items[${index}][batch_item_id]" value="${item.id}">
                    <input type="number"
                        class="form-control input-sm receive-qty"
                        name="items[${index}][quantity]"
                        min="1"
                        max="${item.remaining_quantity}"
                        value="${item.remaining_quantity}"
                        readonly
                    >
                `;
            const checkboxCell = noStock
                ? '<span class="text-muted">—</span>'
                : `<input type="checkbox" class="receive-confirm" name="items[${index}][confirmed]" value="1">`;

            return `
                <tr data-item-id="${item.id}" data-remaining="${item.remaining_quantity}">
                    <td>${item.product_name}</td>
                    <td>${item.quantity}</td>
                    <td>${item.received_quantity}</td>
                    <td>${item.remaining_quantity}</td>
                    <td>${qtyInput}</td>
                    <td class="text-center">${checkboxCell}</td>
                </tr>
            `;
        }).join('');

        itemsBody.html(rows);
        if (itemsBody.find('.receive-qty').length === 0) {
            receiveButton.prop('disabled', true);
        } else {
            receiveButton.prop('disabled', false);
        }
    }

    function loadBatch(batchId) {
        if (!batchId) {
            summaryBox.hide();
            itemsBody.html('<tr><td colspan="6" class="text-center text-muted">Select a purchase order to load items.</td></tr>');
            receiveButton.prop('disabled', true);
            return;
        }

        receiveButton.prop('disabled', true);
        const url = batchShowUrlTemplate.replace('__batch__', batchId);
        $.get(url)
            .done(response => {
                currentBatchId = response.batch.id;
                summaryFields.po.text(response.batch.po_number);
                summaryFields.supplier.text(response.batch.supplier);
                summaryFields.date.text(response.batch.order_date || '-');
                summaryBox.slideDown();
                renderItems(response.items);
                receiveForm.attr('action', batchReceiveUrlTemplate.replace('__batch__', currentBatchId));
            })
            .fail(() => {
                showAlert('Unable to load purchase order details.', 'danger');
                itemsBody.html('<tr><td colspan="6" class="text-center text-danger">Failed to load items.</td></tr>');
            });
    }

    function toggleInputState(row, isChecked) {
        const input = row.find('.receive-qty');
        if (!input.length) {
            return;
        }
        if (isChecked) {
            const remaining = parseInt(row.data('remaining'), 10) || 1;
            input.prop('readonly', false).val(remaining);
        } else {
            const remaining = parseInt(row.data('remaining'), 10) || '';
            input.prop('readonly', true).val(remaining);
        }
    }

    $(function () {
        batchSelect.select2({
            placeholder: 'Search PO number...',
            allowClear: true,
            width: '100%',
        });

        batchSelect.on('change', function () {
            currentBatchId = null;
            loadBatch($(this).val());
        });

        if (defaultBatchId) {
            batchSelect.val(defaultBatchId).trigger('change');
        }

        $(document).on('change', '.receive-confirm', function () {
            const row = $(this).closest('tr');
            toggleInputState(row, $(this).is(':checked'));
        });

        receiveForm.on('submit', function (e) {
            e.preventDefault();
            if (!currentBatchId) {
                showAlert('Select a purchase order first.', 'danger');
                return;
            }

            const action = receiveForm.attr('action');
            const formData = receiveForm.serialize();
            receiveButton.prop('disabled', true).text('Saving...');

            $.ajax({
                url: action,
                method: 'POST',
                data: formData,
            })
            .done(response => {
                showAlert(response.message || 'Goods received successfully.', 'success');
                // Redirect to received orders page after a short delay
                setTimeout(() => {
                    window.location.href = '{{ route('purchase-orders.received.index') }}';
                }, 1000);
            })
            .fail(xhr => {
                let message = 'Unable to save receipt.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.errors) {
                        const firstError = Object.values(xhr.responseJSON.errors)[0];
                        if (firstError) {
                            message = Array.isArray(firstError) ? firstError[0] : firstError;
                        }
                    }
                }
                showAlert(message, 'danger');
            })
            .always(() => {
                receiveButton.prop('disabled', false).text('Receive Items');
            });
        });
    });
</script>
@endpush

