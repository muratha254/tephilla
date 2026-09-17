@extends('layouts.fleet')

@section('title', 'Sales Invoice')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Sales Invoice',
    'backUrl' => route('sales.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Sales List', 'url' => route('sales.index')],
        ['label' => 'Sales Invoice'],
    ],
])

@php
    $money = function ($amount) {
        return 'Ksh ' . number_format((float) $amount, 2);
    };
    $qty = function ($amount) {
        $formatted = number_format((float) $amount, 4, '.', '');
        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    };
    $customer = $sale->customer;
    $company = $sale->company;
    $fromName = trim((optional($company)->name ?: ($companyName ?? '')) . ' ' . (optional($sale->branch)->name ?: ''));
    $fromAddress = trim(implode(', ', array_filter([
        optional($company)->address,
        optional($company)->city ? 'City:' . $company->city : null,
    ])));
    $qtyTotal = 0;
    $unitTotal = 0;
    $netTotal = 0;
    $taxTotal = 0;
    $discTotal = 0;
    $amountTotal = 0;
@endphp

<div class="sx-invoice sx-sale-invoice">
    <div class="sx-invoice-title">
        <h3><i class="fa fa-dot-circle-o"></i> Sales Invoice</h3>
        <div class="sx-invoice-date">Date: {{ optional($sale->sale_date)->format('d-m-Y H:i:s') }}</div>
    </div>

    <div class="row sx-invoice-parties">
        <div class="col-md-4">
            <div class="sx-invoice-label">From</div>
            <div class="sx-invoice-name">{{ $fromName }}</div>
            <div>{{ $fromAddress }}</div>
            <div>Phone: {{ optional($company)->phone }}</div>
            <div>Mobile: {{ optional($company)->phone }}</div>
            <div>Email: {{ optional($company)->email }}</div>
        </div>
        <div class="col-md-4">
            <div class="sx-invoice-label">Customer Details</div>
            <div class="sx-invoice-name">{{ $sale->customerDisplayName() === 'WALK-IN' ? 'WALK IN' : $sale->customerDisplayName() }}</div>
            <div>Mobile: {{ optional($customer)->is_walk_in ? '' : optional($customer)->phone }}</div>
            <div>Phone: {{ optional($customer)->is_walk_in ? '' : optional($customer)->phone }}</div>
            <div>Email: {{ optional($customer)->is_walk_in ? '' : optional($customer)->email }}</div>
        </div>
        <div class="col-md-4">
            <div>Invoice #{{ $sale->documentNumber() }}</div>
            <div>Sales Status: {{ $sale->statusLabel() }}</div>
            <div>Reference No.: {{ optional($sale->quotation)->number }}</div>
            <div>Sales Person: {{ optional($sale->cashier)->name }}</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered sx-invoice-items">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Unit price</th>
                    <th>Quantity</th>
                    <th>Net Cost</th>
                    <th>TAX</th>
                    <th>TAX Amt</th>
                    <th>Discount</th>
                    <th>Discount Amount</th>
                    <th>Unit Cost</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sale->items as $item)
                    @php
                        $quantity = (float) $item->quantity;
                        $taxAmt = (float) $item->tax_amount;
                        $discAmt = (float) $item->discount_amount;
                        $exclusive = round((float) $item->line_total - $taxAmt, 2);
                        $safeQty = $quantity > 0 ? $quantity : 1;
                        $unitExc = round($exclusive / $safeQty, 2);
                        $net = round($unitExc * $quantity, 2);
                        $rate = (float) $item->tax_rate;
                        $product = $item->product;
                        if ($rate > 0) {
                            $taxName = optional(optional($product)->tax)->name ?: 'VAT';
                            $inc = $product && $product->tax_inclusive ? 'Inc.' : 'Exc.';
                            $rateText = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
                            $taxText = $rateText . '% ' . $taxName . ' [' . $inc . ']';
                        } else {
                            $taxText = '-';
                        }
                        $qtyTotal += $quantity;
                        $unitTotal += $unitExc;
                        $netTotal += $net;
                        $taxTotal += $taxAmt;
                        $discTotal += $discAmt;
                        $amountTotal += $exclusive;
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->name }}</td>
                        <td>{{ $money($unitExc) }}</td>
                        <td>{{ $qty($quantity) }}</td>
                        <td>{{ $money($net) }}</td>
                        <td>{{ $taxText }}</td>
                        <td>{{ $money($taxAmt) }}</td>
                        <td>{{ $discAmt > 0 ? number_format($discAmt, 2) : '-' }}</td>
                        <td>{{ $discAmt > 0 ? $money($discAmt) : '-' }}</td>
                        <td>{{ $money($unitExc) }}</td>
                        <td>{{ $money($exclusive) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11">No items.</td></tr>
                @endforelse
            </tbody>
            @if($sale->items->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">Total</th>
                        <th>{{ $money($unitTotal) }}</th>
                        <th>{{ $qty($qtyTotal) }}</th>
                        <th>-</th>
                        <th>-</th>
                        <th>{{ $money($taxTotal) }}</th>
                        <th>-</th>
                        <th>{{ $money($discTotal) }}</th>
                        <th>{{ $money(0) }}</th>
                        <th>{{ $money($amountTotal) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="row sx-invoice-summary">
        <div class="col-md-6">
            <div class="sx-invoice-note-row">
                <span>Discount on All</span>
                <strong>: {{ number_format((float) $sale->discount_amount, 2) }} ({{ (float) $sale->discount_percent > 0 ? number_format((float) $sale->discount_percent, 2) . '%' : 'Fixed' }})</strong>
            </div>
            <div class="sx-invoice-note-row">
                <span>Note</span>
                <strong>: {{ $sale->notes }}</strong>
            </div>
        </div>
        <div class="col-md-6">
            <div class="sx-invoice-totals">
                <div><span>Subtotal</span><strong>{{ number_format($amountTotal, 2) }}</strong></div>
                <div><span>Other Charges</span><strong>0.00</strong></div>
                <div><span>Discount on All</span><strong>{{ number_format((float) $sale->discount_amount, 2) }}</strong></div>
                <div><span>Round Off</span><strong>{{ number_format((float) $sale->tax_amount, 2) }}</strong></div>
                <div class="sx-invoice-grand"><span>Grand Total</span><strong>{{ number_format((float) $sale->total, 2) }}</strong></div>
            </div>
        </div>
    </div>

    @if($sale->paymentPlan)
        <h4 class="sx-invoice-section">Payment Plan: {{ $sale->paymentPlan->typeLabel() }}</h4>
        <div class="table-responsive">
            <table class="table table-bordered sx-purple-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sale->paymentPlan->items as $row)
                        @php $rowLeft = $row->remainingAmount(); @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ optional($row->due_date)->format('d-m-Y') }}</td>
                            <td>{{ number_format((float) $row->amount, 2) }}</td>
                            <td>{{ number_format((float) $row->paid_amount, 2) }}</td>
                            <td>{{ number_format($rowLeft, 2) }}</td>
                            <td>{{ ucfirst($row->status) }}</td>
                            <td>
                                @if(!empty($canPlan) && $rowLeft > 0)
                                    <a href="#" class="sx-record-plan"
                                        data-url="{{ route('sales.payment-plan.pay', [$sale, $row]) }}"
                                        data-due="{{ optional($row->due_date)->format('d-m-Y') }}"
                                        data-amount="{{ number_format($rowLeft, 2, '.', '') }}">
                                        Record Payment
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">Total</th>
                        <th>{{ number_format((float) $sale->paymentPlan->items->sum('amount'), 2) }}</th>
                        <th>{{ number_format((float) $sale->paymentPlan->items->sum('paid_amount'), 2) }}</th>
                        <th>{{ number_format((float) $sale->paymentPlan->items->sum(function ($row) { return $row->remainingAmount(); }), 2) }}</th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <h4 class="sx-invoice-section">Payment Information:</h4>
    <div class="table-responsive">
        <table class="table table-bordered sx-purple-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Payment Type</th>
                    <th>Payment Note</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sale->payments as $payment)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ optional($payment->paid_at)->format('d-m-Y') ?: '-' }}</td>
                        <td>{{ ($paymentMethods[$payment->method] ?? ucfirst((string) $payment->method)) }}</td>
                        <td>{{ $payment->notes ?: ($payment->reference ?: '') }}</td>
                        <td>{{ number_format((float) $payment->amount, 2) }}</td>
                        <td>
                            <a href="#" class="sx-edit-payment"
                                data-url="{{ route('sales.payments.update', [$sale, $payment]) }}"
                                data-method="{{ $payment->method }}"
                                data-notes="{{ $payment->notes }}"
                                data-reference="{{ $payment->reference }}"
                                data-date="{{ optional($payment->paid_at)->toDateString() }}">
                                Edit <i class="fa fa-pencil"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="sx-none-found">No Record found</td></tr>
                @endforelse
            </tbody>
            @if($sale->payments->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">Total</th>
                        <th>{{ number_format((float) $sale->payments->sum('amount'), 2) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="sx-invoice-actions">
        @if(!empty($canUpdate))
            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#sx-change-details-modal">
                <i class="fa fa-check"></i> Change Sale Details
            </button>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#sx-apply-discount-modal">
                <i class="fa fa-check"></i> Apply Discount
            </button>
            <form method="post" action="{{ route('sales.remove-vat', $sale) }}" class="sx-inline-form" id="sx-remove-vat-form">
                @csrf
                <button type="submit" class="btn btn-default" @if((float) $sale->tax_amount <= 0) disabled @endif>
                    <i class="fa fa-check"></i> Remove VAT
                </button>
            </form>
        @endif
        <button type="button" class="btn btn-warning" onclick="window.print()">
            <i class="fa fa-print"></i> Print
        </button>
        @if(!empty($canPrint) && $sale->status === \App\Models\Sale::STATUS_COMPLETED)
            <a href="{{ route('sales.pdf', $sale) }}" class="btn btn-primary">
                <i class="fa fa-file-pdf-o"></i> PDF
            </a>
            <a href="{{ route('sales.pos-pdf', $sale) }}" class="btn btn-info">
                <i class="fa fa-print"></i> POS Invoice
            </a>
        @endif
        @if($sale->status === \App\Models\Sale::STATUS_COMPLETED && !empty($canPlan))
            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#sx-pay-plan-modal">
                <i class="fa fa-check"></i> Create Pay Plan
            </button>
        @endif
    </div>
</div>

<div class="modal fade" id="sx-change-details-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Change Sale Details</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="{{ route('sales.details.update', $sale) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label class="sx-req">Customer*</label>
                        <select name="customer_id" class="form-control" required>
                            @foreach($customers as $option)
                                <option value="{{ $option->id }}" @if((int) $sale->customer_id === (int) $option->id) selected @endif>
                                    {{ $option->is_walk_in ? 'WALK IN' : $option->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Sale Date*</label>
                        <input type="datetime-local" name="sale_date" class="form-control" required
                            value="{{ optional($sale->sale_date)->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="{{ optional($sale->due_date)->toDateString() }}">
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="notes" class="form-control" rows="2">{{ $sale->notes }}</textarea>
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

<div class="modal fade" id="sx-apply-discount-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Apply Discount</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="{{ route('sales.discount', $sale) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label class="sx-req">Discount Amount*</label>
                        <input type="number" step="0.01" min="0" max="{{ number_format((float) $sale->subtotal, 2, '.', '') }}" name="discount_amount" class="form-control" required value="{{ number_format((float) $sale->discount_amount, 2, '.', '') }}">
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

@php
    $plan = $sale->paymentPlan;
    $planTarget = $sale->remainingBalance() > 0 ? $sale->remainingBalance() : (float) $sale->total;
@endphp
<div class="modal fade" id="sx-pay-plan-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog sx-pay-plan-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Create Payment Plan</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="{{ route('sales.payment-plan.store', $sale) }}" id="sx-pay-plan-form">
                    @csrf
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Sales Person <sup class="sx-req">*</sup></label>
                                <select name="user_id" class="form-control" required>
                                    @foreach($salesPeople as $person)
                                        <option value="{{ $person->id }}" @if((int) optional($plan)->user_id === (int) $person->id || (! $plan && (int) auth()->id() === (int) $person->id)) selected @endif>
                                            {{ $person->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Instalment Amount <sup class="sx-req">*</sup> (amount e.g 50000)</label>
                                <input type="number" step="0.01" min="0.01" name="installment_amount" id="sx-plan-amount" class="form-control" placeholder="Instalment Amount" value="{{ $plan ? number_format((float) $plan->installment_amount, 2, '.', '') : '' }}">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Update Plan</label>
                                <div>
                                    <button type="button" class="btn btn-success" id="sx-plan-generate">
                                        <i class="fa fa-plus"></i> Generate
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <select name="type" id="sx-plan-type" class="form-control" required>
                                    <option value="">--Select Instalment Plan--</option>
                                    <option value="regular" @if(optional($plan)->type === 'regular') selected @endif>REGULAR PLAN</option>
                                    <option value="irregular" @if(optional($plan)->type === 'irregular') selected @endif>IRREGULAR PLAN</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date" id="sx-plan-due" class="form-control" value="{{ optional(optional($plan)->due_date)->toDateString() }}">
                            </div>
                        </div>
                    </div>

                    <div id="sx-irregular-box" hidden>
                        <table class="table table-bordered sx-gold-table" width="100%">
                            <thead>
                                <tr>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th class="sx-check-col"></th>
                                </tr>
                            </thead>
                            <tbody id="sx-irregular-rows"></tbody>
                        </table>
                        <button type="button" class="btn btn-default btn-sm" id="sx-irregular-add"><i class="fa fa-plus"></i> Add Instalment</button>
                    </div>

                    <div class="table-responsive" id="sx-plan-preview-wrap" hidden>
                        <table class="table table-bordered sx-gold-table" width="100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="sx-plan-preview-rows"></tbody>
                        </table>
                    </div>

                    <div class="sx-pay-plan-actions">
                        <button type="submit" class="btn btn-success">Generate Plan</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-record-plan-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Record Payment</h4>
            </div>
            <div class="modal-body">
                <p>Instalment due: <strong id="sx-record-plan-due">-</strong></p>
                <form method="post" action="#" id="sx-record-plan-form">
                    @csrf
                    <div class="form-group">
                        <label class="sx-req">Amount*</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="sx-record-plan-amount" class="form-control" required>
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

<div class="modal fade" id="sx-edit-payment-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Payment</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="#" id="sx-edit-payment-form">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label class="sx-req">Payment Type*</label>
                        <select name="method" id="sx-edit-pay-method" class="form-control" required>
                            @foreach($paymentMethods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Note</label>
                        <input type="text" name="notes" id="sx-edit-pay-notes" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Reference</label>
                        <input type="text" name="reference" id="sx-edit-pay-reference" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Payment Date*</label>
                        <input type="date" name="paid_at" id="sx-edit-pay-date" class="form-control" required>
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
@endsection

@push('scripts')
<script>
(function ($) {
    $('#sx-remove-vat-form').on('submit', function (e) {
        if (!window.Swal) {
            return window.confirm('Remove VAT from this sale?');
        }
        e.preventDefault();
        var form = this;
        Swal.fire({
            icon: 'warning',
            title: 'Remove VAT',
            text: 'VAT will be removed and the grand total will be reduced.',
            showCancelButton: true,
            confirmButtonColor: '#dd4b39',
            cancelButtonColor: '#00a65a',
            confirmButtonText: 'Remove VAT',
            cancelButtonText: 'Close'
        }).then(function (result) {
            if (result.isConfirmed) HTMLFormElement.prototype.submit.call(form);
        });
    });

    var planTarget = {{ json_encode(round($planTarget, 2)) }};

    function addIrregularRow(due, amount) {
        var row = '<tr>';
        row += '<td><input type="date" name="items[' + $('#sx-irregular-rows tr').length + '][due_date]" class="form-control" value="' + (due || '') + '"></td>';
        row += '<td><input type="number" step="0.01" min="0.01" name="items[' + $('#sx-irregular-rows tr').length + '][amount]" class="form-control" value="' + (amount || '') + '"></td>';
        row += '<td><button type="button" class="btn btn-danger btn-xs sx-irregular-remove"><i class="fa fa-times"></i></button></td>';
        row += '</tr>';
        $('#sx-irregular-rows').append(row);
    }

    function reindexIrregular() {
        $('#sx-irregular-rows tr').each(function (i) {
            $(this).find('input').each(function () {
                this.name = this.name.replace(/items\[\d+]/, 'items[' + i + ']');
            });
        });
    }

    function previewRegular() {
        var amount = parseFloat($('#sx-plan-amount').val() || '0');
        var due = $('#sx-plan-due').val();
        if (!(amount > 0) || !due) {
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Missing details', text: 'Enter an instalment amount and due date.' });
            }
            return;
        }
        var left = planTarget;
        var html = '';
        var n = 0;
        var date = new Date(due + 'T00:00:00');
        while (left > 0.009 && n < 60) {
            var take = Math.min(amount, left);
            n += 1;
            html += '<tr><td>' + n + '</td><td>' + date.toISOString().slice(0, 10) + '</td><td>' + take.toFixed(2) + '</td></tr>';
            left = Math.round((left - take) * 100) / 100;
            date.setMonth(date.getMonth() + 1);
        }
        $('#sx-plan-preview-rows').html(html);
        $('#sx-plan-preview-wrap').prop('hidden', false);
    }

    function togglePlanType() {
        var irregular = $('#sx-plan-type').val() === 'irregular';
        $('#sx-irregular-box').prop('hidden', !irregular);
        if (irregular && !$('#sx-irregular-rows tr').length) {
            addIrregularRow($('#sx-plan-due').val(), $('#sx-plan-amount').val());
        }
        if (!irregular) {
            $('#sx-plan-preview-wrap').prop('hidden', true);
        }
    }

    $('#sx-plan-type').on('change', togglePlanType);
    $('#sx-irregular-add').on('click', function () {
        addIrregularRow($('#sx-plan-due').val(), '');
    });
    $(document).on('click', '.sx-irregular-remove', function () {
        $(this).closest('tr').remove();
        reindexIrregular();
    });
    $('#sx-plan-generate').on('click', function () {
        if ($('#sx-plan-type').val() === 'irregular') {
            addIrregularRow($('#sx-plan-due').val(), $('#sx-plan-amount').val());
            return;
        }
        if ($('#sx-plan-type').val() !== 'regular') {
            if (window.Swal) {
                Swal.fire({ icon: 'warning', title: 'Select a plan', text: 'Choose REGULAR PLAN or IRREGULAR PLAN.' });
            }
            return;
        }
        previewRegular();
    });
    $('#sx-pay-plan-form').on('submit', function (e) {
        var type = $('#sx-plan-type').val();
        if (!type) {
            e.preventDefault();
            if (window.Swal) Swal.fire({ icon: 'warning', title: 'Select a plan', text: 'Choose REGULAR PLAN or IRREGULAR PLAN.' });
            return;
        }
        if (type === 'regular' && (!parseFloat($('#sx-plan-amount').val() || '0') || !$('#sx-plan-due').val())) {
            e.preventDefault();
            if (window.Swal) Swal.fire({ icon: 'warning', title: 'Missing details', text: 'Enter an instalment amount and due date.' });
        }
    });
    $('#sx-pay-plan-modal').on('shown.bs.modal', togglePlanType);

    $(document).on('click', '.sx-record-plan', function (e) {
        e.preventDefault();
        var amount = $(this).data('amount');
        $('#sx-record-plan-form').attr('action', $(this).data('url'));
        $('#sx-record-plan-due').text($(this).data('due') || '-');
        $('#sx-record-plan-amount').attr('max', amount).val(amount);
        $('#sx-record-plan-form')[0].reset();
        $('#sx-record-plan-amount').attr('max', amount).val(amount);
        $('#sx-record-plan-form').find('[name="paid_at"]').val('{{ now()->toDateString() }}');
        $('#sx-record-plan-modal').modal('show');
    });

    $(document).on('click', '.sx-edit-payment', function (e) {
        e.preventDefault();
        $('#sx-edit-payment-form').attr('action', $(this).data('url'));
        $('#sx-edit-pay-method').val($(this).data('method'));
        $('#sx-edit-pay-notes').val($(this).data('notes') || '');
        $('#sx-edit-pay-reference').val($(this).data('reference') || '');
        $('#sx-edit-pay-date').val($(this).data('date') || '');
        $('#sx-edit-payment-modal').modal('show');
    });
})(jQuery);
</script>
@endpush
