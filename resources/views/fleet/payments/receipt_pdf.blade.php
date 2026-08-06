<!DOCTYPE html>

<html>

<head>

    <meta charset="utf-8">

    <title>Payment Receipt - {{ $payment->receiptNumber() }}</title>

    <style>

        @include('fleet.partials.pdf_edge_layout_styles')

        body {

            font-family: 'DejaVu Sans', Arial, sans-serif;

            font-size: 10pt;

            color: #222;

        }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }

        .header-table td { vertical-align: top; }

        .receipt-title { font-size: 18pt; font-weight: bold; color: #00a65a; text-align: right; margin: 0 0 4px; }

        .receipt-meta { text-align: right; font-size: 9pt; line-height: 1.5; }

        .receipt-meta-bar { margin-bottom: 8px; text-align: right; }

        .section-title {

            font-size: 9pt;

            font-weight: bold;

            color: #3c8dbc;

            text-transform: uppercase;

            margin: 0 0 4px;

            padding-bottom: 3px;

            border-bottom: 1px solid #d7dee8;

        }

        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }

        .info-grid td { width: 50%; vertical-align: top; padding-right: 10px; font-size: 9pt; line-height: 1.45; }

        .amount-box {

            margin: 8px 0;

            padding: 10px;

            background: #f0f9f4;

            border: 1px solid #b8e6c8;

            text-align: center;

        }

        .amount-box .label { font-size: 8.5pt; color: #666; margin-bottom: 4px; }

        .amount-box .value { font-size: 16pt; font-weight: bold; color: #00a65a; }

        .customer-balance-note {

            margin: 0 0 8px;

            padding: 8px 10px;

            background: #fff8e6;

            border: 1px solid #f3d19c;

            font-size: 8.5pt;

            color: #8a6d3b;

        }

        table.details {

            width: 100%;

            border-collapse: collapse;

            margin-top: 4px;

        }

        table.details th {

            background: #f0f4f8;

            color: #2e3d4f;

            font-size: 8pt;

            font-weight: bold;

            text-transform: uppercase;

            padding: 6px 8px;

            border: 1px solid #d7dee8;

            text-align: left;

        }

        table.details td {

            padding: 6px 8px;

            border: 1px solid #e3e8ef;

            font-size: 9pt;

        }

        table.details .right { text-align: right; white-space: nowrap; }

        .notes { margin-top: 8px; font-size: 8.5pt; color: #666; line-height: 1.4; }

        .receipt-body { page-break-inside: avoid; }

    </style>

</head>

<body>

    @include('fleet.partials.pdf_document_layout_open')



    <div class="receipt-body">

    @if (! empty($hasDocumentHeaderImage))

        <div class="receipt-meta-bar">

            <div class="receipt-title">RECEIPT</div>

            <div class="receipt-meta">

                <strong>Receipt No:</strong> {{ $payment->receiptNumber() }} |

                <strong>Date:</strong> {{ $payment->formattedDate() }} |

                <strong>Generated:</strong> {{ $generatedAt }}

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

                    <div class="receipt-title">RECEIPT</div>

                    <div class="receipt-meta">

                        <strong>Receipt No:</strong> {{ $payment->receiptNumber() }}<br>

                        <strong>Payment Date:</strong> {{ $payment->formattedDate() }}<br>

                        <strong>Generated:</strong> {{ $generatedAt }}

                    </div>

                </td>

            </tr>

        </table>

    @endif



    <table class="info-grid">

        <tr>

            <td>

                <div class="section-title">Received From</div>

                <strong>{{ $payment->customer?->name ?: '-' }}</strong><br>

                @if ($payment->customer?->mobile)

                    Mobile: {{ $payment->customer->mobile }}<br>

                @endif

                @if ($payment->customer?->email)

                    Email: {{ $payment->customer->email }}

                @endif

            </td>

            <td>

                <div class="section-title">Trip Details</div>

                @if ($trip)

                    <strong>Invoice:</strong> {{ $trip->invoiceNumber() }}<br>

                    <strong>Trip Ref:</strong> {{ $trip->displayTripCode() }}<br>

                    <strong>Route:</strong> {{ $trip->routeLocationShort($trip->pickup_location) }} → {{ $trip->routeLocationShort($trip->drop_location) }}

                @else

                    -

                @endif

            </td>

        </tr>

    </table>



    <div class="amount-box">

        <div class="label">Amount Received</div>

        <div class="value">{{ format_kes($payment->amount) }}</div>

    </div>



    @if ($customerOutstanding !== null)

        <div class="customer-balance-note">

            <strong>Customer Account Balance:</strong> {{ format_kes($customerOutstanding) }} outstanding across all trips.

        </div>

    @endif



    <table class="details avoid-break">

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



    @include('fleet.partials.pdf_document_layout_close')

</body>

</html>

