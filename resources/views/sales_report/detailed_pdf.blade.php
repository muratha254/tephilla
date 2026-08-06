<!DOCTYPE html>
<html>
<head>
    <title>Sales Detailed Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
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
            padding: 6px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
            font-weight: bold;
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
        .daily-total-row {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        .transaction-row {
            background-color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="text-transform: uppercase; margin: 5px 0;">{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }}</h2>
        @if($setting && $setting->alamat)
        <p style="font-size: 11px; color: #666; margin: 3px 0;">{{ $setting->alamat }}</p>
        @endif
        <h3 style="margin: 10px 0;">Sales Detailed Report</h3>
        <p>Period: {{ $start->format('F d, Y') }} to {{ $end->format('F d, Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Receipt No</th>
                <th>Customer</th>
                <th>Payment Type</th>
                <th class="text-right">Gross Amount</th>
                <th class="text-right">Discount(%)</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Sale Amount</th>
                <th class="text-right">Cash on Hand</th>
                <th class="text-right">Daily Total Sales</th>
                <th class="text-right">Net Sales</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detailedData as $row)
            @if($row['type'] == 'transaction')
            <tr class="transaction-row">
                <td>{{ $row['date_formatted'] }}</td>
                <td>{{ $row['receipt_no'] }}</td>
                <td>{{ $row['customer'] }}</td>
                <td>{{ $row['payment_method'] }}</td>
                <td class="text-right">Ksh {{ number_format($row['gross_amount'] ?? $row['amount'], 2) }}</td>
                <td class="text-right">{{ ($row['discount_percentage'] ?? 0) > 0 ? number_format($row['discount_percentage'], 2) . '%' : '-' }}</td>
                <td class="text-right">{{ ($row['discount'] ?? 0) > 0 ? 'Ksh ' . number_format($row['discount'], (($row['discount_percentage'] ?? 0) > 0 ? 0 : 2)) : '-' }}</td>
                <td class="text-right">Ksh {{ number_format($row['amount'], 2) }}</td>
                <td class="text-right">
                    @if(isset($row['cash_on_hand']) && $row['cash_on_hand'] !== null)
                        Ksh {{ number_format($row['cash_on_hand'], 2) }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
            </tr>
            @else
            <tr class="daily-total-row">
                <td colspan="4" class="text-right"><strong>Daily Totals for {{ date('F d, Y', strtotime($row['date'])) }}:</strong></td>
                <td class="text-right"><strong>Ksh {{ number_format($row['gross_sales'] ?? 0, 2) }}</strong></td>
                <td class="text-right">-</td>
                <td class="text-right"><strong>Ksh {{ number_format($row['total_discount'] ?? 0, 0) }}</strong></td>
                <td class="text-right">-</td>
                <td class="text-right"><strong>Ksh {{ number_format($row['cash_on_hand'], 2) }}</strong></td>
                <td class="text-right"><strong>Ksh {{ number_format($row['total_sales'], 2) }}</strong></td>
                <td class="text-right"><strong>Ksh {{ number_format($row['net_sales'], 2) }}</strong></td>
            </tr>
            @endif
            @empty
            <tr>
                <td colspan="11" class="text-center">No data available for the selected period.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="daily-total-row">
                <th colspan="4" class="text-right">Grand Total:</th>
                <th class="text-right">Ksh {{ number_format($totalGrossSales ?? $totalSales, 2) }}</th>
                <th class="text-right">-</th>
                <th class="text-right">Ksh {{ number_format($totalDiscount ?? 0, 0) }}</th>
                <th class="text-right">Ksh {{ number_format($totalSales, 2) }}</th>
                <th class="text-right">Ksh {{ number_format($totalCashOnHand, 2) }}</th>
                <th class="text-right">Ksh {{ number_format($totalSales, 2) }}</th>
                <th class="text-right">Ksh {{ number_format($totalNetSales, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>
        <p>Report Period: {{ $start->format('F d, Y') }} to {{ $end->format('F d, Y') }}</p>
    </div>
</body>
</html>

