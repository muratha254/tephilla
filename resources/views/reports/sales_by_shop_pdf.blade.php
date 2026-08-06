<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales by Shop</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 20px;
        }
        .header-bar {
            background: #f15a24;
            color: #fff;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 4px;
        }
        .header-bar .title {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .header-bar .period {
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        table th, table td {
            border: 1px solid #e0e0e0;
            padding: 8px;
        }
        table th {
            background-color: #f4f4f4;
            font-weight: bold;
            font-size: 11px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer {
            margin-top: 16px;
            font-size: 10px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <div class="title">{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }} — Sales by Shop</div>
        <div class="period">Period: {{ \Carbon\Carbon::parse($start)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($end)->format('M d, Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Shop</th>
                <th class="text-right">Total Qty</th>
                <th class="text-right">Total Sales</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $row['shop_name'] }}</td>
                <td class="text-right">{{ number_format($row['total_qty'], 2) }}</td>
                <td class="text-right">Ksh {{ number_format($row['total_amount'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center">No data for the selected period.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" class="text-right">Totals</th>
                <th class="text-right">{{ number_format($totalQty, 2) }}</th>
                <th class="text-right">Ksh {{ number_format($totalAmount, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generated on: {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>










