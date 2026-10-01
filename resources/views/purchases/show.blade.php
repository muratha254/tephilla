@extends('layouts.fleet')

@section('title', 'Purchase Invoice')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Purchase Invoice',
    'backUrl' => route('purchases.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Purchase List', 'url' => route('purchases.index')],
        ['label' => 'Purchase Invoice'],
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
    $supplier = $purchase->supplier;
    $company = $company ?? $purchase->company;
    $branchName = optional($purchase->branch)->name ?: optional($branch)->name;
    $forName = trim(($company->name ?? $companyName ?? '') . ' ' . ($branchName ?: ''));
    $statusLabel = $purchaseStatuses[$purchase->status] ?? ucfirst((string) $purchase->status);
    $orderedTotal = 0;
    $receivedTotal = 0;
    $taxTotal = 0;
    $expenseTotal = 0;
    $discountTotal = 0;
    $lpTotal = 0;
    $invoiceTotal = 0;
@endphp

<div class="sx-invoice">
    <div class="sx-invoice-title">
        <h3><i class="fa fa-dot-circle-o"></i> Purchase Invoice</h3>
        <div class="sx-invoice-date">Date: {{ optional($purchase->order_date)->format('d-m-Y') }}</div>
    </div>

    <div class="row sx-invoice-parties">
        <div class="col-md-4">
            <div class="sx-invoice-label">For</div>
            <div class="sx-invoice-name">{{ $forName }}</div>
            <div>City: {{ optional($company)->city }}</div>
            <div>Phone No: {{ optional($company)->phone }}</div>
            <div>Alt. Phone:</div>
            <div>Email: {{ optional($company)->email }}</div>
            <div>KRA PIN: {{ optional($company)->tax_pin }}</div>
        </div>
        <div class="col-md-4">
            <div class="sx-invoice-label">Supplier Details</div>
            <div class="sx-invoice-name">{{ optional($supplier)->name }}</div>
            <div>Phone No: {{ optional($supplier)->phone }}</div>
            <div>Alt. Phone: {{ optional($supplier)->mobile }}</div>
            <div>Email: {{ optional($supplier)->email }}</div>
            <div>KRA Pin: {{ optional($supplier)->tax_number }}</div>
        </div>
        <div class="col-md-4">
            <div>Purchase Invoice #{{ $purchase->number }}</div>
            <div>
                Purchase Status: {{ $statusLabel }}
                @if(!empty($canUpdate))
                    <a href="#" class="sx-invoice-link" id="sx-change-status">Change</a>
                @endif
                @if(!empty($canReceive) && ! in_array($purchase->status, ['received', 'cancelled'], true))
                    <form method="post" action="{{ route('purchases.receive', $purchase) }}" style="display:inline-block;margin-left:8px;">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">Receive goods</button>
                    </form>
                @endif
            </div>
            <div>Purchase Ref.: {{ $purchase->reference_no }}</div>
            <div>CU NO.: {{ $purchase->cu_number }}</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered sx-invoice-items">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Purchase Price</th>
                    <th>Ordered Qty</th>
                    <th>Received Qty</th>
                    <th>Tax</th>
                    <th>Tax Amt</th>
                    <th>Expense</th>
                    <th>Discount</th>
                    <th>Unit Cost (pur)</th>
                    <th>LP Total</th>
                    <th>Invoice Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchase->items as $item)
                    @php
                        $ordered = (float) $item->quantity;
                        $received = (float) $item->quantity_received;
                        $cost = (float) $item->unit_cost;
                        $lineLp = round($ordered * $cost, 2);
                        $lineTax = (float) $item->tax_amount;
                        $lineDisc = (float) $item->discount_amount;
                        $lineExp = 0;
                        $lineInv = (float) $item->line_total;
                        $orderedTotal += $ordered;
                        $receivedTotal += $received;
                        $taxTotal += $lineTax;
                        $expenseTotal += $lineExp;
                        $discountTotal += $lineDisc;
                        $lpTotal += $lineLp;
                        $invoiceTotal += $lineInv;
                        $product = $item->product;
                        $taxRate = (float) optional(optional($product)->tax)->rate;
                        if ($product && $taxRate > 0) {
                            $taxText = $product->taxLabel();
                        } else {
                            $inc = $product && $product->tax_inclusive ? 'Inc.' : 'Exc.';
                            $taxText = 'Tax Exempt [' . $inc . ']';
                        }
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}.</td>
                        <td>{{ optional($product)->name ?: $item->description }}</td>
                        <td>
                            {{ $money($cost) }}
                            <div class="sx-recv-price">Receiving Price: {{ number_format((float) $item->selling_price, 2) }}</div>
                        </td>
                        <td>{{ $qty($ordered) }}</td>
                        <td>{{ $qty($received) }}</td>
                        <td>{{ $taxText }}</td>
                        <td>{{ $money($lineTax) }}</td>
                        <td>{{ $money($lineExp) }}</td>
                        <td>{{ $lineDisc > 0 ? $money($lineDisc) : '-' }}</td>
                        <td>{{ $money($cost) }}</td>
                        <td>{{ number_format($lineLp, 2) }}</td>
                        <td>{{ number_format($lineInv, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12">No items.</td></tr>
                @endforelse
            </tbody>
            @if($purchase->items->isNotEmpty())
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">Total</th>
                        <th>{{ $qty($orderedTotal) }}</th>
                        <th>{{ $qty($receivedTotal) }}</th>
                        <th>-</th>
                        <th>{{ $money($taxTotal) }}</th>
                        <th>{{ $money($expenseTotal) }}</th>
                        <th>{{ $money($discountTotal) }}</th>
                        <th></th>
                        <th>{{ $money($lpTotal) }}</th>
                        <th>{{ $money($invoiceTotal) }}</th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="row sx-invoice-summary">
        <div class="col-md-6">
            <div class="sx-invoice-note-row">
                <span>Discount on All</span>
                <strong>: {{ number_format((float) $purchase->discount_amount, 2) }} (Fixed)</strong>
            </div>
            <div class="sx-invoice-note-row">
                <span>Note</span>
                <strong>: {{ $purchase->notes }}</strong>
            </div>
        </div>
        <div class="col-md-6">
            <div class="sx-invoice-totals">
                <div><span>Subtotal</span><strong>{{ $money($purchase->subtotal) }}</strong></div>
                <div><span>Discount on all</span><strong>{{ $money($purchase->discount_amount) }}</strong></div>
                <div><span>Round Off</span><strong>{{ $money($purchase->round_off) }}</strong></div>
                <div class="sx-invoice-grand"><span>Grand Total</span><strong>{{ $money($purchase->total) }}</strong></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <h4 class="sx-invoice-section">
                Payment Information:
                @if(!empty($canPay) && $purchase->balance() > 0)
                    <button type="button" class="btn btn-success btn-xs" id="sx-add-payment">Add Payment</button>
                @endif
            </h4>
            <div class="table-responsive">
                <table class="table table-bordered sx-purple-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Payment Type</th>
                            <th>Payment Note</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchase->payments as $payment)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ optional($payment->paid_at)->format('d-m-Y') ?: '-' }}</td>
                                <td>{{ $paymentMethods[$payment->method] ?? ucfirst((string) $payment->method) }}</td>
                                <td>{{ $payment->notes }}</td>
                                <td>{{ $money($payment->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="sx-none-found">No Record found</td></tr>
                        @endforelse
                    </tbody>
                    @if($purchase->payments->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">Total</th>
                                <th>{{ $money($purchase->payments->sum('amount')) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <h4 class="sx-invoice-section">Goods Received Note:</h4>
    <div class="table-responsive">
        <table class="table table-bordered sx-purple-table">
            <thead>
                <tr>
                    <th>Print</th>
                    <th>Receiving Date</th>
                    <th>Received By</th>
                    <th>Serial No</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchase->receipts as $receipt)
                    @php
                        $grnValue = $receipt->items->sum(function ($row) {
                            return (float) $row->quantity_received * (float) $row->unit_cost;
                        });
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('purchases.grn.print', ['purchase' => $purchase, 'receipt' => $receipt]) }}" target="_blank" title="Print GRN">
                                <i class="fa fa-print"></i>
                            </a>
                        </td>
                        <td>{{ optional($receipt->received_at)->format('d-m-Y') }}</td>
                        <td>{{ optional($receipt->user)->name ?: '-' }}</td>
                        <td>{{ $receipt->number }}</td>
                        <td>{{ $money($grnValue) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="sx-none-found">No Record found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="sx-invoice-actions">
        <button type="button" class="btn btn-success" id="sx-change-pur">
            <i class="fa fa-check"></i> Change Pur Details
        </button>
        <form method="post" action="{{ route('products.labels.print') }}" target="_blank" class="sx-inline-form">
            @csrf
            <input type="hidden" name="auto_print" value="1">
            @foreach($purchase->items as $item)
                @if($item->product_id)
                    <input type="hidden" name="items[{{ $item->product_id }}]" value="{{ max(1, (int) $item->quantity) }}">
                @endif
            @endforeach
            <button type="submit" class="btn btn-primary" @if($purchase->items->isEmpty()) disabled @endif>
                <i class="fa fa-barcode"></i> Barcode
            </button>
        </form>
        <a href="{{ route('purchases.print', ['purchase' => $purchase, 'mode' => 'po']) }}" target="_blank" class="btn btn-primary">
            <i class="fa fa-file-pdf-o"></i> PDF
        </a>
    </div>
</div>

<div class="modal fade" id="sx-change-status-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Change Order Status</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="{{ route('purchases.status.update', $purchase) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label class="sx-req">Status*</label>
                        <select name="status" class="form-control" required>
                            @foreach($purchaseStatuses as $value => $label)
                                <option value="{{ $value }}" @if((string) old('status', $purchase->status) === (string) $value) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($purchase->status !== 'received')
                        <p class="help-block">Setting status to <strong>Received</strong> will add stock and create a Goods Received Note.</p>
                    @else
                        <p class="help-block">This purchase is already received. Status cannot be reversed.</p>
                    @endif
                    <div class="sx-form-actions">
                        <button type="submit" class="btn btn-success" @if($purchase->status === 'received') disabled @endif>Submit</button>
                        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sx-add-payment-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Complete Supplier Payment</h4>
            </div>
            <div class="modal-body">
                <div class="sx-po-pay-meta">
                    <div><span>Total</span> <strong>Ksh {{ number_format((float) $purchase->total, 2) }}</strong></div>
                    <div><span>Paid</span> <strong>Ksh {{ number_format((float) $purchase->paid_amount, 2) }}</strong></div>
                    <div><span>Balance</span> <strong>Ksh {{ number_format($purchase->balance(), 2) }}</strong></div>
                </div>
                <form method="post" action="{{ route('purchases.payments.store', $purchase) }}">
                    @csrf
                    <div class="form-group">
                        <label class="sx-req">Amount*</label>
                        <input type="number" step="0.01" min="0.01" max="{{ number_format($purchase->balance(), 2, '.', '') }}" name="amount" class="form-control" required value="{{ old('amount', number_format($purchase->balance(), 2, '.', '')) }}">
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Payment Type*</label>
                        <select name="method" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($paymentMethods as $value => $label)
                                <option value="{{ $value }}" @if(old('method') === $value) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Voucher No</label>
                        <input type="text" name="reference" class="form-control" placeholder="Voucher No" value="{{ old('reference') }}">
                    </div>
                    <div class="form-group">
                        <label>Payment Date*</label>
                        <input type="date" name="paid_at" class="form-control" required value="{{ old('paid_at', now()->toDateString()) }}">
                    </div>
                    <div class="form-group">
                        <label>Payment Note</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
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

<div class="modal fade" id="sx-change-details-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content sx-conversion-modal">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Change Details</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="{{ route('purchases.details.update', $purchase) }}" id="sx-change-details-form">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label class="sx-req">Supplier*</label>
                        <select name="supplier_id" class="form-control" required>
                            <option value="">-Select-</option>
                            @foreach($suppliers as $option)
                                <option value="{{ $option->id }}" @if((string) old('supplier_id', $purchase->supplier_id) === (string) $option->id) selected @endif>
                                    {{ trim($option->name . ' ' . ($option->phone ?: $option->mobile)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="sx-req">Purchase Date*</label>
                        <input type="date" name="order_date" class="form-control" required value="{{ old('order_date', optional($purchase->order_date)->format('Y-m-d')) }}">
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
jQuery(function ($) {
    $('#sx-change-pur').on('click', function () {
        $('#sx-change-details-modal').modal('show');
    });
    $('#sx-change-status').on('click', function (e) {
        e.preventDefault();
        $('#sx-change-status-modal').modal('show');
    });
    $('#sx-add-payment').on('click', function () {
        $('#sx-add-payment-modal').modal('show');
    });
    @if($errors->has('supplier_id') || $errors->has('order_date'))
        $('#sx-change-details-modal').modal('show');
    @endif
    @if($errors->has('status'))
        $('#sx-change-status-modal').modal('show');
    @endif
    @if($errors->has('amount') || $errors->has('method') || $errors->has('paid_at'))
        $('#sx-add-payment-modal').modal('show');
    @endif
});
</script>
@endpush
