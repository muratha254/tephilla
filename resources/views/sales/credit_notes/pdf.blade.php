@php
    $letter = document_letterhead('credit_note');
    $footerPt = document_banner_height_pt($letter['footer_pdf'] ?? null);
    $companyTitle = trim((string) ($profile['companyName'] ?? $companyName ?? ''));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Credit Note {{ $note->number }}</title>
    <style>
        @page { margin: 0 0 {{ $footerPt }}pt 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 0; padding: 0; }
        .sheet { padding: 12px 22px 10px; }
        h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding-bottom: 12px; }
        .items { margin-top: 12px; }
        .items th, .items td { border: 1px solid #ccc; padding: 6px 8px; }
        .items th { background: #A2502B; color: #fff; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .totals td { padding: 3px 0; border: 0; }
        .grand { font-weight: 700; font-size: 14px; }
        .logo { max-height: 52px; max-width: 160px; }
        .doc-letter { margin: 0; padding: 0; width: 100%; }
        .doc-letter img { width: 595pt; max-width: 595pt; height: auto; display: block; }
        .page-footer { position: fixed; left: 0; bottom: -{{ $footerPt }}pt; width: 595pt; height: {{ $footerPt }}pt; }
        .page-footer img { width: 595pt; height: {{ $footerPt }}pt; }
    </style>
</head>
<body>
@php
    $money = function ($amount) {
        return number_format((float) $amount, 2);
    };
    $customer = optional($note->customer)->name
        ?: (optional($note->sale)->customerDisplayName() ?: 'WALK-IN');
    $phone = trim((string) ($profile['companyPhone'] ?? ''));
    $email = trim((string) ($profile['companyEmail'] ?? ''));
    if ($phone === '-') { $phone = ''; }
    if ($email === '-') { $email = ''; }
@endphp
    @if(!empty($letter['header_pdf']))
        <div class="doc-letter"><img src="{{ $letter['header_pdf'] }}" alt=""></div>
    @endif

    <div class="sheet">
    <table class="head">
        <tr>
            <td width="60%">
                @if(empty($letter['header_pdf']) && !empty($logo_pdf_path) && file_exists($logo_pdf_path))
                    <img class="logo" src="{{ $logo_pdf_path }}" alt=""><br>
                @endif
                <h2>{{ $companyTitle }}</h2>
                @if(empty($letter['header_pdf']))
                    @if(!empty($profile['companyAddress']) && $profile['companyAddress'] !== '-')
                        <div class="muted">{{ $profile['companyAddress'] }}</div>
                    @endif
                    @if($phone !== '' || $email !== '')
                        <div class="muted">{{ $phone }}@if($phone !== '' && $email !== '') | @endif{{ $email }}</div>
                    @endif
                    @if(!empty($profile['companyTaxPin']))
                        <div class="muted">VAT PIN: {{ $profile['companyTaxPin'] }}</div>
                    @endif
                @endif
            </td>
            <td width="40%" align="right">
                <h3>CREDIT NOTE</h3>
                <div><strong>{{ $note->number }}</strong></div>
                <div class="muted">Date: {{ optional($note->credit_date)->format('d-m-Y') }}</div>
                <div class="muted">Status: {{ ucfirst($note->status) }}</div>
                <div class="muted">Branch: {{ optional($note->branch)->name }}</div>
            </td>
        </tr>
    </table>

    <div>
        <strong>Customer</strong><br>
        {{ $customer }}
        @if(optional($note->customer)->phone)
            <div class="muted">{{ $note->customer->phone }}</div>
        @endif
        <div style="margin-top:8px;">
            <strong>Against Invoice:</strong> {{ optional($note->sale)->documentNumber() ?: '-' }}<br>
            <strong>Reason:</strong> {{ $note->reason ?: '-' }}
        </div>
    </div>

    <table class="items">
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
        <p class="muted"><strong>Notes:</strong> {{ $note->notes }}</p>
    @endif
    </div>
    @if(!empty($letter['footer_pdf']))
        <div class="page-footer"><img src="{{ $letter['footer_pdf'] }}" alt=""></div>
    @endif
</body>
</html>
