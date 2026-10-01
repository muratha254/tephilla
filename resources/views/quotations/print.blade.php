<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $systemName ?? 'TEPHILLA SYSTEM' }} | {{ $quotation->number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; background: #fff; }
        h1, h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .head img { max-height: 56px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #c9a027; color: #fff; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .totals td { border: 0; padding: 3px 0; }
        .totals .grand { font-weight: 700; font-size: 14px; }
        .no-print { margin-bottom: 12px; }
        .notes { margin-top: 16px; font-size: 12px; }
        @media print { .no-print { display: none; } }
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
            <h2>{{ $companyName ?? 'TEPHILLA SYSTEM' }}</h2>
            <div class="muted">{{ $companyProfile['companyAddress'] ?? '' }}</div>
            <div class="muted">{{ $companyProfile['companyPhone'] ?? '' }} @if(!empty($companyProfile['companyEmail'])) | {{ $companyProfile['companyEmail'] }} @endif</div>
        </div>
        <div>
            <h3>Quotation</h3>
            <div><strong>{{ $quotation->number }}</strong></div>
            <div class="muted">Date: {{ optional($quotation->quote_date)->format('d-m-Y') }}</div>
            <div class="muted">Valid until: {{ optional($quotation->valid_until)->format('d-m-Y') ?: '-' }}</div>
            <div class="muted">Status: {{ $statuses[$quotation->status] ?? ucfirst($quotation->status) }}</div>
        </div>
    </div>

    <div>
        <strong>Customer</strong><br>
        {{ optional($quotation->customer)->name ?: '-' }}<br>
        <span class="muted">
            {{ optional($quotation->customer)->phone ?: '' }}
            {{ optional($quotation->customer)->email ? ' | '.$quotation->customer->email : '' }}
        </span>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->description ?: optional($item->product)->name }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 4), '0'), '.') }}</td>
                    <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td>{{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td>{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td>Ksh {{ number_format((float) $quotation->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td>Ksh {{ number_format((float) $quotation->discount_amount, 2) }}</td></tr>
        <tr><td>Tax</td><td>Ksh {{ number_format((float) $quotation->tax_amount, 2) }}</td></tr>
        <tr class="grand"><td>Grand Total</td><td>Ksh {{ number_format((float) $quotation->total, 2) }}</td></tr>
    </table>

    @if($quotation->notes || $quotation->terms)
        <div class="notes">
            @if($quotation->notes)
                <div><strong>Notes:</strong> {{ $quotation->notes }}</div>
            @endif
            @if($quotation->terms)
                <div><strong>Terms:</strong> {{ $quotation->terms }}</div>
            @endif
        </div>
    @endif
</body>
</html>
