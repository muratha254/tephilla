<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $systemName ?? 'Sellix POS' }} | Credit Note {{ $note->number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; background: #fff; }
        h1, h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .head img { max-height: 56px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #c9a027; color: #fff; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .totals td { border: 0; padding: 3px 0; }
        .totals .grand { font-weight: 700; font-size: 14px; }
        .no-print { margin-bottom: 12px; }
        .meta { margin-top: 8px; font-size: 13px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
@php
    $money = function ($amount) {
        return number_format((float) $amount, 2);
    };
    $customer = optional($note->customer)->name
        ?: (optional($note->sale)->customerDisplayName() ?: 'WALK-IN');
@endphp
    <p class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </p>

    <div class="head">
        <div>
            @if(!empty($companyLogoUrl))
                <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}">
            @endif
            <h2>{{ $profile['companyName'] ?? $companyName ?? 'Sellix POS' }}</h2>
            @if(!empty($profile['companyAddress']) && $profile['companyAddress'] !== '-')
                <div class="muted">{{ $profile['companyAddress'] }}</div>
            @endif
            <div class="muted">
                {{ $profile['companyPhone'] ?? '' }}
                @if(!empty($profile['companyEmail'])) | {{ $profile['companyEmail'] }} @endif
            </div>
            @if(!empty($profile['companyTaxPin']))
                <div class="muted">VAT PIN: {{ $profile['companyTaxPin'] }}</div>
            @endif
        </div>
        <div>
            <h3>CREDIT NOTE</h3>
            <div><strong>{{ $note->number }}</strong></div>
            <div class="muted">Date: {{ optional($note->credit_date)->format('d-m-Y') }}</div>
            <div class="muted">Status: {{ ucfirst($note->status) }}</div>
            <div class="muted">Branch: {{ optional($note->branch)->name }}</div>
        </div>
    </div>

    <div class="meta">
        <strong>Customer</strong><br>
        {{ $customer }}<br>
        @if(optional($note->customer)->phone)
            <span class="muted">{{ $note->customer->phone }}</span><br>
        @endif
        <div style="margin-top:8px;">
            <strong>Against Invoice:</strong> {{ optional($note->sale)->documentNumber() ?: '-' }}<br>
            <strong>Reason:</strong> {{ $note->reason ?: '-' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Tax</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($note->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ optional($item->product)->name ?: (optional($item->saleItem)->name ?: ('Item #' . $item->sale_item_id)) }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                    <td class="num">{{ $money($item->unit_price) }}</td>
                    <td class="num">{{ $money($item->tax_amount) }}</td>
                    <td class="num">{{ $money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="num">{{ $money($note->subtotal) }}</td>
        </tr>
        <tr>
            <td>Tax</td>
            <td class="num">{{ $money($note->tax_amount) }}</td>
        </tr>
        <tr class="grand">
            <td>Total Credit</td>
            <td class="num">{{ $money($note->total) }}</td>
        </tr>
    </table>

    @if($note->notes)
        <p class="muted" style="margin-top:18px;"><strong>Notes:</strong> {{ $note->notes }}</p>
    @endif
</body>
</html>
