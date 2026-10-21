@php
    $letter = document_letterhead('quotation');
    $footerPt = document_banner_height_pt($letter['footer_pdf'] ?? null);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->number }}</title>
    <style>
        @page { margin: 0 0 {{ $footerPt }}pt 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 0; }
        h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 11px; }
        .sheet { padding: 12px 22px 10px; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding-bottom: 12px; border: 0; }
        .items { margin-top: 12px; }
        .items th, .items td { border: 1px solid #ccc; padding: 6px 8px; }
        .items th { background: #A2502B; color: #fff; }
        .num { text-align: right; white-space: nowrap; }
        .split td { border: 0; vertical-align: top; padding-top: 14px; }
        .totals td { padding: 3px 0; border: 0; }
        .grand { font-weight: 700; font-size: 14px; }
        .notes { margin-top: 12px; font-size: 11px; }
        .terms-frame { border: 1px solid #999; padding: 8px 10px; font-size: 10px; line-height: 1.35; }
        .bill-label { font-weight: 700; font-size: 12px; margin-bottom: 8px; }
        .bill-name { font-weight: 700; font-size: 14px; }
        .meta td { border: 3px solid #fff; padding: 7px 10px; font-weight: 700; font-size: 11px; }
        .meta .lbl { background: #A2502B; color: #fff; width: 42%; }
        .meta .val { background: #f4eae6; color: #1a1a1a; }
        .logo { max-height: 52px; max-width: 160px; }
        .doc-letter { margin: 0; padding: 0; width: 100%; }
        .doc-letter img { width: 595pt; max-width: 595pt; max-height: 120pt; height: auto; display: block; }
        .page-footer { position: fixed; left: 0; bottom: -{{ $footerPt }}pt; width: 595pt; height: {{ $footerPt }}pt; }
        .page-footer img { width: 595pt; height: {{ $footerPt }}pt; }
    </style>
</head>
<body>
@php
    $customer = optional($quotation->customer);
    $pin = trim((string) ($profile['companyTaxPin'] ?? '')) ?: trim((string) ($customer->tax_number ?? ''));
@endphp
    @if(!empty($letter['header_pdf']))
        <div class="doc-letter"><img src="{{ $letter['header_pdf'] }}" alt=""></div>
    @endif

    <div class="sheet">
    <table class="head">
        <tr>
            <td width="60%">
                @if(empty($letter['header_pdf']))
                    @if(!empty($logo_pdf_path) && file_exists($logo_pdf_path))
                        <img class="logo" src="{{ $logo_pdf_path }}" alt=""><br>
                    @endif
                    <h2>{{ $profile['companyName'] ?? $companyName ?? 'TEPHILLAH SYSTEM' }}</h2>
                    @if(!empty($profile['companyAddress']) && $profile['companyAddress'] !== '-')
                        <div class="muted">{{ $profile['companyAddress'] }}</div>
                    @endif
                    <div class="muted">
                        {{ $profile['companyPhone'] ?? '' }}
                        @if(!empty($profile['companyEmail'])) | {{ $profile['companyEmail'] }} @endif
                    </div>
                @endif
            </td>
            <td width="40%"></td>
        </tr>
    </table>

    <table>
        <tr>
            <td width="46%" style="vertical-align:top;border:0;">
                <div class="bill-label">BILL TO:</div>
                <div class="bill-name">{{ $customer->name ?: '-' }}</div>
                @if($customer->phone)
                    <div>{{ $customer->phone }}</div>
                @endif
            </td>
            <td width="54%" style="vertical-align:top;border:0;">
                <table class="meta">
                    <tr>
                        <td class="lbl">QUOTE NO:</td>
                        <td class="val">{{ $quotation->number }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">DATE:</td>
                        <td class="val">{{ optional($quotation->quote_date)->format('d F Y') ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">DUE DATE:</td>
                        <td class="val">{{ optional($quotation->valid_until)->format('d F Y') ?: '-' }}</td>
                    </tr>
                    @if($pin !== '')
                    <tr>
                        <td class="lbl">PIN:</td>
                        <td class="val">{{ $pin }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Colour</th>
                <th>UOM</th>
                <th class="num">Qty</th>
                <th class="num">Price</th>
                <th class="num">Tax</th>
                <th class="num">Total</th>
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
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $documentNote = trim((string) $quotation->terms);
        if ($documentNote === '') {
            $documentNote = optional($quotation->company)->documentNote('quotation') ?: \App\Models\Quotation::DEFAULT_TERMS;
        }
    @endphp
    @if($quotation->notes)
        <div class="notes"><strong>Notes:</strong> {{ $quotation->notes }}</div>
    @endif

    <table class="split">
        <tr>
            <td width="58%">
                @if($documentNote !== '')
                    <div class="terms-frame">{!! nl2br(e($documentNote)) !!}</div>
                @endif
            </td>
            <td width="42%">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="num">Ksh {{ number_format((float) $quotation->subtotal, 2) }}</td></tr>
                    <tr><td>Discount</td><td class="num">Ksh {{ number_format((float) $quotation->discount_amount, 2) }}</td></tr>
                    <tr><td>Tax</td><td class="num">Ksh {{ number_format((float) $quotation->tax_amount, 2) }}</td></tr>
                    <tr class="grand"><td>Grand Total</td><td class="num">Ksh {{ number_format((float) $quotation->total, 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
    </div>
    @if(!empty($letter['footer_pdf']))
        <div class="page-footer"><img src="{{ $letter['footer_pdf'] }}" alt=""></div>
    @endif
</body>
</html>
