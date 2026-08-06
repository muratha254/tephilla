<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Payments Report</title>
    <style>
        @page { margin: 12mm; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .header { text-align: center; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid #3c8dbc; }
        .header h1 { font-size: 16pt; margin: 0 0 4px; color: #2e3d4f; }
        .header h2 { font-size: 12pt; margin: 0; color: #3c8dbc; font-weight: normal; }
        .meta { margin-bottom: 14px; font-size: 8.5pt; color: #666; line-height: 1.6; }
        .summary-row { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary-row td { width: 50%; padding-right: 8px; vertical-align: top; }
        .summary-card { border-radius: 4px; padding: 10px 12px; color: #fff; }
        .summary-card.count { background: #00c0ef; }
        .summary-card.total { background: #00a65a; }
        .summary-card.outstanding { background: #dd4b39; }
        .summary-row.is-three-col td { width: 33.33%; }
        .summary-card .label { font-size: 8pt; margin-bottom: 4px; }
        .summary-card .value { font-size: 13pt; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #2e3d4f;
            color: #fff;
            font-size: 7.5pt;
            text-transform: uppercase;
            padding: 7px 5px;
            border: 1px solid #243244;
            text-align: left;
        }
        table.data td {
            padding: 6px 5px;
            border: 1px solid #e3e8ef;
            font-size: 8pt;
            vertical-align: top;
        }
        table.data .right { text-align: right; white-space: nowrap; }
        table.data tfoot td {
            background: #f0f4f8;
            font-weight: bold;
            border-top: 2px solid #d7dee8;
        }
        .footer { margin-top: 16px; font-size: 8pt; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @include('fleet.partials.pdf_report_header')
        <h2>Customer Payments Report</h2>
    </div>

    <div class="meta">
        <strong>Customer:</strong> {{ $customerLabel }}<br>
        @if ($dateFrom || $dateTo)
            <strong>Period:</strong>
            {{ $dateFrom ? format_fleet_date($dateFrom) : 'Start' }}
            to
            {{ $dateTo ? format_fleet_date($dateTo) : 'End' }}<br>
        @endif
        @if ($search !== '')
            <strong>Search:</strong> {{ $search }}<br>
        @endif
        <strong>Generated:</strong> {{ $generatedAt }}
    </div>

    <table class="summary-row {{ $customerOutstanding !== null ? 'is-three-col' : '' }}">
        <tr>
            <td>
                <div class="summary-card count">
                    <div class="label">Payments in Report</div>
                    <div class="value">{{ number_format($summary['payment_count']) }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card total">
                    <div class="label">Total Collected</div>
                    <div class="value">{{ format_kes($summary['total_collected']) }}</div>
                </div>
            </td>
            @if ($customerOutstanding !== null)
                <td>
                    <div class="summary-card outstanding">
                        <div class="label">Customer Remaining Balance</div>
                        <div class="value">{{ format_kes($customerOutstanding) }}</div>
                    </div>
                </td>
            @endif
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Receipt</th>
                <th>Customer</th>
                <th>Trip / Invoice</th>
                <th>Method</th>
                <th>Reference</th>
                <th class="right">Amount</th>
                <th class="right">Remaining Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $index => $payment)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ format_fleet_date($payment->payment_date) }}</td>
                    <td>{{ $payment->receiptNumber() }}</td>
                    <td>{{ $payment->customer?->name ?: '-' }}</td>
                    <td>{{ $payment->trip?->invoiceNumber() ?: '-' }}</td>
                    <td>{{ $payment->payment_method }}</td>
                    <td>{{ $payment->reference_no ?: '-' }}</td>
                    <td class="right">{{ format_kes($payment->amount) }}</td>
                    <td class="right">{{ $payment->trip ? format_kes($payment->remainingAfterPayment()) : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No payments found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($payments->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="7" class="right"><strong>Total Collected</strong></td>
                    <td class="right"><strong>{{ format_kes($summary['total_collected']) }}</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">{{ $companyName }} — Customer Payments Report</div>
</body>
</html>
