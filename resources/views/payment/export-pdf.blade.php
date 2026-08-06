<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Details - {{ $payment->reference_number }}</title>
    <style>
        @page {
            size: A4 landscape;
            /* Taller top margin: repeated thead on page 2+ was clipping under printer non-print area */
            margin: 14mm 8mm 10mm 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
            line-height: 1.25;
        }
        /* Payment title row only — not chunked; flows once before line items */
        .pdf-meta-table {
            width: 99%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 2px;
            margin-left: auto;
            margin-right: auto;
        }
        .pdf-meta-table tr.sync-top-tr {
            page-break-inside: avoid;
        }
        .pdf-meta-table col.left-col { width: 50%; }
        .pdf-meta-table col.right-col { width: 50%; }
        .pdf-meta-table > tbody > tr > td {
            vertical-align: top;
            padding: 2px 4px;
            width: 50%;
        }
        /* Extra space each side of dashed line so a physical cut does not clip text */
        .pdf-meta-table > tbody > tr > td:first-child {
            border-right: 2px dashed #b3b3b3;
            padding: 2px 3mm 2px 4px;
        }
        .pdf-meta-table > tbody > tr > td:last-child {
            padding: 2px 4px 2px 3mm;
        }
        /* Continuous line items: DomPDF repeats thead on each printed page */
        .pdf-items-table {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-left: auto;
            margin-right: auto;
        }
        .pdf-items-table thead {
            display: table-header-group;
        }
        .pdf-items-table thead tr {
            page-break-inside: avoid;
        }
        .pdf-items-table tbody {
            display: table-row-group;
        }
        .pdf-items-table tr.item-row {
            page-break-inside: avoid;
        }
        /* DomPDF often ignores <colgroup> — widths on thead/tbody cells only (not tfoot colspan row) */
        .pdf-items-table thead th:nth-child(1),
        .pdf-items-table tbody td:nth-child(1),
        .pdf-items-table thead th:nth-child(8),
        .pdf-items-table tbody td:nth-child(8) { width: 5%; }
        .pdf-items-table thead th:nth-child(2),
        .pdf-items-table tbody td:nth-child(2),
        .pdf-items-table thead th:nth-child(9),
        .pdf-items-table tbody td:nth-child(9) { width: 5%; }
        .pdf-items-table thead th:nth-child(3),
        .pdf-items-table tbody td:nth-child(3),
        .pdf-items-table thead th:nth-child(10),
        .pdf-items-table tbody td:nth-child(10) { width: 22%; }
        .pdf-items-table thead th:nth-child(4),
        .pdf-items-table tbody td:nth-child(4),
        .pdf-items-table thead th:nth-child(11),
        .pdf-items-table tbody td:nth-child(11) { width: 4%; }
        .pdf-items-table thead th:nth-child(5),
        .pdf-items-table tbody td:nth-child(5),
        .pdf-items-table thead th:nth-child(12),
        .pdf-items-table tbody td:nth-child(12) { width: 1.5%; max-width: 1.5%; }
        .pdf-items-table thead th:nth-child(6),
        .pdf-items-table tbody td:nth-child(6),
        .pdf-items-table thead th:nth-child(13),
        .pdf-items-table tbody td:nth-child(13) { width: 6%; }
        .pdf-items-table thead th:nth-child(7),
        .pdf-items-table tbody td:nth-child(7),
        .pdf-items-table thead th:nth-child(14),
        .pdf-items-table tbody td:nth-child(14) { width: 6.5%; }

        .pdf-items-table th,
        .pdf-items-table td {
            border: 1px solid #cfcfcf;
            padding: 1px 1.5px;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.15;
            vertical-align: top;
        }
        .pdf-items-table thead th {
            background: #e8e8e8;
            font-weight: bold;
            font-size: 9px;
            white-space: nowrap;
            /* Extra air above/below header row when DomPDF repeats thead on new pages */
            padding-top: 4px;
            padding-bottom: 3px;
        }
        /* Dashed split between supplier (1–7) and company (8–14) columns + cut gutter */
        .pdf-items-table thead th:nth-child(7),
        .pdf-items-table tbody td:nth-child(7) {
            border-right: 2px dashed #b3b3b3;
            padding-top: 4px;
            padding-bottom: 3px;
            padding-right: 2.5mm;
            padding-left: 1.5px;
        }
        .pdf-items-table thead th:nth-child(8),
        .pdf-items-table tbody td:nth-child(8) {
            padding-top: 4px;
            padding-bottom: 3px;
            padding-left: 2.5mm;
            padding-right: 1.5px;
        }
        .pdf-items-table thead th:nth-child(5),
        .pdf-items-table thead th:nth-child(6),
        .pdf-items-table thead th:nth-child(7),
        .pdf-items-table thead th:nth-child(12),
        .pdf-items-table thead th:nth-child(13),
        .pdf-items-table thead th:nth-child(14) {
            text-align: right;
        }
        .pdf-items-table tbody td:nth-child(5),
        .pdf-items-table tbody td:nth-child(6),
        .pdf-items-table tbody td:nth-child(7),
        .pdf-items-table tbody td:nth-child(12),
        .pdf-items-table tbody td:nth-child(13),
        .pdf-items-table tbody td:nth-child(14) {
            text-align: right;
        }
        /* DomPDF does not handle max-width:0 column tricks well — keep receipt/date on one line with clipping */
        .pdf-items-table tbody td:nth-child(1),
        .pdf-items-table tbody td:nth-child(8) {
            font-size: 8.5px;
            white-space: nowrap;
            overflow: hidden;
        }
        .pdf-items-table tbody td:nth-child(2),
        .pdf-items-table tbody td:nth-child(9) {
            font-size: 8.5px;
            white-space: nowrap;
            overflow: hidden;
        }
        .pdf-items-table tbody td:nth-child(4),
        .pdf-items-table tbody td:nth-child(11) {
            white-space: normal;
            word-break: break-word;
        }
        /* Commodity: widest column — prefer wrapping over deep stacking */
        .pdf-items-table thead th:nth-child(3),
        .pdf-items-table thead th:nth-child(10),
        .pdf-items-table tbody td:nth-child(3),
        .pdf-items-table tbody td:nth-child(10) {
            white-space: normal;
            word-break: break-word;
            line-height: 1.12;
        }
        /* Narrow Qty column (preview + paid PDF) */
        .pdf-items-table thead th:nth-child(5),
        .pdf-items-table thead th:nth-child(12),
        .pdf-items-table tbody td:nth-child(5),
        .pdf-items-table tbody td:nth-child(12) {
            font-size: 7.5px;
            padding-left: 0;
            padding-right: 0;
        }
        .pdf-items-table tfoot td {
            border: 1px solid #cfcfcf;
            font-weight: bold;
            font-size: 9.5px;
            background: #e8e8e8;
        }
        /* Zebra striping (body rows only) */
        .pdf-items-table tbody tr.item-row:nth-child(odd) td {
            background-color: #ffffff;
        }
        .pdf-items-table tbody tr.item-row:nth-child(even) td {
            background-color: #f0f0f0;
        }
        .copy-header-block {
            border: 1px solid #bfbfbf;
            padding: 2px 4px;
            margin-bottom: 2px;
        }
        .copy-header {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            margin: 0 0 2px 0;
            line-height: 1.2;
        }
        .company {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            margin: 0 0 1px 0;
            line-height: 1.2;
        }
        .subtitle {
            text-align: center;
            margin: 0 0 1px 0;
            font-size: 10px;
            line-height: 1.2;
        }
        .line {
            margin: 0;
            font-size: 10px;
            line-height: 1.25;
        }
    </style>
