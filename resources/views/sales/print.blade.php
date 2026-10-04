@php
    $letter = ($sale->isInvoice() ?? false) ? document_letterhead('invoice') : ['header_url' => null, 'footer_url' => null, 'header_pdf' => null, 'footer_pdf' => null];
    $footerPx = (int) round(document_banner_height_pt($letter['footer_pdf'] ?? null) * 1.333);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $systemName ?? 'TEPHILLA SYSTEM' }} | {{ $sale->documentNumber() }}</title>
    <style>
        html, body { height: 100%; }
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; background: #fff; min-height: 100vh; display: flex; flex-direction: column; }
        .sheet { padding: 16px 22px 12px; flex: 1 0 auto; }
        .page-footer { margin-top: auto; width: 100%; }
        .page-footer img { width: 100%; height: auto; display: block; }
        @media print {
            body { display: block; min-height: 0; }
            .page-footer { position: fixed; left: 0; bottom: 0; width: 100%; margin: 0; }
            .sheet { padding-bottom: {{ $footerPx + 16 }}px; }
        }
        h1, h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .head img { max-height: 56px; }
        .doc-letter { margin: 0 0 12px; }
        .doc-letter-footer { margin: 24px 0 0; }
        .doc-letter img { width: 100%; max-width: 100%; max-height: none; height: auto; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #A2502B; color: #fff; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .totals td { border: 0; padding: 3px 0; }
        .totals .grand { font-weight: 700; font-size: 14px; }
        .no-print { margin-bottom: 12px; }
        .sign { display: flex; justify-content: space-between; margin-top: 36px; font-size: 12px; }
        .sign div { width: 40%; border-top: 1px solid #999; padding-top: 6px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
@php
    $titles = [
        'a4' => $sale->isInvoice() ? 'TAX INVOICE' : 'RECEIPT',
        'dispatch' => 'DISPATCH LIST',
        'delivery' => 'DELIVERY NOTE',
    ];
    $title = $titles[$mode] ?? 'RECEIPT';
    $hidePrices = in_array($mode, ['dispatch', 'delivery'], true);
    $customer = $sale->customerDisplayName();
    $money = function ($amount) {
        return number_format((float) $amount, 2);
    };
@endphp
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
        </div>
        <div>
            <h3>{{ $title }}</h3>
            <div><strong>{{ $sale->documentNumber() }}</strong></div>
            <div class="muted">Date: {{ optional($sale->sale_date)->format('d-m-Y H:i') }}</div>
            <div class="muted">Branch: {{ optional($sale->branch)->name }}</div>
        </div>
    </div>

    <div>
        <strong>Customer</strong><br>
        {{ $customer }}<br>
        @if(optional($sale->customer)->phone && ! optional($sale->customer)->is_walk_in)
            <span class="muted">{{ $sale->customer->phone }}</span>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Particulars</th>
                <th class="num">Qty</th>
                @unless($hidePrices)
                    <th class="num">Price</th>
                    <th class="num">Tax</th>
                    <th class="num">Total</th>
                @endunless
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->name }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                    @unless($hidePrices)
                        <td class="num">{{ $money($item->unit_price) }}</td>
                        <td class="num">{{ $money($item->tax_amount) }}</td>
                        <td class="num">{{ $money($item->line_total) }}</td>
                    @endunless
                </tr>
            @endforeach
        </tbody>
    </table>

    @unless($hidePrices)
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
    @endunless

    @if($mode === 'delivery')
        <div class="sign">
            <div>Delivered by</div>
            <div>Received by</div>
        </div>
    @endif

    @if($sale->notes && $mode !== 'dispatch')
        <p class="muted">Note: {{ $sale->notes }}</p>
    @endif

    <p class="muted">Served by: {{ optional($sale->cashier)->name }}</p>
    </div>
    @if(!empty($letter['footer_url']))
        <div class="page-footer"><img src="{{ $letter['footer_url'] }}" alt=""></div>
    @endif
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
