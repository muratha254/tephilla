<!DOCTYPE html>
<html>
<head>
    <title>Daily Closing Report - {{ date('F d, Y', strtotime($dailyCash->date)) }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .summary {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 10px;
        }
        .highlight {
            background-color: #d4edda;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="text-transform: uppercase; margin: 5px 0;">{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }}</h2>
        @if($setting && $setting->alamat)
        <p style="font-size: 11px; color: #666; margin: 3px 0;">{{ $setting->alamat }}</p>
        @endif
        <h3 style="margin: 10px 0;">Daily Closing Report</h3>
        <p>{{ date('F d, Y', strtotime($dailyCash->date)) }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <th width="30%">Date:</th>
                <td>{{ date('F d, Y', strtotime($dailyCash->date)) }}</td>
            </tr>
            <tr>
                <th>Cash on Hand (Opening):</th>
                <td><strong>Ksh {{ number_format($dailyCash->opening_cash, 2) }}</strong></td>
            </tr>
            <tr>
                <th>Total Sales:</th>
                <td><strong>Ksh {{ number_format($dailyCash->total_sales, 2) }}</strong></td>
            </tr>
            <tr class="highlight">
                <th>Net Sales:</th>
                <td><strong>Ksh {{ number_format($dailyCash->net_sales, 2) }}</strong></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>{{ $dailyCash->is_closed ? 'Closed' : 'Open' }}</td>
            </tr>
            <tr>
                <th>Opened By:</th>
                <td>{{ $dailyCash->openedByUser->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Opened At:</th>
                <td>{{ $dailyCash->opened_at ? date('Y-m-d H:i:s', strtotime($dailyCash->opened_at)) : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Closed By:</th>
                <td>{{ $dailyCash->closedByUser->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Closed At:</th>
                <td>{{ $dailyCash->closed_at ? date('Y-m-d H:i:s', strtotime($dailyCash->closed_at)) : 'N/A' }}</td>
            </tr>
            @if($dailyCash->notes)
            <tr>
                <th>Notes:</th>
                <td>{{ $dailyCash->notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="footer">
        <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>
        <p>Report Date: {{ date('F d, Y', strtotime($dailyCash->date)) }}</p>
    </div>
</body>
</html>