</head>
<body>
    @php
        $items = isset($items) ? collect($items) : collect();
    @endphp

    <table class="pdf-meta-table two-copies-table" width="100%" cellpadding="0" cellspacing="0">
        <colgroup>
            <col class="left-col">
            <col class="right-col">
        </colgroup>
        <tbody>
            <tr class="sync-top-tr">
                <td>
                    <div class="copy-header-block">
                        <div class="copy-header">SUPPLIER COPY</div>
                        <div class="company">UTAMADUNI CRAFT CENTRE</div>
                        <div class="subtitle">Payment Details</div>
                        @if(!empty($isPreview))
                        <div class="line" style="color:#a00;font-weight:bold;">DRAFT — not yet paid (preview only)</div>
                        @endif
                        <div class="line"><strong>Supplier:</strong> {{ $payment->supplier->nama ?? 'N/A' }}</div>
                        <div class="line"><strong>Reference No:</strong> {{ $payment->reference_number }}</div>
                        <div class="line"><strong>Start Date:</strong> {{ isset($startDate) && $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/y') : ($payment->date ? \Carbon\Carbon::parse($payment->date)->format('d/m/y') : 'N/A') }}</div>
                        <div class="line"><strong>End Date:</strong> {{ isset($endDate) && $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/y') : ($payment->date ? \Carbon\Carbon::parse($payment->date)->format('d/m/y') : 'N/A') }}</div>
                        <div class="line"><strong>{{ !empty($isPreview) ? 'Total to pay' : 'Total Paid' }}: Ksh {{ number_format($totalPaid, 2) }}</strong></div>
                    </div>
                </td>
                <td>
                    <div class="copy-header-block">
                        <div class="copy-header">COMPANY COPY</div>
                        <div class="company">UTAMADUNI CRAFT CENTRE</div>
                        <div class="subtitle">Payment Details</div>
                        @if(!empty($isPreview))
                        <div class="line" style="color:#a00;font-weight:bold;">DRAFT — not yet paid (preview only)</div>
                        @endif
                        <div class="line"><strong>Supplier:</strong> {{ $payment->supplier->nama ?? 'N/A' }}</div>
                        <div class="line"><strong>Reference No:</strong> {{ $payment->reference_number }}</div>
                        <div class="line"><strong>Start Date:</strong> {{ isset($startDate) && $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/y') : ($payment->date ? \Carbon\Carbon::parse($payment->date)->format('d/m/y') : 'N/A') }}</div>
                        <div class="line"><strong>End Date:</strong> {{ isset($endDate) && $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/y') : ($payment->date ? \Carbon\Carbon::parse($payment->date)->format('d/m/y') : 'N/A') }}</div>
                        <div class="line"><strong>{{ !empty($isPreview) ? 'Total to pay' : 'Total Paid' }}: Ksh {{ number_format($totalPaid, 2) }}</strong></div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <table class="pdf-items-table" width="100%" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>Rec.No</th>
                <th>Date</th>
                <th>Commodity</th>
                <th>Shop</th>
                <th>Qty</th>
                <th>B.P</th>
                <th>Total</th>
                <th>Rec.No</th>
                <th>Date</th>
                <th>Commodity</th>
                <th>Shop</th>
                <th>Qty</th>
                <th>B.P</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            @php
                $qty = (float) ($item['quantity'] ?? 0);
                $unitAmt = (float) ($item['unit_amount'] ?? 0);
                $lineTotal = $qty * $unitAmt;
                $r = $item['receipt_no'] ?? 'N/A';
                $d = $item['date_sold'] ?? 'N/A';
                $p = $item['product_name'] ?? 'N/A';
                $s = $item['shop_name'] ?? 'N/A';
                $q = $item['quantity'];
            @endphp
            <tr class="item-row">
                <td>{{ $r }}</td>
                <td>{{ $d }}</td>
                <td>{{ $p }}</td>
                <td>{{ $s }}</td>
                <td>{{ $q }}</td>
                <td>{{ number_format($unitAmt, 2) }}</td>
                <td>{{ number_format($lineTotal, 2) }}</td>
                <td>{{ $r }}</td>
                <td>{{ $d }}</td>
                <td>{{ $p }}</td>
                <td>{{ $s }}</td>
                <td>{{ $q }}</td>
                <td>{{ number_format($unitAmt, 2) }}</td>
                <td>{{ number_format($lineTotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align: right; padding: 3px 4px;">Grand Total (Ksh)</td>
                <td style="text-align: right; padding: 3px 4px;">{{ number_format($totalPaid ?? 0, 2) }}</td>
                <td colspan="6" style="text-align: right; padding: 3px 4px;">Grand Total (Ksh)</td>
                <td style="text-align: right; padding: 3px 4px;">{{ number_format($totalPaid ?? 0, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans');
            $size = 9;
            $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
            $w = $fontMetrics->get_text_width('Page 000 of 000', $font, $size);
            $x = max(8, ($pdf->get_width() - $w) / 2);
            // Keep page number safely above bottom non-printable area
            $y = $pdf->get_height() - 20;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.35, 0.35, 0.35));
        }
    </script>
</body>
</html>
