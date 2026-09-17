<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment {{ $payment->number }}</title>
    <style>
        @if($mode === 'pos')
        body { font-family: "Courier New", Courier, monospace; font-size: 12px; width: 72mm; margin: 8px auto; color: #000; }
        h2, h3 { margin: 0 0 6px; text-align: center; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; margin: 3px 0; }
        .rule { border-top: 1px dashed #000; margin: 8px 0; }
        .amount { font-size: 18px; font-weight: 700; text-align: center; margin: 10px 0; }
        @else
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; }
        h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; }
        th { background: #c9a027; color: #fff; }
        @endif
        .no-print { margin-bottom: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
@php
    $methods = $paymentLabels ?? [];
    $method = $methods[$payment->method] ?? ucfirst((string) $payment->method);
    $customer = $sale->customerDisplayName() === 'WALK-IN' ? 'WALK IN' : $sale->customerDisplayName();
@endphp
    <p class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </p>

    @if($mode === 'pos')
        <h2>{{ $profile['companyName'] ?? $companyName ?? 'Sellix POS' }}</h2>
        <div class="center">PAYMENT RECEIPT</div>
        <div class="rule"></div>
        <div class="row"><span>Invoice</span><span>{{ $sale->documentNumber() }}</span></div>
        <div class="row"><span>Payment</span><span>{{ $payment->number }}</span></div>
        <div class="row"><span>Customer</span><span>{{ $customer }}</span></div>
        <div class="row"><span>Date</span><span>{{ optional($payment->paid_at)->format('d-m-Y') }}</span></div>
        <div class="row"><span>Type</span><span>{{ $method }}</span></div>
        @if($payment->notes)
            <div class="row"><span>Note</span><span>{{ $payment->notes }}</span></div>
        @endif
        <div class="rule"></div>
        <div class="amount">Ksh {{ number_format((float) $payment->amount, 2) }}</div>
        <div class="center">Served by {{ optional($payment->user)->name ?: optional($sale->cashier)->name }}</div>
    @else
        <div class="head">
            <div>
                <h2>{{ $profile['companyName'] ?? $companyName ?? 'Sellix POS' }}</h2>
                @if(!empty($profile['companyAddress']) && $profile['companyAddress'] !== '-')
                    <div class="muted">{{ $profile['companyAddress'] }}</div>
                @endif
            </div>
            <div>
                <h3>PAYMENT RECEIPT</h3>
                <div>{{ $payment->number }}</div>
            </div>
        </div>
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
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
