<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Trips Report</title>
    <style>
        @page { margin: 12mm; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3c8dbc;
        }
        .header h2 {
            font-size: 12pt;
            margin: 0;
            color: #3c8dbc;
            font-weight: normal;
        }
        .meta {
            margin-bottom: 14px;
            font-size: 8pt;
            color: #666;
            line-height: 1.5;
        }
        .summary-row {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .summary-row td {
            width: 20%;
            padding: 0 6px 0 0;
            vertical-align: top;
        }
        .summary-card {
            border-radius: 4px;
            padding: 8px 10px;
            color: #fff;
        }
        .summary-card.bookings { background: #00c0ef; }
        .summary-card.revenue { background: #00a65a; }
        .summary-card.expenses { background: #dd4b39; }
        .summary-card.profit { background: #3c8dbc; }
        .summary-card.distance { background: #f39c12; }
        .summary-card .label {
            font-size: 7pt;
            margin-bottom: 4px;
        }
        .summary-card .value {
            font-size: 11pt;
            font-weight: bold;
        }
        .status-breakdown {
            margin-bottom: 12px;
            font-size: 8pt;
            color: #555;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data th {
            background: #2e3d4f;
            color: #fff;
            font-size: 7pt;
            text-transform: uppercase;
            padding: 6px 4px;
            border: 1px solid #243244;
            text-align: left;
        }
        table.data td {
            padding: 5px 4px;
            border: 1px solid #e3e8ef;
            font-size: 7.5pt;
            vertical-align: top;
        }
        table.data tbody tr:nth-child(even) {
            background: #fbfcfd;
        }
        table.data tfoot td {
            background: #f0f4f8;
            font-weight: bold;
        }
        .right { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .profit-positive { color: #00a65a; font-weight: bold; }
        .profit-negative { color: #dd4b39; font-weight: bold; }
        .footer {
            margin-top: 14px;
            font-size: 8pt;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        @include('fleet.partials.pdf_report_header')
        <h2>Trips Report</h2>
    </div>

    <div class="meta">
        Period: {{ format_fleet_date($dateFrom) }} to {{ format_fleet_date($dateTo) }}<br>
        Vehicle: {{ $vehicleLabel }} | Customer: {{ $customerLabel }} | Status: {{ $statusLabel }}<br>
        Generated: {{ $generatedAt }}
    </div>

    <table class="summary-row">
        <tr>
            <td>
                <div class="summary-card bookings">
                    <div class="label">Total Trips</div>
                    <div class="value">{{ number_format($summary['total_bookings']) }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card revenue">
                    <div class="label">Total Trip Amount</div>
                    <div class="value">{{ format_kes($summary['total_revenue']) }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card expenses">
                    <div class="label">Total Expenses</div>
                    <div class="value">{{ format_kes($summary['total_expenses']) }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card profit">
                    <div class="label">Total Profit</div>
                    <div class="value">{{ format_kes($summary['total_profit']) }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card distance">
                    <div class="label">Total Distance</div>
                    <div class="value">{{ number_format($summary['total_distance'], 2) }} km</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="status-breakdown">
        <strong>Status:</strong>
        Completed {{ $statusCounts['Completed'] ?? 0 }},
        Ongoing {{ $statusCounts['Ongoing'] ?? 0 }},
        Yet to Start {{ $statusCounts['Yet to Start'] ?? 0 }},
        Cancelled {{ $statusCounts['Cancelled'] ?? 0 }}
    </div>

    <table class="data">
        <thead>
            <tr>
                <th class="center">S.No</th>
                <th>Trip Ref</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Route</th>
                <th>Status</th>
                <th class="right">Trip Amount</th>
                <th class="right">Expenses</th>
                <th class="right">Profit</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tripRows as $row)
            <tr>
                <td class="center">{{ $row['serial'] }}</td>
                <td>{{ $row['trip_code'] }}</td>
                <td>{{ $row['start_date'] }}</td>
                <td>{{ $row['customer_name'] }}</td>
                <td>{{ $row['vehicle_name'] }}</td>
                <td>{{ $row['route'] }}</td>
                <td>{{ $row['status'] }}</td>
                <td class="right">{{ format_kes($row['trip_amount']) }}</td>
                <td class="right">{{ format_kes($row['expenses']) }}</td>
                <td class="right">
                    <span class="{{ $row['profit'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                        {{ format_kes($row['profit']) }}
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10">No trips found for the selected filters.</td>
            </tr>
            @endforelse
        </tbody>
        @if ($tripRows->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="7" class="right"><strong>Totals</strong></td>
                <td class="right"><strong>{{ format_kes($summary['total_revenue']) }}</strong></td>
                <td class="right"><strong>{{ format_kes($summary['total_expenses']) }}</strong></td>
                <td class="right">
                    <strong class="{{ $summary['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                        {{ format_kes($summary['total_profit']) }}
                    </strong>
                </td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footer">Developed By Codeforts. | Version 8.0</div>
</body>
</html>
