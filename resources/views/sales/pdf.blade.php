@php
    $letter = $sale->isInvoice() ? document_letterhead('invoice') : ['header_pdf' => null, 'footer_pdf' => null];
    $footerPt = document_banner_height_pt($letter['footer_pdf'] ?? null);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $sale->documentNumber() }}</title>
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
        .items th { background: #c9a027; color: #fff; }
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
    $title = $sale->isInvoice() ? 'TAX INVOICE' : 'RECEIPT';
    $customer = $sale->customerDisplayName() === 'WALK-IN' ? 'WALK IN' : $sale->customerDisplayName();
    $money = function ($amount) {
        return number_format((float) $amount, 2);
    };
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
                    <h2>{{ $profile['companyName'] ?? $companyName ?? 'TEPHILLA SYSTEM' }}</h2>
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
                @endif
            </td>
            <td width="40%" align="right">
                <h3>{{ $title }}</h3>
                <div><strong>{{ $sale->documentNumber() }}</strong></div>
                <div class="muted">Date: {{ optional($sale->sale_date)->format('d-m-Y H:i') }}</div>
                <div class="muted">Branch: {{ optional($sale->branch)->name }}</div>
            </td>
        </tr>
    </table>

    <div>
        <strong>Customer</strong><br>
        {{ $customer }}
        @if(optional($sale->customer)->phone && ! optional($sale->customer)->is_walk_in)
            <div class="muted">{{ $sale->customer->phone }}</div>
        @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Particulars</th>
                <th class="num">Qty</th>
                <th class="num">Price</th>
                <th class="num">Tax</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->name }}</td>
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
            <td class="num">{{ $money($sale->subtotal) }}</td>
        </tr>
        <tr>
            <td>Discount</td>
            <td class="num">{{ $money($sale->discount_amount) }}</td>
        </tr>
        <tr>
            <td>Tax</td>
            <td class="num">{{ $money($sale->tax_amount) }}</td>
        </tr>
        <tr class="grand">
            <td>Total</td>
            <td class="num">Ksh {{ $money($sale->total) }}</td>
        </tr>
        <tr>
            <td>Paid</td>
            <td class="num">{{ $money($sale->paid_amount) }}</td>
        </tr>
        <tr>
            <td>Balance</td>
            <td class="num">{{ $money($sale->remainingBalance()) }}</td>
        </tr>
    </table>

    @if($sale->notes)
        <p class="muted">Note: {{ $sale->notes }}</p>
    @endif
    <p class="muted">Served by: {{ optional($sale->cashier)->name }}</p>
    </div>
    @if(!empty($letter['footer_pdf']))
        <div class="page-footer"><img src="{{ $letter['footer_pdf'] }}" alt=""></div>
    @endif
</body>
</html>
