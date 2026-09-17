<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $sale->receipt_number ?: $sale->invoice_number ?: $sale->number }}</title>
    <style>
        @page {
            size: {{ $paper }} auto;
            margin: 3mm;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            font-family: "Courier New", Courier, monospace;
            font-size: {{ $paper === '58mm' ? '11px' : '12px' }};
            line-height: 1.35;
        }
        .ticket {
            width: {{ $paper === '58mm' ? '52mm' : '72mm' }};
            margin: 0 auto;
            padding: 4px 0 10px;
        }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .company { font-size: {{ $paper === '58mm' ? '13px' : '15px' }}; font-weight: 700; text-transform: uppercase; }
        .muted { font-size: 11px; }
        .rule { border-top: 1px dashed #000; margin: 6px 0; }
        .rule-solid { border-top: 1px solid #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .meta { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        col.left { width: 58%; }
        col.total { width: 42%; }
        th, td { padding: 3px 2px; vertical-align: top; }
        th { font-size: 10px; text-transform: uppercase; border-bottom: 1px dashed #000; }
        .left { text-align: left; white-space: nowrap; }
        .total { text-align: right; white-space: nowrap; }
        .item-name {
            text-transform: uppercase;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal;
        }
        .totals .row { margin: 2px 0; }
        .totals .label { text-align: right; flex: 1; padding-right: 8px; }
        .no-print { text-align: center; margin: 12px 0; }
        .no-print button {
            background: #c9a027;
            color: #fff;
            border: 0;
            padding: 8px 16px;
            font-weight: 700;
            cursor: pointer;
        }
        @media print {
            .no-print { display: none; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
@php
    $number = $sale->receipt_number ?: $sale->invoice_number ?: $sale->number;
    $title = $sale->document_type === 'invoice' ? 'ORIGINAL INVOICE' : 'ORIGINAL RECEIPT';
    $customer = optional($sale->customer)->is_walk_in ? 'WALK IN' : (optional($sale->customer)->name ?: 'WALK IN');
    $received = (float) $sale->payments->sum('amount');
    if ($received <= 0) {
        $received = (float) $sale->paid_amount;
    }
    $change = max(0, $received - (float) $sale->total);
    $website = $profile['companyWebsite'] ?? '';
@endphp
<div class="ticket">
    <div class="center company">{{ $profile['companyName'] ?? fleet_system_name() }}</div>
    @if(!empty($profile['companyAddress']) && $profile['companyAddress'] !== '-')
        <div class="center muted">{{ $profile['companyAddress'] }}</div>
    @endif
    @if(!empty($profile['companyTaxPin']))
        <div class="center muted">VAT #: {{ $profile['companyTaxPin'] }}</div>
    @endif
    <div class="rule"></div>
    <div class="center bold">{{ $title }}</div>
    <div class="rule"></div>
    <div class="meta row"><span>Invoice No:</span><span>{{ $number }}</span></div>
    <div class="meta row"><span>Customer:</span><span>{{ $customer }}</span></div>
    <div class="meta row"><span>Date:</span><span>{{ optional($sale->created_at)->format('d-m-Y H:i:s') }}</span></div>
    <div class="rule-solid"></div>
    <table>
        <colgroup>
            <col class="left">
            <col class="total">
        </colgroup>
        <thead>
            <tr>
                <th colspan="2">Particulars</th>
            </tr>
            <tr>
                <th class="left">Qty x Price</th>
                <th class="total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                @php
                    $qty = rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.');
                    $unit = (float) $item->quantity > 0 ? (float) $item->line_total / (float) $item->quantity : (float) $item->unit_price;
                @endphp
                <tr>
                    <td colspan="2" class="item-name">{{ $item->name }}</td>
                </tr>
                <tr>
                    <td class="left">{{ $qty }} x {{ number_format($unit, 2) }}</td>
                    <td class="total">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="rule"></div>
    <div class="totals">
        <div class="row"><span class="label">SUB TOTAL</span><span>Ksh {{ number_format((float) $sale->subtotal, 2) }}</span></div>
        @if((float) $sale->discount_amount > 0)
            <div class="row"><span class="label">DISCOUNT</span><span>Ksh {{ number_format((float) $sale->discount_amount, 2) }}</span></div>
        @endif
        @if((float) $sale->tax_amount > 0)
            <div class="row"><span class="label">TAX</span><span>Ksh {{ number_format((float) $sale->tax_amount, 2) }}</span></div>
        @endif
        <div class="row bold"><span class="label">TOTAL</span><span>Ksh {{ number_format((float) $sale->total, 2) }}</span></div>
        @forelse($sale->payments as $payment)
            <div class="row">
                <span class="label">{{ $paymentLabels[$payment->method] ?? ucfirst($payment->method) }}{{ $payment->reference ? ' '.$payment->reference : '' }}:</span>
                <span>Ksh {{ number_format((float) $payment->amount, 2) }}</span>
            </div>
        @empty
            <div class="row"><span class="label">Paid:</span><span>Ksh {{ number_format((float) $sale->paid_amount, 2) }}</span></div>
        @endforelse
        @if($change > 0)
            <div class="row"><span class="label">CHANGE</span><span>Ksh {{ number_format($change, 2) }}</span></div>
        @endif
    </div>
    <div class="rule"></div>
    <div class="center">SERVED BY: {{ strtoupper(optional($sale->cashier)->name ?: 'CASHIER') }}</div>
    <div class="center muted" style="margin-top:8px;">Thank You...Come Again</div>
    @if($website)
        <div class="center muted">{{ $website }}</div>
    @endif
    <div class="no-print">
        <button type="button" onclick="window.print()">Print Receipt</button>
    </div>
</div>
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 250);
    });
</script>
</body>
</html>
