@php
    $letter = ($sale->isInvoice() ?? false) ? document_letterhead('invoice') : ['header_url' => null, 'footer_url' => null, 'header_pdf' => null, 'footer_pdf' => null];
    $footerPx = (int) round(document_banner_height_pt($letter['footer_pdf'] ?? null) * 1.333);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $systemName ?? 'TEPHILLAH SYSTEM' }} | {{ $sale->documentNumber() }}</title>
    <style>
        html, body { height: auto; }
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; background: #fff; }
        .sheet { padding: 16px 22px 12px; }
        .page-footer { width: 100%; }
        .page-footer img { width: 100%; height: auto; display: block; }
        @media print {
            html, body { height: auto; min-height: 0; }
            .page-footer { position: fixed; left: 0; bottom: 0; width: 100%; margin: 0; }
            .sheet { padding-bottom: {{ $footerPx + 16 }}px; }
        }
        h1, h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .head img { max-height: 56px; }
        .doc-letter { margin: 0 0 12px; }
        .doc-letter-footer { margin: 24px 0 0; }
        .doc-letter img { width: 100%; max-width: 100%; max-height: 150px; height: auto; object-fit: contain; object-position: top; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #A2502B; color: #fff; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .totals td { border: 0; padding: 3px 0; }
        .totals .grand { font-weight: 700; font-size: 14px; }
        .split { display: flex; align-items: flex-start; gap: 18px; margin-top: 16px; }
        .terms-frame { flex: 1; border: 1px solid #999; padding: 8px 10px; font-size: 12px; white-space: pre-line; }
        .split .totals { width: 280px; margin: 0; flex: none; }
        .no-print { margin-bottom: 12px; }
        .parties { width: 100%; margin-top: 8px; }
        .parties td { border: 0; vertical-align: top; padding: 0; }
        .bill-label { font-weight: 700; margin-bottom: 8px; }
        .bill-name { font-weight: 700; font-size: 15px; }
        .meta { margin-top: 0; }
        .meta td { border: 3px solid #fff; padding: 7px 10px; font-weight: 700; }
        .meta .lbl { background: #A2502B; color: #fff; width: 42%; }
        .meta .val { background: #f4eae6; color: #1a1a1a; }
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
        <button type="button" onclick="sxPrintOnce()">Print</button>
        <button type="button" onclick="window.close()">Close</button>
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
                <h2>{{ $profile['companyName'] ?? $companyName ?? 'TEPHILLAH SYSTEM' }}</h2>
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
    </div>

    @php
        $docLabel = $sale->isInvoice() ? 'INVOICE NO:' : 'RECEIPT NO:';
        $customerModel = optional($sale->customer);
        $pin = trim((string) ($profile['companyTaxPin'] ?? '')) ?: trim((string) ($customerModel->tax_number ?? ''));
    @endphp
    <table class="parties">
        <tr>
            <td width="46%">
                <div class="bill-label">BILL TO:</div>
                <div class="bill-name">{{ $customer }}</div>
                @if($customerModel->phone && ! $customerModel->is_walk_in)
                    <div>{{ $customerModel->phone }}</div>
                @endif
            </td>
            <td width="54%">
                <table class="meta">
                    <tr><td class="lbl">{{ $docLabel }}</td><td class="val">{{ $sale->documentNumber() }}</td></tr>
                    <tr><td class="lbl">DATE:</td><td class="val">{{ optional($sale->sale_date)->format('d F Y') ?: '-' }}</td></tr>
                    <tr><td class="lbl">DUE DATE:</td><td class="val">{{ optional($sale->due_date)->format('d F Y') ?: '-' }}</td></tr>
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
                <th>Particulars</th>
                <th>Colour</th>
                <th>UOM</th>
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
                @php
                    $unit = optional(optional($item->product)->unit);
                    $uom = $unit->short_name ?: $unit->name;
                    $colour = $item->color ?: optional($item->variant)->color ?: optional($item->product)->color;
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $colour ?: '—' }}</td>
                    <td>{{ $uom ?: '—' }}</td>
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
        @php
            $documentNote = trim((string) $sale->notes);
            if ($documentNote === '' && $sale->isInvoice()) {
                $documentNote = optional($sale->company)->documentNote('invoice') ?: \App\Models\Quotation::DEFAULT_TERMS;
            }
        @endphp
        <div class="split">
            @if($documentNote !== '')
                <div class="terms-frame">{{ $documentNote }}</div>
            @endif
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
        </div>
    @endunless

    @if($mode === 'delivery')
        <div class="sign">
            <div>Delivered by</div>
            <div>Received by</div>
        </div>
    @endif

    @if($mode !== 'dispatch' && ! $sale->isInvoice() && $sale->notes)
        <p class="muted">Note: {{ $sale->notes }}</p>
    @endif

    <p class="muted">Served by: {{ optional($sale->cashier)->name }}</p>
    </div>
    @if(!empty($letter['footer_url']))
        <div class="page-footer"><img src="{{ $letter['footer_url'] }}" alt=""></div>
    @endif
    <script>
        var sxPrinted = false;
        function sxPrintOnce() {
            if (sxPrinted) return;
            sxPrinted = true;
            window.print();
        }
    </script>
</body>
</html>
