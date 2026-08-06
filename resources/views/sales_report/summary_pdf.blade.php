<!DOCTYPE html>
<html>
<head>
    <title>Daily Sales Report</title>
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
        .summary-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 15px 0 10px 0;
        }
        .summary-card {
            background: #f7f7f7;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 10px;
        }
        .summary-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #777;
            margin-bottom: 4px;
        }
        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
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
        <div class="title">{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }} — Daily Sales Report</div>
        <div class="period">Period: {{ $start->format('M d, Y') }} to {{ $end->format('M d, Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th class="text-right">Cash on Hand</th>
                <th class="text-right">Gross Sales</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Total Sales</th>
                <th class="text-right">Net Sales</th>
                <th class="text-right">VAT</th>
                <th class="text-right">Total – VAT</th>
                <th class="text-center">Transactions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($summaryData as $row)
            <tr>
                <td>{{ $row['date_formatted'] }}</td>
                <td class="text-right">Ksh {{ number_format($row['cash_on_hand'], 2) }}</td>
                <td class="text-right">Ksh {{ number_format($row['gross_sales'] ?? $row['total_sales'], 2) }}</td>
                <td class="text-right">Ksh {{ number_format($row['total_discount'] ?? 0, 0) }}</td>
                <td class="text-right">Ksh {{ number_format($row['total_sales'], 2) }}</td>
                <td class="text-right">Ksh {{ number_format($row['net_sales'], 2) }}</td>
                <td class="text-right">Ksh {{ number_format($row['tax'] ?? 0, 2) }}</td>
                <td class="text-right">Ksh {{ number_format($row['net_after_tax'] ?? $row['total_sales'], 2) }}</td>
                <td class="text-center">{{ $row['transaction_count'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No data available for the selected period.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th class="text-right">Totals</th>
                <th class="text-right">Ksh {{ number_format($totalCashOnHand, 2) }}</th>
                <th class="text-right">Ksh {{ number_format($totalGrossSales ?? $totalSales, 2) }}</th>
                <th class="text-right">Ksh {{ number_format($totalDiscount ?? 0, 0) }}</th>
                <th class="text-right">Ksh {{ number_format($totalSales, 2) }}</th>
                <th class="text-right">Ksh {{ number_format($totalNetSales, 2) }}</th>
                <th class="text-right">Ksh {{ number_format($totalTax ?? 0, 2) }}</th>
                <th class="text-right">Ksh {{ number_format($totalNetAfterTax ?? $totalSales, 2) }}</th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generated on: {{ date('Y-m-d H:i:s') }} • Period: {{ $start->format('F d, Y') }} to {{ $end->format('F d, Y') }}
    </div>
</body>
</html>









