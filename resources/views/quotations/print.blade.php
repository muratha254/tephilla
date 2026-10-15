@php
    $letter = document_letterhead('quotation');
    $footerPx = (int) round(document_banner_height_pt($letter['footer_pdf'] ?? null) * 1.333);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $systemName ?? 'TEPHILLAH SYSTEM' }} | {{ $quotation->number }}</title>
    <style>
        @page { margin: 0 0 {{ $footerPx }}px 0; }
        html, body { height: 100%; }
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; background: #fff; min-height: 100vh; display: flex; flex-direction: column; }
        .sheet { padding: 16px 22px 12px; flex: 1 0 auto; }
        .page-footer { margin-top: auto; width: 100%; }
        .page-footer img { width: 100%; height: auto; display: block; }
        @media print {
            body { display: block; min-height: 0; }
            .page-footer { position: fixed; left: 0; bottom: 0; width: 100%; margin: 0; }
            .sheet { padding-bottom: {{ $footerPx + 12 }}px; }
        }
        h1, h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .head img { max-height: 56px; }
        .doc-letter { margin: 0; width: 100%; }
        .doc-letter img { width: 100%; max-width: 100%; max-height: none; height: auto; display: block; }
        .split { display: flex; align-items: flex-start; gap: 18px; margin-top: 16px; }
        .terms-frame { flex: 1; border: 1px solid #999; padding: 8px 10px; font-size: 12px; white-space: pre-line; }
        .split .totals { width: 260px; margin: 0; flex: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #A2502B; color: #fff; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .totals td { border: 0; padding: 3px 0; }
        .totals .grand { font-weight: 700; font-size: 14px; }
        .no-print { margin: 0; padding: 12px 22px; }
        .notes { margin-top: 16px; font-size: 12px; }
        .parties { width: 100%; margin-top: 8px; }
        .parties td { border: 0; vertical-align: top; padding: 0; }
        .bill-label { font-weight: 700; margin-bottom: 8px; }
        .bill-name { font-weight: 700; font-size: 15px; }
        .meta { margin-top: 0; }
        .meta td { border: 3px solid #fff; padding: 7px 10px; font-weight: 700; }
        .meta .lbl { background: #A2502B; color: #fff; width: 42%; }
        .meta .val { background: #f4eae6; color: #1a1a1a; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </p>

    @if(!empty($letter['header_url']))
        <div class="doc-letter"><img src="{{ $letter['header_url'] }}" alt=""></div>
    @endif

    <div class="sheet">
    <div class="head">
        <div>
            @if(empty($letter['header_url']))
                @if(!empty($companyLogoUrl))
                    <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }}">
                @endif
                <h2>{{ $companyName ?? 'TEPHILLAH SYSTEM' }}</h2>
            <div class="muted">{{ $companyProfile['companyAddress'] ?? '' }}</div>
            <div class="muted">{{ $companyProfile['companyPhone'] ?? '' }} @if(!empty($companyProfile['companyEmail'])) | {{ $companyProfile['companyEmail'] }} @endif</div>
            @endif
        </div>
    </div>

    @php
        $customer = optional($quotation->customer);
        $pin = trim((string) ($companyProfile['companyTaxPin'] ?? '')) ?: trim((string) ($customer->tax_number ?? ''));
    @endphp
    <table class="parties">
        <tr>
            <td width="46%">
                <div class="bill-label">BILL TO:</div>
                <div class="bill-name">{{ $customer->name ?: '-' }}</div>
                @if($customer->phone)
                    <div>{{ $customer->phone }}</div>
                @endif
            </td>
            <td width="54%">
                <table class="meta">
                    <tr><td class="lbl">QUOTE NO:</td><td class="val">{{ $quotation->number }}</td></tr>
                    <tr><td class="lbl">DATE:</td><td class="val">{{ optional($quotation->quote_date)->format('d F Y') ?: '-' }}</td></tr>
                    <tr><td class="lbl">DUE DATE:</td><td class="val">{{ optional($quotation->valid_until)->format('d F Y') ?: '-' }}</td></tr>
                    @if($pin !== '')
                    <tr><td class="lbl">PIN:</td><td class="val">{{ $pin }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Colour</th>
                <th>UOM</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $item)
                @php
                    $unit = optional(optional($item->product)->unit);
                    $uom = $unit->short_name ?: $unit->name;
                    $colour = $item->color ?: optional($item->variant)->color ?: optional($item->product)->color;
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->description ?: optional($item->product)->name }}</td>
                    <td>{{ $colour ?: '—' }}</td>
                    <td>{{ $uom ?: '—' }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 4), '0'), '.') }}</td>
                    <td>{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td>{{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td>{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($quotation->notes)
        <div class="notes"><strong>Notes:</strong> {{ $quotation->notes }}</div>
    @endif

    <div class="split">
        @if($quotation->terms)
            <div class="terms-frame">{{ $quotation->terms }}</div>
        @endif
        <table class="totals">
            <tr><td>Subtotal</td><td>Ksh {{ number_format((float) $quotation->subtotal, 2) }}</td></tr>
            <tr><td>Discount</td><td>Ksh {{ number_format((float) $quotation->discount_amount, 2) }}</td></tr>
            <tr><td>Tax</td><td>Ksh {{ number_format((float) $quotation->tax_amount, 2) }}</td></tr>
            <tr class="grand"><td>Grand Total</td><td>Ksh {{ number_format((float) $quotation->total, 2) }}</td></tr>
        </table>
    </div>
    </div>
    @if(!empty($letter['footer_url']))
        <div class="page-footer"><img src="{{ $letter['footer_url'] }}" alt=""></div>
    @endif
</body>
</html>
