<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Trip Invoice - {{ $trip->invoiceNumber() }}</title>
    <style>
        @include('fleet.partials.pdf_edge_layout_styles')
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #222;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #2e3d4f;
            margin: 0 0 6px;
        }
        .company-meta {
            font-size: 9pt;
            color: #666;
            line-height: 1.5;
        }
        .invoice-title {
            font-size: 18pt;
            font-weight: bold;
            color: #3c8dbc;
            text-align: right;
            margin: 0 0 4px;
        }
        .invoice-meta {
            text-align: right;
            font-size: 9pt;
            line-height: 1.5;
        }
        .invoice-meta strong {
            color: #2e3d4f;
        }
        .invoice-meta-bar {
            width: 100%;
            margin-bottom: 10px;
            text-align: right;
        }
        .section-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: #3c8dbc;
            text-transform: uppercase;
            margin: 0 0 5px;
            padding-bottom: 3px;
            border-bottom: 1px solid #d7dee8;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-grid td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }
        .info-box {
            font-size: 9pt;
            line-height: 1.45;
        }
        .info-box strong {
            color: #2e3d4f;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.items th {
            background: #f0f4f8;
            color: #2e3d4f;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #d7dee8;
            text-align: left;
        }
        table.items td {
            padding: 6px 8px;
            border: 1px solid #e3e8ef;
            font-size: 9pt;
            vertical-align: top;
        }
        table.items .right {
            text-align: right;
            white-space: nowrap;
        }
        .totals-wrap {
            width: 100%;
            margin-top: 8px;
        }
        .totals-wrap td {
            vertical-align: top;
        }
        .notes {
            font-size: 8.5pt;
            color: #666;
            line-height: 1.5;
            padding-right: 20px;
        }
        table.totals {
            width: 240px;
            margin-left: auto;
            border-collapse: collapse;
        }
        table.totals td {
            padding: 5px 8px;
            border: 1px solid #e3e8ef;
            font-size: 9pt;
        }
        table.totals td.label {
            color: #666;
        }
        table.totals td.amount {
            text-align: right;
            font-weight: bold;
            color: #2e3d4f;
        }
        table.totals tr.grand td {
            background: #f0f4f8;
            font-size: 11pt;
            color: #3c8dbc;
        }
        .status-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            background: #eef2f6;
            color: #2e3d4f;
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: capitalize;
        }
    </style>
</head>
<body>
    @include('fleet.partials.pdf_document_layout_open')
    @if (!empty($hasDocumentHeaderImage))
        <div class="invoice-meta-bar">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-meta">
                <strong>Invoice No:</strong> {{ $trip->invoiceNumber() }} |
                <strong>Date:</strong> {{ $invoiceDate }} |
                <strong>Trip Ref:</strong> {{ $trip->displayTripCode() }} |
                <strong>Status:</strong> <span class="status-tag">{{ $trip->statusLabel() }}</span>
            </div>
            @include('fleet.partials.pdf_document_header')
        </div>
    @else
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                @include('fleet.partials.pdf_document_header')
            </td>
            <td style="width: 45%;">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">
                    <strong>Invoice No:</strong> {{ $trip->invoiceNumber() }}<br>
                    <strong>Invoice Date:</strong> {{ $invoiceDate }}<br>
                    <strong>Trip Ref:</strong> {{ $trip->displayTripCode() }}<br>
                    <strong>Status:</strong> <span class="status-tag">{{ $trip->statusLabel() }}</span>
                </div>
            </td>
        </tr>
    </table>
    @endif

    <table class="info-grid">
        <tr>
            <td>
                <div class="section-title">Bill To</div>
                <div class="info-box">
                    <strong>{{ $trip->customer_name }}</strong><br>
                    @if ($trip->customer_phone)
                        Phone: {{ $trip->customer_phone }}<br>
                    @endif
                    Trip Type: {{ $trip->trip_type }}
                </div>
            </td>
            <td>
                <div class="section-title">Trip Details</div>
                <div class="info-box">
                    <strong>Pickup:</strong> {{ $trip->pickup_location }}<br>
                    <strong>Drop:</strong> {{ $trip->drop_location }}<br>
                    @if ($trip->container_number)
                        <strong>Container No:</strong> {{ $trip->container_number }}<br>
                    @endif
                    @if ($trip->container_empty_drop_point)
                        <strong>Empty Drop Point:</strong> {{ $trip->container_empty_drop_point }}<br>
                    @endif
                    <strong>Start:</strong> {{ $trip->formattedStart() }}<br>
                    <strong>End:</strong> {{ $trip->formattedEnd() }}
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">Charges</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th>Description</th>
                <th style="width: 18%;">Billing</th>
                <th style="width: 16%;" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    Transport service — {{ $trip->trip_type }}<br>
                    <span style="color:#666;font-size:8.5pt;">
                        {{ $trip->pickup_location }} to {{ $trip->drop_location }}
                    </span>
                    @if ($trip->billingBreakdownLabel())
                        <br><span style="color:#666;font-size:8.5pt;">{{ $trip->billingBreakdownLabel() }}</span>
                    @endif
                    @if (! empty($trip->additional_stops))
                        <br><span style="color:#666;font-size:8.5pt;">
                            Stops: {{ implode(', ', $trip->additional_stops) }}
                        </span>
                    @endif
                </td>
                <td>{{ $trip->billing_type }}</td>
                <td class="right">{{ format_kes($trip->base_amount) }}</td>
            </tr>
            @if ((float) $trip->discount_amount > 0)
            <tr>
                <td>2</td>
                <td>
                    Discount
                    @if ($trip->coupon_code)
                        (Coupon: {{ $trip->coupon_code }})
                    @endif
                </td>
                <td>-</td>
                <td class="right">- {{ format_kes($trip->discount_amount) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table class="totals-wrap avoid-break">
        <tr>
            <td style="width: 58%;">
                <div class="notes">
                    <strong>Payment Terms:</strong> Due on receipt.<br>
                    All amounts are in Kenya Shillings (KSh).<br>
                    @if ($trip->tax_type && $trip->tax_type !== 'No Tax')
                        Tax applied: {{ $trip->tax_type }}.
                    @else
                        No tax applied on this invoice.
                    @endif
                </div>
            </td>
            <td style="width: 42%;">
                <table class="totals">
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="amount">{{ format_kes($trip->subtotalAmount()) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Tax ({{ $trip->tax_type ?: 'No Tax' }})</td>
                        <td class="amount">{{ format_kes($trip->taxAmount()) }}</td>
                    </tr>
                    <tr class="grand">
                        <td class="label">Total Due</td>
                        <td class="amount">{{ format_kes($trip->totalAmount()) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    @include('fleet.partials.pdf_document_layout_close')
</body>
</html>
