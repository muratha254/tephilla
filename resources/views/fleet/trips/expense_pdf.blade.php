<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Trip Expense Report - {{ $trip->expenseReportNumber() }}</title>
    <style>
        @page { margin: 14mm; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .header-table td { vertical-align: top; }
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
        .report-title {
            font-size: 22pt;
            font-weight: bold;
            color: #f39c12;
            text-align: right;
            margin: 0 0 8px;
        }
        .report-meta {
            text-align: right;
            font-size: 9.5pt;
            line-height: 1.7;
        }
        .report-meta strong { color: #2e3d4f; }
        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #f39c12;
            text-transform: uppercase;
            margin: 0 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #d7dee8;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .info-grid td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
            font-size: 9.5pt;
            line-height: 1.6;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.items th {
            background: #fff8eb;
            color: #2e3d4f;
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 9px 8px;
            border: 1px solid #d7dee8;
            text-align: left;
        }
        table.items td {
            padding: 9px 8px;
            border: 1px solid #e3e8ef;
            font-size: 9.5pt;
            vertical-align: top;
        }
        table.items .right {
            text-align: right;
            white-space: nowrap;
        }
        table.totals {
            width: 240px;
            margin-left: auto;
            margin-top: 12px;
            border-collapse: collapse;
        }
        table.totals td {
            padding: 8px 10px;
            border: 1px solid #e3e8ef;
            font-size: 10pt;
        }
        table.totals td.label { color: #666; }
        table.totals td.amount {
            text-align: right;
            font-weight: bold;
            color: #f39c12;
            font-size: 12pt;
        }
        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #d7dee8;
            font-size: 8pt;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                @include('fleet.partials.pdf_company_block')
            </td>
            <td style="width: 45%;">
                <div class="report-title">EXPENSE REPORT</div>
                <div class="report-meta">
                    <strong>Report No:</strong> {{ $trip->expenseReportNumber() }}<br>
                    <strong>Report Date:</strong> {{ $reportDate }}<br>
                    <strong>Trip Ref:</strong> {{ $trip->displayTripCode() }}<br>
                    <strong>Customer:</strong> {{ $trip->customer_name }}
                </div>
            </td>
        </tr>
    </table>

    <table class="info-grid">
        <tr>
            <td>
                <div class="section-title">Trip Route</div>
                <strong>From:</strong> {{ $trip->pickup_location }}<br>
                <strong>To:</strong> {{ $trip->drop_location }}<br>
                <strong>Period:</strong> {{ $trip->formattedStart() }} to {{ $trip->formattedEnd() }}
            </td>
            <td>
                <div class="section-title">Assigned Resources</div>
                <strong>Vehicle:</strong> {{ optional($trip->vehicle)->displayName() ?: 'No Vehicle' }}
                @if ($trip->vehicle)
                    ({{ $trip->vehicle->registration_number }})
                @endif
                <br>
                <strong>Driver:</strong> {{ optional($trip->driver)->name ?: 'Unassigned' }}
            </td>
        </tr>
    </table>

    <div class="section-title">Expense Breakdown</div>
    <table class="items">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 14%;">Date</th>
                <th style="width: 16%;">Category</th>
                <th>Description</th>
                <th style="width: 14%;">Payment</th>
                <th style="width: 12%;">Reference</th>
                <th style="width: 14%;" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $index => $expense)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $expense->formattedDate() }}</td>
                <td>{{ $expense->category }}</td>
                <td>{{ $expense->description ?: '-' }}</td>
                <td>{{ $expense->payment_method }}</td>
                <td>{{ $expense->reference_no ?: '-' }}</td>
                <td class="right">{{ format_kes($expense->amount) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align:center;color:#888;">No expenses recorded.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Total Expenses</td>
            <td class="amount">{{ format_kes($totalExpenses) }}</td>
        </tr>
    </table>

    <div class="footer">
        Generated on {{ $generatedAt }} | {{ $companyName }} | All amounts in Kenya Shillings (KSh).
    </div>
</body>
</html>
