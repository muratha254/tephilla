<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Receipt — {{ $payment->receiptNumber() }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #222;
            margin: 0;
            padding: 24px;
            background: #f4f6f9;
        }
        .receipt-shell {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #d7dee8;
            border-radius: 6px;
            padding: 0;
            overflow: hidden;
        }
        .document-body {
            padding: 28px;
        }
        .document-edge-header {
            width: 100%;
            margin: 0;
            padding: 0;
            line-height: 0;
        }
        .document-edge-header img,
        .document-header-image {
            width: 100%;
            height: auto;
            display: block;
            margin: 0;
            padding: 0;
            border: 0;
        }
        .document-edge-footer {
            width: 100%;
            margin: 0;
            padding: 16px 0;
            border-top: 1px solid #eef2f6;
            text-align: center;
            font-size: 12px;
            color: #888;
            line-height: 1.5;
        }
        .document-edge-footer-image {
            width: 100%;
            margin: 0;
            padding: 0;
            line-height: 0;
        }
        .document-edge-footer-image img,
        .document-footer-image {
            width: 100%;
            height: auto;
            display: block;
            margin: 0;
            padding: 0;
            border: 0;
        }
        .document-edge-footer-text {
            width: 100%;
            margin: 0;
            padding: 16px 0;
            border-top: 1px solid #eef2f6;
            text-align: center;
            font-size: 12px;
            color: #888;
            line-height: 1.5;
        }
        .document-edge-footer-text.has-footer-image {
            border-top: none;
            padding-top: 12px;
        }
        .receipt-toolbar {
            max-width: 720px;
            margin: 0 auto 16px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .btn {
            border: none;
            border-radius: 4px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary { background: #3c8dbc; color: #fff; }
        .btn-success { background: #00a65a; color: #fff; }
        .btn-light { background: #fff; color: #444; border: 1px solid #d7dee8; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .company-name { font-size: 24px; font-weight: 700; color: #2e3d4f; }
        .company-meta { font-size: 13px; color: #666; line-height: 1.6; margin-top: 6px; }
        .receipt-title { font-size: 28px; font-weight: 700; color: #00a65a; text-align: right; }
        .receipt-meta { text-align: right; font-size: 13px; line-height: 1.7; margin-top: 8px; }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #3c8dbc;
            text-transform: uppercase;
            margin: 0 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #eef2f6;
        }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px; }
        .info-block { font-size: 14px; line-height: 1.6; }
        .amount-box {
            margin: 20px 0;
            padding: 20px;
            background: #f0f9f4;
            border: 1px solid #b8e6c8;
            border-radius: 6px;
            text-align: center;
        }
        .amount-box span { display: block; font-size: 13px; color: #666; margin-bottom: 6px; }
        .amount-box strong { font-size: 32px; color: #00a65a; }
        .balance-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .balance-card {
            border: 1px solid #e3e8ef;
            border-radius: 6px;
            background: #f8fafc;
            padding: 14px;
            text-align: center;
        }
        .balance-card span {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .balance-card strong {
            font-size: 18px;
            color: #2e3d4f;
        }
        .balance-card.is-remaining strong { color: #dd4b39; }
        .balance-card.is-clear strong { color: #00a65a; }
        .customer-balance-note {
            margin-bottom: 20px;
            padding: 12px 14px;
            background: #fff8e6;
            border: 1px solid #f3d19c;
            border-radius: 6px;
            font-size: 13px;
            color: #8a6d3b;
        }
        .details-table { width: 100%; border-collapse: collapse; }
        .details-table th, .details-table td {
            border: 1px solid #e3e8ef;
            padding: 10px;
            font-size: 13px;
            text-align: left;
        }
        .details-table th { background: #f8fafc; }
        .details-table .right { text-align: right; }
        .notes { margin-top: 16px; font-size: 13px; color: #666; }
        .document-header-text {
            margin-bottom: 12px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e3e8ef;
            border-radius: 4px;
            font-size: 13px;
            color: #555;
            line-height: 1.5;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt-toolbar { display: none; }
            .receipt-shell { border: none; box-shadow: none; max-width: none; border-radius: 0; }
            .document-body { padding: 10mm 14mm; }
        }
    </style>
</head>
<body>
    <div class="receipt-toolbar">
        <a href="{{ route('payments.receipt-pdf', $payment) }}" class="btn btn-success">Download PDF</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print Receipt</button>
        <a href="{{ route('payments.history') }}" class="btn btn-light">Back to Payment History</a>
        <a href="{{ route('payments.index') }}" class="btn btn-light">Customer Payments</a>
    </div>

    <div class="receipt-shell">
        @include('fleet.partials.pdf_document_header_image', ['useFixedDocumentChrome' => false])

        <div class="document-body">
        <table class="header-table">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    @include('fleet.partials.pdf_document_header')
                </td>
                <td style="width: 45%; vertical-align: top;">
                    <div class="receipt-title">RECEIPT</div>
                    <div class="receipt-meta">
                        <strong>Receipt No:</strong> {{ $payment->receiptNumber() }}<br>
                        <strong>Payment Date:</strong> {{ $payment->formattedDate() }}<br>
                        <strong>Generated:</strong> {{ $generatedAt }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="info-grid">
            <div class="info-block">
                <div class="section-title">Received From</div>
                <strong>{{ $payment->customer?->name ?: '-' }}</strong><br>
                @if ($payment->customer?->mobile)
                    Mobile: {{ $payment->customer->mobile }}<br>
                @endif
            </div>
            <div class="info-block">
                <div class="section-title">Trip Details</div>
                @if ($trip)
                    <strong>Invoice:</strong> {{ $trip->invoiceNumber() }}<br>
                    <strong>Trip Ref:</strong> {{ $trip->displayTripCode() }}<br>
                    <strong>Route:</strong> {{ $trip->routeLocationShort($trip->pickup_location) }} → {{ $trip->routeLocationShort($trip->drop_location) }}
                @else
                    -
                @endif
            </div>
        </div>

        <div class="amount-box">
            <span>Amount Received</span>
            <strong>{{ format_kes($payment->amount) }}</strong>
        </div>

        @if ($trip)
            <div class="balance-summary">
                <div class="balance-card">
                    <span>Trip Total</span>
                    <strong>{{ format_kes($tripTotal) }}</strong>
                </div>
                <div class="balance-card">
                    <span>Total Paid After Receipt</span>
                    <strong>{{ format_kes($tripPaid) }}</strong>
                </div>
                <div class="balance-card {{ $tripRemaining > 0 ? 'is-remaining' : 'is-clear' }}">
                    <span>Remaining Balance</span>
                    <strong>{{ format_kes($tripRemaining) }}</strong>
                </div>
            </div>
        @endif

        @if ($customerOutstanding !== null)
            <div class="customer-balance-note">
                <strong>Customer Account Balance:</strong> {{ format_kes($customerOutstanding) }} outstanding across all trips.
            </div>
        @endif

        <table class="details-table">
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th>Reference</th>
                    <th class="right">Trip Total</th>
                    <th class="right">Total Paid</th>
                    <th class="right">Remaining Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $payment->payment_method }}</td>
                    <td>{{ $payment->reference_no ?: '-' }}</td>
                    <td class="right">{{ format_kes($tripTotal) }}</td>
                    <td class="right">{{ format_kes($tripPaid) }}</td>
                    <td class="right">{{ format_kes($tripRemaining) }}</td>
                </tr>
            </tbody>
        </table>

        @if ($payment->notes)
            <div class="notes"><strong>Notes:</strong> {{ $payment->notes }}</div>
        @endif
        </div>

        @include('fleet.partials.pdf_document_footer', ['useFixedDocumentChrome' => false])
    </div>
</body>
</html>
