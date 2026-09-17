<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>POS {{ $sale->documentNumber() }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #000; margin: 0; padding: 8px 6px; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .company { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .muted { font-size: 9px; }
        .rule { border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 2px 0; vertical-align: top; }
        th { font-size: 8px; text-transform: uppercase; border-bottom: 1px dashed #000; text-align: left; }
        .num { text-align: right; white-space: nowrap; }
        table.items { table-layout: fixed; }
        table.items th,
        table.items td { padding: 3px 2px; vertical-align: top; }
        table.items .col-left { width: 58%; text-align: left; }
        table.items .col-total { width: 42%; text-align: right; }
        table.items .item-name { text-transform: uppercase; padding-top: 6px; padding-bottom: 1px; word-wrap: break-word; }
        table.items .item-nums td { padding-top: 0; padding-bottom: 6px; }
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
@endphp
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
    <table>
        <tr><td>Invoice No:</td><td class="num">{{ $number }}</td></tr>
        <tr><td>Customer:</td><td class="num">{{ $customer }}</td></tr>
        <tr><td>Date:</td><td class="num">{{ optional($sale->sale_date)->format('d-m-Y H:i:s') }}</td></tr>
    </table>
    <div class="rule"></div>
    <table class="items">
        <thead>
            <tr>
                <th colspan="2">Particulars</th>
            </tr>
            <tr>
                <th class="col-left">Qty x Price</th>
                <th class="col-total">Total</th>
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
                <tr class="item-nums">
                    <td class="col-left">{{ $qty }} x {{ number_format($unit, 2) }}</td>
                    <td class="col-total">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="rule"></div>
    <table>
        <tr><td>SUB TOTAL</td><td class="num">Ksh {{ number_format((float) $sale->subtotal, 2) }}</td></tr>
        @if((float) $sale->discount_amount > 0)
            <tr><td>DISCOUNT</td><td class="num">Ksh {{ number_format((float) $sale->discount_amount, 2) }}</td></tr>
        @endif
        @if((float) $sale->tax_amount > 0)
            <tr><td>TAX</td><td class="num">Ksh {{ number_format((float) $sale->tax_amount, 2) }}</td></tr>
        @endif
        <tr class="bold"><td>TOTAL</td><td class="num">Ksh {{ number_format((float) $sale->total, 2) }}</td></tr>
        @forelse($sale->payments as $payment)
            <tr>
                <td>{{ $paymentLabels[$payment->method] ?? ucfirst($payment->method) }}{{ $payment->reference ? ' '.$payment->reference : '' }}</td>
                <td class="num">Ksh {{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td>Paid</td><td class="num">Ksh {{ number_format((float) $sale->paid_amount, 2) }}</td></tr>
        @endforelse
        @if($change > 0)
            <tr><td>CHANGE</td><td class="num">Ksh {{ number_format($change, 2) }}</td></tr>
        @endif
    </table>
    <div class="rule"></div>
    <div class="center">SERVED BY: {{ strtoupper(optional($sale->cashier)->name ?: 'CASHIER') }}</div>
    <div class="center muted">Thank You...Come Again</div>
</body>
</html>
