@extends('layouts.fleet')

@section('title', 'Invoices')

@push('css')
<link rel="stylesheet" href="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/css/dataTables.bootstrap.css') }}">
@endpush

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Invoices',
    'subtitle' => 'View/Search Invoices',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Invoices'],
    ],
])

<div class="sx-box sx-items-card">
    <div class="sx-items-toolbar">
        <h3 class="sx-box-title" style="flex:none;margin:0;">Invoice List</h3>
        <div class="sx-toolbar-actions">
            @if($canCreate)
                <a href="{{ route('sales.invoices.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New Invoice</a>
            @endif
        </div>
    </div>

    <div class="sx-box-body sx-items-body">
        <div class="sx-dt-row">
            <div id="inv-length"></div>
            <div class="sx-export-btns" id="inv-export">
                <button type="button" class="btn" data-export="copy">Copy</button>
                <button type="button" class="btn" data-export="excel">Excel</button>
                <button type="button" class="btn" data-export="pdf">PDF</button>
                <button type="button" class="btn" data-export="print">Print</button>
                <button type="button" class="btn" data-export="csv">CSV</button>
            </div>
            <div id="inv-search"></div>
        </div>

        <div class="table-responsive">
            <table id="inv-table" class="table table-bordered sx-gold-table" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Due Date</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Pay Status</th>
                        <th>Created by</th>
                        <th class="sx-no-export">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        @php
                            $payClass = [
                                'paid' => 'sx-pay-paid',
                                'partial' => 'sx-pay-partial',
                                'unpaid' => 'sx-pay-unpaid',
                            ][$invoice->payment_status] ?? 'sx-pay-unpaid';
                            $isFinal = $invoice->status === \App\Models\Sale::STATUS_COMPLETED;
                        @endphp
                        <tr>
                            <td class="inv-row-num"></td>
                            <td data-order="{{ optional($invoice->sale_date)->format('Y-m-d') }}">{{ optional($invoice->sale_date)->format('d-m-Y') }}</td>
                            <td>{{ $invoice->invoice_number ?: $invoice->documentNumber() }}</td>
                            <td>{{ $invoice->customerDisplayName() }}</td>
                            <td data-order="{{ optional($invoice->due_date)->format('Y-m-d') }}">{{ optional($invoice->due_date)->format('d-m-Y') ?: '-' }}</td>
                            <td data-order="{{ $invoice->total }}">{{ number_format((float) $invoice->total, 2) }}</td>
                            <td data-order="{{ $invoice->paid_amount }}">{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                            <td data-order="{{ $invoice->remainingBalance() }}">{{ number_format($invoice->remainingBalance(), 2) }}</td>
                            <td><span class="sx-pay-badge {{ $payClass }}">{{ $invoice->paymentStatusLabel() }}</span></td>
                            <td>{{ optional($invoice->cashier)->name ?: '-' }}</td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown">
                                        Action <span class="caret"></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right sx-action-menu">
                                        <li>
                                            <a href="{{ route('sales.show', $invoice) }}"><i class="fa fa-eye"></i> View</a>
                                        </li>
                                        @if($canPay && $isFinal && $invoice->remainingBalance() > 0)
                                            <li>
                                                <a href="#" class="sx-inv-pay"
                                                    data-url="{{ route('sales.payments.store', $invoice) }}"
                                                    data-balance="{{ number_format($invoice->remainingBalance(), 2, '.', '') }}"
                                                    data-number="{{ $invoice->invoice_number ?: $invoice->documentNumber() }}">
                                                    <i class="fa fa-money"></i> Payment
                                                </a>
                                            </li>
                                        @endif
                                        @if($canPrint && $isFinal)
                                            <li>
                                                <a href="{{ route('sales.pdf', $invoice) }}"><i class="fa fa-file-text-o"></i> Print</a>
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

@if($canPay)
<div class="modal fade" id="sx-inv-pay-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Record Payment</h4>
            </div>
            <div class="modal-body">
                <p>Invoice <strong id="sx-inv-pay-number">-</strong>. Balance due: <strong id="sx-inv-pay-balance">0.00</strong></p>
                <form method="post" action="#" id="sx-inv-pay-form">
                    @csrf
                    <input type="hidden" name="redirect" value="invoices">
                    <div class="form-group">
                        <label class="sx-req">Amount*</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="sx-inv-pay-amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Payment Type*</label>
                        <select name="method" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($paymentMethods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Note</label>
                        <input type="text" name="notes" class="form-control" placeholder="Cash payment">
                    </div>
                    <div class="form-group">
                        <label>Reference</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Payment Date*</label>
                        <input type="date" name="paid_at" class="form-control" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success">Submit</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE-2/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script>
(function ($) {
    var table = $('#inv-table').DataTable({
        pageLength: 25,
        order: [[1, 'desc']],
        columnDefs: [{ targets: [0, -1], orderable: false }],
        language: {
            lengthMenu: 'Show _MENU_ entries',
            search: 'Search:',
            zeroRecords: 'No matching invoices found',
            emptyTable: 'No data available in table',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            paginate: { previous: 'Previous', next: 'Next' }
        },
        drawCallback: function () {
            var api = this.api();
            var start = api.page.info().start;
            api.column(0, { page: 'current' }).nodes().each(function (cell, i) {
                cell.innerHTML = start + i + 1;
            });
        }
    });
    $('.sx-items-body .table-responsive').on('show.bs.dropdown', function () {
        $(this).css('overflow', 'visible');
    }).on('hide.bs.dropdown', function () {
        $(this).css('overflow', 'auto');
    });

    $(document).on('click', '.sx-inv-pay', function (e) {
        e.preventDefault();
        var balance = $(this).data('balance');
        $('#sx-inv-pay-form').attr('action', $(this).data('url'));
        $('#sx-inv-pay-number').text($(this).data('number') || '-');
        $('#sx-inv-pay-balance').text(balance);
        $('#sx-inv-pay-amount').attr('max', balance).val(balance);
        $('#sx-inv-pay-modal').modal('show');
    });

    $('#inv-length').append($('#inv-table_length'));
    $('#inv-search').append($('#inv-table_filter'));
    $('#inv-export [data-export]').on('click', function () {
        var type = $(this).data('export');
        if (type === 'print') {
            window.print();
            return;
        }
        if (type === 'copy') {
            var text = [];
            table.rows({ search: 'applied' }).every(function () {
                text.push($(this.node()).children().not(':last').map(function () { return $(this).text().trim(); }).get().join('\t'));
            });
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text.join('\n'));
            }
            return;
        }
        window.print();
    });
})(jQuery);
</script>
@endpush
