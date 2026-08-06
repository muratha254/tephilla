<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Income & Expenses Report</title>
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
            width: 33.33%;
            padding: 0 8px 0 0;
            vertical-align: top;
        }
        .summary-card {
            border: 1px solid #d7dee8;
            border-radius: 4px;
            padding: 12px 14px;
            color: #fff;
        }
        .summary-card.income { background: #00a65a; }
        .summary-card.costs { background: #dd4b39; }
        .summary-card.profit { background: #3c8dbc; }
        .summary-card .label {
            font-size: 8pt;
            opacity: .95;
            margin-bottom: 6px;
        }
        .summary-card .value {
            font-size: 14pt;
            font-weight: bold;
        }
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
        <h2>Income & Expenses Report</h2>
    </div>

    <div class="meta">
        Period: {{ format_fleet_date($dateFrom) }} to {{ format_fleet_date($dateTo) }}
        | Vehicle: {{ $vehicleLabel }}
        | Generated: {{ $generatedAt }}
    </div>

    <table class="summary-row">
        <tr>
            <td>
                <div class="summary-card income">
                    <div class="label">Total Income</div>
                    <div class="value">{{ format_kes($summary['total_income']) }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card costs">
                    <div class="label">Total Costs</div>
                    <div class="value">{{ format_kes($summary['total_costs']) }}</div>
                </div>
            </td>
            <td>
                <div class="summary-card profit">
                    <div class="label">Net Profit</div>
                    <div class="value">{{ format_kes($summary['net_profit']) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">Developed By Codeforts. | Version 8.0</div>
</body>
</html>
