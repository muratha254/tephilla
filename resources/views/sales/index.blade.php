@extends('layouts.fleet')

@section('title', 'Sales List')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sales List',
    'subtitle' => 'View/Search Invoice',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Sales List'],
    ],
])

<form method="get" action="{{ route('sales.index') }}" class="sx-filter-card sx-sales-filters" id="sx-sales-filter">
    <select name="period" class="form-control" onchange="this.form.submit()">
        @foreach($periodOptions as $value => $label)
            <option value="{{ $value }}" @if($period === $value) selected @endif>{{ $label }}</option>
        @endforeach
    </select>
    <select name="branch_id" class="form-control" onchange="this.form.submit()">
        <option value="">All Branches</option>
        @foreach($branches as $option)
            <option value="{{ $option->id }}" @if((int) $filterBranchId === (int) $option->id) selected @endif>
                {{ $option->name }}
            </option>
        @endforeach
    </select>
</form>

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;"><i class="fa fa-list-alt"></i> Merged List</h3>
        <div class="sx-toolbar-actions">
            @if($canCreate)
                <a href="{{ route('pos.index') }}" class="btn sx-btn-gold"><i class="fa fa-plus"></i> New Sale</a>
            @endif
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="sl-length"></div>
            <div id="sl-search"></div>
        </div>

        <div class="table-responsive">
            <table id="sl-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th class="sx-no-export sx-check-col"><input type="checkbox" id="sl-check-all"></th>
                        <th class="sx-no-export">SN</th>
                        <th>Date</th>
                        <th>Inv No</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Pay Status</th>
                        <th>Customer</th>
                        <th>Note</th>
                        <th>Branch</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales as $sale)
                        @php
                            $payClass = [
                                'paid' => 'sx-pay-paid',
                                'partial' => 'sx-pay-partial',
                                'unpaid' => 'sx-pay-unpaid',
                            ][$sale->payment_status] ?? 'sx-pay-unpaid';
                            $isFinal = $sale->status === \App\Models\Sale::STATUS_COMPLETED;
                            $isVoided = $sale->status === \App\Models\Sale::STATUS_VOIDED;
                        @endphp
                        <tr>
                            <td class="sx-check-col"><input type="checkbox" class="sl-row-check" value="{{ $sale->id }}"></td>
                            <td class="sx-row-num"></td>
                            <td data-order="{{ optional($sale->sale_date)->format('Y-m-d H:i:s') }}">
                                {{ optional($sale->sale_date)->format('d-m-Y H:i:s') }}
                            </td>
                            <td>{{ $sale->documentNumber() }}</td>
                            <td>
                                @if($isFinal)
                                    <span class="sx-status-final">Final <i class="fa fa-check"></i></span>
                                @elseif($isVoided)
                                    <span class="sx-status-voided">{{ $sale->statusLabel() }}</span>
                                @else
                                    {{ $sale->statusLabel() }}
                                @endif
                            </td>
                            <td>{{ optional($sale->cashier)->name ?: '-' }}</td>
                            <td data-order="{{ $sale->total }}">Ksh {{ number_format((float) $sale->total, 2) }}</td>
                            <td data-order="{{ $sale->paid_amount }}">Ksh {{ number_format((float) $sale->paid_amount, 2) }}</td>
                            <td data-order="{{ $sale->remainingBalance() }}">Ksh {{ number_format($sale->remainingBalance(), 2) }}</td>
                            <td><span class="sx-pay-badge {{ $payClass }}">{{ $sale->paymentStatusLabel() }}</span></td>
                            <td>{{ $sale->customerDisplayName() }}</td>
                            <td>{{ $sale->notes }}</td>
                            <td>{{ optional($sale->branch)->name ?: '-' }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li>
                                            <a href="{{ route('sales.show', $sale) }}"><i class="fa fa-eye"></i> View sale</a>
                                        </li>
                                        <li>
                                            <a href="#" class="sx-view-payments" data-url="{{ route('sales.payments', $sale) }}">
                                                <i class="fa fa-money"></i> View Payment
                                            </a>
                                        </li>
                                        @if($canPrint && $isFinal)
                                            <li>
                                                <a href="{{ route('sales.pos-pdf', $sale) }}">
                                                    <i class="fa fa-print"></i> POS Invoice
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{{ route('sales.pdf', $sale) }}">
                                                    <i class="fa fa-file-text-o"></i> A4 Invoice/Receipt
                                                </a>
                                            </li>
                                            <li>
                                                <a href="{{ route('sales.dispatch-pdf', $sale) }}">
                                                    <i class="fa fa-list"></i> Dispatch List
                                                </a>
                                            </li>
                                        @endif
                                        @if($canDelivery && $isFinal)
                                            <li>
                                                <a href="{{ route('sales.delivery-pdf', $sale) }}">
                                                    <i class="fa fa-truck"></i> A4 Delivery Note
                                                </a>
                                            </li>
                                        @endif
                                        @if($canReturn && $isFinal)
                                            <li>
                                                <a href="#" class="sx-sale-return" data-url="{{ route('sales.returns.form', $sale) }}">
                                                    <i class="fa fa-refresh"></i> Sales return
                                                </a>
                                            </li>
                                        @endif
                                        @if($canVoid && $isFinal)
                                            <li>
                                                <a href="#" class="sx-cancel-sale"
                                                    data-url="{{ route('sales.void', $sale) }}"
                                                    data-number="{{ $sale->documentNumber() }}"
                                                    data-date="{{ optional($sale->sale_date)->format('Y-m-d') }}">
                                                    <i class="fa fa-times"></i> Cancel Sale
                                                </a>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-sale-payments-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog sx-payments-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Payments</h4>
            </div>
            <div class="modal-body">
                <div class="row sx-payments-info">
                    <div class="col-sm-4">
                        <div class="sx-invoice-label">Customer Information</div>
                        <div class="sx-invoice-name" id="sx-sale-pay-customer">WALK IN</div>
                        <div>Mobile: <span id="sx-sale-pay-mobile"></span></div>
                        <div>Phone: <span id="sx-sale-pay-phone"></span></div>
                        <div>Email: <span id="sx-sale-pay-email"></span></div>
                    </div>
                    <div class="col-sm-4">
                        <div class="sx-invoice-label">Sales Information</div>
                        <div>Invoice #<span id="sx-sale-pay-number">-</span></div>
                        <div>Date: <span id="sx-sale-pay-date">-</span></div>
                        <div>Grand Total: <span id="sx-sale-pay-total">0.00</span></div>
                    </div>
                    <div class="col-sm-4">
                        <div>Paid Amount: <strong id="sx-sale-pay-paid">0.00</strong></div>
                        <div>Due Amount: <strong id="sx-sale-pay-due">0.00</strong></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered sx-gold-table" width="100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Payment Date</th>
                                <th>Payment</th>
                                <th>Payment Type</th>
                                <th>Payment Note</th>
                                <th>Created by</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="sx-sale-pay-rows">
                            <tr><td colspan="7">No payments found.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="sx-payments-close">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-sale-return-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog sx-return-dialog" role="document">
        <form method="post" class="modal-content sx-conversion-modal" id="sx-sale-return-form">
            @csrf
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-refresh"></i> Sales Return</h4>
            </div>
            <div class="modal-body">
                <p class="sx-return-warn">
                    This action is permanent!! NOTE: The items are returned back to stock upon submission.
                    Use the delete button to remove the item you don't want to return.
                </p>
                <div class="table-responsive">
                    <table class="table sx-return-items">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Sold Qty</th>
                                <th>Qty To Return</th>
                                <th>Good Condition?</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="sx-return-rows"></tbody>
                    </table>
                </div>
                <div class="row sx-return-meta">
                    <div class="col-sm-4">
                        <label>Receipt Ref No: <span class="sx-req">*</span></label>
                        <input type="text" name="receipt_ref" id="sx-return-ref" class="form-control" required readonly>
                    </div>
                    <div class="col-sm-4">
                        <label>Want to Refund?: <span class="sx-req">*</span></label>
                        <select name="want_refund" class="form-control" required>
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label>Narrative: <span class="sx-req">*</span></label>
                        <textarea name="notes" class="form-control" rows="2" required placeholder="Reason for Return"></textarea>
                    </div>
                </div>
                <div class="sx-return-actions">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">
                        <i class="fa fa-times"></i> Close
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-paper-plane"></i> Submit
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="sx-cancel-sale-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog sx-void-dialog" role="document">
        <form method="post" class="modal-content sx-conversion-modal" id="sx-cancel-sale-form">
            @csrf
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-times"></i> Cancel Sale</h4>
            </div>
            <div class="modal-body">
                <p class="sx-void-warn">
                    You are about to cancel the sale!! This action is permanent!! NOTE: The items are returned back to stock.
                </p>
                <div class="form-group">
                    <label>Invoice Date: <span class="sx-req">*</span></label>
                    <input type="date" name="invoice_date" id="sx-void-date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Receipt Ref No: <span class="sx-req">*</span></label>
                    <input type="text" name="receipt_ref" id="sx-void-ref" class="form-control" required readonly>
                </div>
                <div class="form-group">
                    <label>Want to Do Refund?: <span class="sx-req">*</span></label>
                    <select name="want_refund" class="form-control" required>
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Narrative: <span class="sx-req">*</span></label>
                    <textarea name="void_reason" class="form-control" rows="2" required placeholder="Reason for Cancellation"></textarea>
                </div>
                <div class="form-group sx-void-flag">
                    <label>
                        Flag The record?: <span class="sx-req">*</span>
                        <span class="sx-void-flag-note">(if flagged will not show on customer statement)</span>
                    </label>
                    <input type="checkbox" name="flagged" value="1">
                </div>
                <div class="sx-return-actions">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">
                        <i class="fa fa-times"></i> Close
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-paper-plane"></i> Submit
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var table = $('#sl-table').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[2, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, 1, 13], orderable: false, searchable: false }
        ],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Column search:',
            zeroRecords: 'No matching sales found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        initComplete: function () {
            var wrap = $(this.api().table().container());
            wrap.find('.dataTables_length').appendTo('#sl-length');
            wrap.find('.dataTables_filter').appendTo('#sl-search');
        },
        drawCallback: function () {
            var api = this.api();
            var start = api.page.info().start;
            api.column(1, { page: 'current' }).nodes().each(function (cell, i) {
                cell.innerHTML = (start + i + 1) + '.';
            });
        }
    });

    $('#sl-check-all').on('change', function () {
        $('.sl-row-check').prop('checked', this.checked);
    });

    $('.sx-items-body .table-responsive').on('show.bs.dropdown', function () {
        $(this).css('overflow', 'visible');
    }).on('hide.bs.dropdown', function () {
        $(this).css('overflow', 'auto');
    });

    $(document).on('click', '.sx-view-payments', function (e) {
        e.preventDefault();
        $('#sx-sale-pay-rows').html('<tr><td colspan="7">Loading...</td></tr>');
        $('#sx-sale-payments-modal').modal('show');
        $.ajax({
            url: $(this).data('url'),
            headers: { 'Accept': 'application/json' }
        }).done(function (data) {
            $('#sx-sale-pay-customer').text(data.customer_name || 'WALK IN');
            $('#sx-sale-pay-mobile').text(data.mobile || '');
            $('#sx-sale-pay-phone').text(data.phone || '');
            $('#sx-sale-pay-email').text(data.email || '');
            $('#sx-sale-pay-number').text(data.number || '-');
            $('#sx-sale-pay-date').text(data.date || '-');
            $('#sx-sale-pay-total').text(data.total || '0.00');
            $('#sx-sale-pay-paid').text(data.paid || '0.00');
            $('#sx-sale-pay-due').text(data.due || '0.00');
            if (!data.payments || !data.payments.length) {
                $('#sx-sale-pay-rows').html('<tr><td colspan="7">No payments found.</td></tr>');
                return;
            }
            var html = '';
            data.payments.forEach(function (row, i) {
                html += '<tr>';
                html += '<td>' + (i + 1) + '</td>';
                html += '<td>' + (row.date || '-') + '</td>';
                html += '<td>' + (row.amount || '0.00') + '</td>';
                html += '<td>' + (row.method || '-') + '</td>';
                html += '<td>' + (row.note || '') + '</td>';
                html += '<td>' + (row.user || '-') + '</td>';
                html += '<td class="sx-pay-row-actions">';
                if (row.pos_url) {
                    html += '<a href="' + row.pos_url + '" target="_blank" title="POS Print"><i class="fa fa-print"></i> POS Print</a> ';
                }
                if (row.a4_url) {
                    html += '<a href="' + row.a4_url + '" target="_blank" title="A4 Print"><i class="fa fa-file-text-o"></i> A4 Print</a> ';
                }
                if (row.delete_url) {
                    html += '<a href="#" class="sx-delete-payment" data-url="' + row.delete_url + '" title="Delete"><i class="fa fa-trash"></i></a>';
                }
                html += '</td></tr>';
            });
            $('#sx-sale-pay-rows').html(html);
        }).fail(function () {
            $('#sx-sale-pay-rows').html('<tr><td colspan="7">Could not load payments.</td></tr>');
        });
    });

    $(document).on('click', '.sx-delete-payment', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        var submit = function () {
            var form = $('<form method="post"></form>').attr('action', url);
            form.append($('<input type="hidden" name="_token">').val($('meta[name="csrf-token"]').attr('content')));
            form.append($('<input type="hidden" name="_method" value="DELETE">'));
            $('body').append(form);
            form.submit();
        };
        if (!window.Swal) {
            if (window.confirm('Delete this payment?')) submit();
            return;
        }
        Swal.fire({
            icon: 'warning',
            title: 'Delete Payment',
            text: 'This payment will be removed and the sale balance will be updated.',
            showCancelButton: true,
            confirmButtonColor: '#dd4b39',
            cancelButtonColor: '#00a65a',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Close'
        }).then(function (result) {
            if (result.isConfirmed) submit();
        });
    });

    function qtyLabel(value) {
        return String(value).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
    }

    $(document).on('click', '.sx-sale-return', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        $('#sx-return-rows').html('<tr><td colspan="5">Loading...</td></tr>');
        $('#sx-sale-return-form')[0].reset();
        $('#sx-sale-return-form').attr('action', '');
        $('#sx-sale-return-modal').modal('show');
        $.ajax({
            url: url,
            headers: { 'Accept': 'application/json' }
        }).done(function (data) {
            $('#sx-sale-return-form').attr('action', data.store_url || '');
            $('#sx-return-ref').val(data.number || '');
            if (!data.items || !data.items.length) {
                $('#sx-return-rows').html('<tr><td colspan="5">No returnable items left on this sale.</td></tr>');
                return;
            }
            var html = '';
            data.items.forEach(function (item, i) {
                var name = $('<div>').text(item.name || '-').html();
                html += '<tr>';
                html += '<td>' + name + '</td>';
                html += '<td><input type="text" class="form-control" value="' + qtyLabel(item.sold_qty) + '" readonly></td>';
                html += '<td>';
                html += '<input type="hidden" name="items[' + i + '][sale_item_id]" value="' + item.sale_item_id + '">';
                html += '<input type="number" name="items[' + i + '][quantity]" class="form-control" min="0" max="' + item.available + '" step="0.0001" placeholder="Qty To Return">';
                html += '</td>';
                html += '<td><select name="items[' + i + '][condition]" class="form-control">';
                html += '<option value="">Select</option><option value="yes">Yes</option><option value="no">No</option>';
                html += '</select></td>';
                html += '<td><button type="button" class="btn btn-link sx-return-remove" title="Remove"><i class="fa fa-trash"></i></button></td>';
                html += '</tr>';
            });
            $('#sx-return-rows').html(html);
        }).fail(function (xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not load sale items.';
            $('#sx-return-rows').html('<tr><td colspan="5">' + message + '</td></tr>');
        });
    });

    $(document).on('click', '.sx-return-remove', function () {
        $(this).closest('tr').remove();
        if (!$('#sx-return-rows tr').length) {
            $('#sx-return-rows').html('<tr><td colspan="5">No items selected for return.</td></tr>');
        }
    });

    $('#sx-sale-return-form').on('submit', function (e) {
        var hasQty = false;
        var missingCondition = false;
        $(this).find('input[name$="[quantity]"]').each(function () {
            var qty = parseFloat($(this).val() || '0');
            if (qty > 0) {
                hasQty = true;
                var condition = $(this).closest('tr').find('select[name$="[condition]"]').val();
                if (!condition) missingCondition = true;
            }
        });
        if (!$(this).attr('action') || !hasQty) {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Sales Return', text: 'Enter a quantity to return.' });
            }
            return false;
        }
        if (missingCondition) {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Sales Return', text: 'Select Good Condition for each item being returned.' });
            }
            return false;
        }
    });

    $(document).on('click', '.sx-cancel-sale', function (e) {
        e.preventDefault();
        var form = $('#sx-cancel-sale-form')[0];
        form.reset();
        form.action = $(this).data('url') || '';
        $('#sx-void-ref').val($(this).data('number') || '');
        $('#sx-void-date').val($(this).data('date') || '');
        $('#sx-cancel-sale-modal').modal('show');
    });
})(jQuery);
</script>
@endpush
