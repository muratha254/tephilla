<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $systemName ?? 'TEPHILLAH SYSTEM' }} | {{ $purchase->number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; background: #fff; }
        h1, h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .head img { max-height: 56px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #A2502B; color: #fff; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .totals td { border: 0; padding: 3px 0; }
        .totals .grand { font-weight: 700; font-size: 14px; }
        .no-print { margin-bottom: 12px; }
        @media print { .no-print { display: none; } }
        @if($thermal)
        body { margin: 8px; width: 76mm; font-size: 12px; }
        .head { display: block; text-align: center; }
        table, th, td { border: 0; padding: 2px 0; }
        th { background: none; color: #000; border-bottom: 1px dashed #999; }
        td { border-bottom: 1px dotted #ddd; }
        .totals { width: 100%; margin: 8px 0 0; }
        @endif
    </style>
</head>
<body>
    <p class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </p>

    <div class="head">
        <div>
            @if(!empty($companyLogoUrl))
                <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}">
            @endif
            <h2>{{ $companyName ?? 'TEPHILLAH SYSTEM' }}</h2>
            <div class="muted">{{ $companyProfile['companyAddress'] ?? '' }}</div>
            <div class="muted">{{ $companyProfile['companyPhone'] ?? '' }} @if(!empty($companyProfile['companyEmail'])) | {{ $companyProfile['companyEmail'] }} @endif</div>
        </div>
        <div>
            <h3>{{ $hidePrices ? 'Purchase Order' : ($thermal ? 'Purchase' : 'Purchase Order') }}</h3>
            <div><strong>{{ $purchase->number }}</strong></div>
            <div class="muted">Date: {{ optional($purchase->order_date)->format('d-m-Y') }}</div>
            @if($purchase->reference_no)
                <div class="muted">Ref: {{ $purchase->reference_no }}</div>
            @endif
        </div>
    </div>

    <div>
        <strong>Supplier</strong><br>
        {{ optional($purchase->supplier)->name ?: '-' }}<br>
        <span class="muted">
            {{ optional($purchase->supplier)->phone ?: '' }}
            {{ optional($purchase->supplier)->address ? ' | '.$purchase->supplier->address : '' }}
        </span>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Qty</th>
                @unless($hidePrices)
                    <th>Cost</th>
                    <th>Total</th>
                @endunless
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ optional($item->product)->name ?: $item->description }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 4), '0'), '.') }}</td>
                    @unless($hidePrices)
                        <td>{{ number_format((float) $item->unit_cost, 2) }}</td>
                        <td>{{ number_format((float) $item->line_total, 2) }}</td>
                    @endunless
                </tr>
            @endforeach
        </tbody>
    </table>

    @unless($hidePrices)
        <table class="totals">
            <tr><td>Subtotal</td><td>Ksh {{ number_format((float) $purchase->subtotal, 2) }}</td></tr>
            <tr><td>Tax</td><td>Ksh {{ number_format((float) $purchase->tax_amount, 2) }}</td></tr>
            @if((float) $purchase->expense_amount)
                <tr><td>Expenses</td><td>Ksh {{ number_format((float) $purchase->expense_amount, 2) }}</td></tr>
            @endif
            <tr class="grand"><td>Grand Total</td><td>Ksh {{ number_format((float) $purchase->total, 2) }}</td></tr>
            <tr><td>Paid</td><td>Ksh {{ number_format((float) $purchase->paid_amount, 2) }}</td></tr>
            <tr><td>Balance</td><td>Ksh {{ number_format($purchase->balance(), 2) }}</td></tr>
        </table>
    @endunless

    @if($purchase->notes)
        <p class="muted">Note: {{ $purchase->notes }}</p>
    @endif

    <p class="muted">{{ $systemName ?? 'TEPHILLAH SYSTEM' }}</p>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
