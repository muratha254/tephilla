<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fuel Report</title>
    <style>
        @page { margin: 14mm; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3c8dbc;
        }
        .header h1 {
            font-size: 16pt;
            margin: 0 0 4px;
            color: #2e3d4f;
        }
        .header h2 {
            font-size: 12pt;
            margin: 0;
            color: #3c8dbc;
            font-weight: normal;
        }
        .meta {
            margin-bottom: 16px;
            font-size: 8.5pt;
            color: #666;
        }
        .summary-row {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .summary-row td {
            width: 50%;
            padding: 0 8px 0 0;
            vertical-align: top;
        }
        .summary-card {
            border-radius: 4px;
            padding: 12px 14px;
            color: #fff;
        }
        .summary-card.liters { background: #00c0ef; }
        .summary-card.cost { background: #00a65a; }
        .summary-card .label {
            font-size: 8pt;
            opacity: .95;
            margin-bottom: 6px;
        }
        .summary-card .value {
            font-size: 14pt;
            font-weight: bold;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.data th {
            background: #2e3d4f;
            color: #fff;
            font-size: 8pt;
            text-transform: uppercase;
            padding: 8px 6px;
            border: 1px solid #243244;
            text-align: left;
        }
        table.data td {
            padding: 7px 6px;
            border: 1px solid #e3e8ef;
            font-size: 8.5pt;
        }
        table.data tbody tr:nth-child(even) {
            background: #fbfcfd;
        }
        .right { text-align: right; }
        .footer {
            margin-top: 18px;
            font-size: 8pt;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        @include('fleet.partials.pdf_report_header')
        <h2>Fuel Report</h2>
    </div>

    <div class="meta">
        Period: {{ format_fleet_date($dateFrom) }} to {{ format_fleet_date($dateTo) }}
        | Vehicle: {{ $vehicleLabel }}
        | Generated: {{ $generatedAt }}
    </div>

    <table class="summary-row">
        <tr>
            <td>
                <div class="summary-card liters">
                    <div class="label">Total Fuel Consumed</div>
                    <div class="value">{{ number_format($summary['total_liters'], 2) }} Liters</div>
                </div>
            </td>
            <td>
                <div class="summary-card cost">
                    <div class="label">Total Cost</div>
                    <div class="value">{{ format_kes($summary['total_cost']) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th class="right">Liters</th>
                <th class="right">Cost</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td class="right">{{ number_format($row['liters'], 2) }}</td>
                <td class="right">{{ format_kes($row['cost']) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3">No fuel records found for this period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Developed By Codeforts. | Version 8.0</div>
</body>
</html>
