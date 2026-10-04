<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment {{ $payment->number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; font-size: {{ $mode === 'pos' ? '10px' : '12px' }}; }
        h2, h3 { margin: 0 0 6px; }
        .center { text-align: center; }
        .muted { color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 5px 6px; {{ $mode === 'a4' ? 'border: 1px solid #ccc;' : '' }} }
        th { {{ $mode === 'a4' ? 'background: #A2502B; color: #fff;' : '' }} text-align: left; }
        .num { text-align: right; }
        .amount { font-size: 16px; font-weight: 700; text-align: center; margin: 10px 0; }
        .rule { border-top: 1px dashed #000; margin: 8px 0; }
    </style>
</head>
<body>
@php
    $methods = $paymentLabels ?? [];
    $method = $methods[$payment->method] ?? ucfirst((string) $payment->method);
    $customer = $sale->customerDisplayName() === 'WALK-IN' ? 'WALK IN' : $sale->customerDisplayName();
@endphp
    <div class="center"><h2>{{ $profile['companyName'] ?? $companyName ?? 'TEPHILLA SYSTEM' }}</h2></div>
    <div class="center"><h3>PAYMENT RECEIPT</h3></div>
    @if($mode === 'pos')
        <div class="rule"></div>
        <table>
            <tr><td>Invoice</td><td class="num">{{ $sale->documentNumber() }}</td></tr>
            <tr><td>Payment</td><td class="num">{{ $payment->number }}</td></tr>
            <tr><td>Customer</td><td class="num">{{ $customer }}</td></tr>
            <tr><td>Date</td><td class="num">{{ optional($payment->paid_at)->format('d-m-Y') }}</td></tr>
            <tr><td>Type</td><td class="num">{{ $method }}</td></tr>
            @if($payment->notes)
                <tr><td>Note</td><td class="num">{{ $payment->notes }}</td></tr>
            @endif
        </table>
        <div class="rule"></div>
        <div class="amount">Ksh {{ number_format((float) $payment->amount, 2) }}</div>
        <div class="center muted">Served by {{ optional($payment->user)->name ?: optional($sale->cashier)->name }}</div>
    @else
        <table>
            <tr><th>Invoice</th><td>{{ $sale->documentNumber() }}</td></tr>
            <tr><th>Customer</th><td>{{ $customer }}</td></tr>
            <tr><th>Payment Date</th><td>{{ optional($payment->paid_at)->format('d-m-Y') }}</td></tr>
            <tr><th>Payment Type</th><td>{{ $method }}</td></tr>
            <tr><th>Payment Note</th><td>{{ $payment->notes ?: $payment->reference }}</td></tr>
            <tr><th>Amount</th><td>Ksh {{ number_format((float) $payment->amount, 2) }}</td></tr>
            <tr><th>Created by</th><td>{{ optional($payment->user)->name ?: optional($sale->cashier)->name }}</td></tr>
        </table>
    @endif
</body>
</html>
