<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reminders Report</title>
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
        .header h1 { font-size: 16pt; margin: 0 0 4px; color: #2e3d4f; }
        .header h2 { font-size: 12pt; margin: 0; color: #3c8dbc; font-weight: normal; }
        .meta { margin-bottom: 14px; font-size: 8pt; color: #666; }
        .summary-row { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary-row td { width: 33.33%; padding: 0 8px 0 0; }
        .summary-card { border-radius: 4px; padding: 10px 12px; color: #fff; }
        .summary-card.total { background: #00c0ef; }
        .summary-card.completed { background: #00a65a; }
        .summary-card.pending { background: #f39c12; }
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
        table.data tbody tr:nth-child(even) { background: #fbfcfd; }
        .footer { margin-top: 14px; font-size: 8pt; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @include('fleet.partials.pdf_report_header')
        <h2>Reminders Report</h2>
    </div>

    <div class="meta">
        Period: {{ format_fleet_date($dateFrom) }} to {{ format_fleet_date($dateTo) }}
        @if ($search)
            | Search: {{ $search }}
        @endif
        | Generated: {{ $generatedAt }}
    </div>

    <table class="summary-row">
        <tr>
            <td>
                <div class="summary-card total">
                    <div class="label">Total</div>
                    <div class="value">{{ number_format($summary['total']) }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card completed">
                    <div class="label">Completed</div>
                    <div class="value">{{ number_format($summary['completed']) }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card pending">
                    <div class="label">Pending</div>
                    <div class="value">{{ number_format($summary['pending']) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>S.No</th>
                <th>Date</th>
                <th>Reminder Title</th>
                <th>Vehicle</th>
                <th>Services</th>
                <th>Due Status</th>
                <th>Completion</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
            <tr>
                <td>{{ $row['serial'] }}</td>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['title'] }}</td>
                <td>{{ $row['vehicle_name'] }}</td>
                <td>{{ implode(', ', $row['services']) ?: '-' }}</td>
                <td>{{ $row['due_status'] }}</td>
                <td>{{ $row['completion_status'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7">No reminders found for this period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Developed By Codeforts. | Version 8.0</div>
</body>
</html>
