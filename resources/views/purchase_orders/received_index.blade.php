@extends('layouts.master')

@section('title')
    Received Orders
@endsection

@section('breadcrumb')
    @parent
    <li><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
    <li class="active">Received Orders</li>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Goods Received History</h3>
            </div>
            <div class="box-body">
                <!-- Search Form -->
                <form method="GET" action="{{ route('purchase-orders.received.index') }}" class="form-inline" style="margin-bottom: 20px;">
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="text" name="search" class="form-control" placeholder="Search by GRN, PO Number, Supplier, or Product..." value="{{ request('search') }}" style="min-width: 300px;">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="date" name="date_from" class="form-control" placeholder="Date From" value="{{ request('date_from') }}">
                    </div>
                    <div class="form-group" style="margin-right: 10px;">
                        <input type="date" name="date_to" class="form-control" placeholder="Date To" value="{{ request('date_to') }}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-flat">
                        <i class="fa fa-search"></i> Search
                    </button>
                    @if(request()->has('search') || request()->has('date_from') || request()->has('date_to'))
                        <a href="{{ route('purchase-orders.received.index') }}" class="btn btn-default btn-flat">
                            <i class="fa fa-times"></i> Clear
                        </a>
                    @endif
                </form>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>GRN Reference</th>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Received Date</th>
                            <th>Items</th>
                            <th>Notes</th>
                            <th width="10%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($receipts as $index => $receipt)
                            <tr>
                                <td>{{ $receipts->firstItem() + $index }}</td>
                                <td><strong>{{ $receipt->reference_number }}</strong></td>
                                <td>{{ $receipt->batch->po_number ?? 'N/A' }}</td>
                                <td>{{ optional($receipt->batch->supplier)->nama ?? 'N/A' }}</td>
                                <td>{{ optional($receipt->received_date)->format('Y-m-d') }}</td>
                                <td>
                                    <ul class="list-unstyled m-b-0">
                                        @foreach($receipt->items as $item)
                                            <li>
                                                {{ $item->product->nama_produk ?? 'N/A' }}
                                                <span class="text-muted">(+{{ $item->quantity }})</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td>{{ $receipt->notes ?? '-' }}</td>
                                <td class="text-center">
                                    <button type="button"
                                        class="btn btn-xs btn-info btn-flat btn-view-receipt"
                                        data-id="{{ $receipt->id }}">
                                        <i class="fa fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No goods received entries yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer text-right">
                {{ $receipts->links() }}
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="receipt-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Receipt Details</h4>
            </div>
            <div class="modal-body">
                <dl class="dl-horizontal">
                    <dt>GRN Reference</dt>
                    <dd id="modal-grn">-</dd>
                    <dt>PO Number</dt>
                    <dd id="modal-po">-</dd>
                    <dt>Supplier</dt>
                    <dd id="modal-supplier">-</dd>
                    <dt>Received Date</dt>
                    <dd id="modal-date">-</dd>
                </dl>
                <h5>Items</h5>
                <div class="table-responsive">
                    <table class="table table-condensed table-striped" id="modal-items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty Ordered</th>
                                <th>Qty Received</th>
                            </tr>
                        </thead>
                        <tbody id="modal-items"></tbody>
                    </table>
                </div>
                <h5>Notes</h5>
                <p id="modal-notes" class="text-muted">-</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-flat" id="btn-print-receipt">
                    <i class="fa fa-print"></i> Print
                </button>
                <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentReceiptPayload = null;
    const showUrlTemplate = '{{ route('purchase-orders.received.show', ['receipt' => '__id__']) }}';
    const modal = $('#receipt-modal');

    $(document).on('click', '.btn-view-receipt', function () {
        const receiptId = $(this).data('id');
        const url = showUrlTemplate.replace('__id__', receiptId);

        $(this).prop('disabled', true);

        $.get(url)
            .done(response => {
                currentReceiptPayload = response;
                $('#modal-grn').text(response.receipt.reference_number);
                $('#modal-po').text(response.receipt.po_number || '-');
                $('#modal-supplier').text(response.receipt.supplier || '-');
                $('#modal-date').text(response.receipt.received_date || '-');
                $('#modal-notes').text(response.receipt.notes || '-');

                const rows = response.items.map(item => `
                    <tr>
                        <td>${item.product}</td>
                        <td>${item.ordered_quantity ?? '-'}</td>
                        <td>${item.received_quantity ?? '-'}</td>
                    </tr>
                `).join('');
                $('#modal-items').html(rows || '<tr><td colspan="3" class="text-center text-muted">No items</td></tr>');

                modal.modal('show');
            })
            .fail(() => {
                alert('Unable to load receipt details.');
            })
            .always(() => {
                $(this).prop('disabled', false);
            });
    });

    $('#btn-print-receipt').on('click', function () {
        if (!currentReceiptPayload) {
            return;
        }

        const { receipt, items } = currentReceiptPayload;
        const rows = items.map(item => `
            <tr>
                <td>${item.product}</td>
                <td>${item.ordered_quantity ?? '-'}</td>
                <td>${item.received_quantity ?? '-'}</td>
            </tr>
        `).join('');

        const printableHtml = `
            <html>
            <head>
                <title>Goods Received ${receipt.reference_number}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h2 { margin-bottom: 5px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                    th { background: #f5f5f5; }
                </style>
            </head>
            <body>
                <h2>Goods Received Note</h2>
                <p><strong>GRN Reference:</strong> ${receipt.reference_number}</p>
                <p><strong>PO Number:</strong> ${receipt.po_number || '-'}</p>
                <p><strong>Supplier:</strong> ${receipt.supplier || '-'}</p>
                <p><strong>Received Date:</strong> ${receipt.received_date || '-'}</p>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty Ordered</th>
                            <th>Qty Received</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows || '<tr><td colspan="3">No items</td></tr>'}
                    </tbody>
                </table>
                <p><strong>Notes:</strong> ${receipt.notes || '-'}</p>
            </body>
            </html>
        `;

        const printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(printableHtml);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    });
</script>
@endpush

