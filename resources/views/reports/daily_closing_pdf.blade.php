<!DOCTYPE html>
<html>
<head>
    <title>Daily Closing Report - {{ $date }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .header p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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
        .text-right {
            text-align: right;
        }
        .bg-gray {
            background-color: #f9f9f9;
        }
        .bg-green {
            background-color: #d4edda;
        }
        .bg-red {
            background-color: #f8d7da;
        }
        .bg-primary {
            background-color: #cce5ff;
        }
        .summary-box {
            border: 2px solid #333;
            padding: 15px;
            margin-top: 20px;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 5px 0;
        }
        .company-address {
            font-size: 11px;
            color: #666;
            margin: 3px 0;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 10px;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }}</div>
        @if($setting && $setting->alamat)
        <div class="company-address">{{ $setting->alamat }}</div>
        @endif
        <h1>Daily Closing Report</h1>
        <p>Date: {{ $selectedDate->format('F d, Y') }}</p>
        <p>Generated on: {{ now()->format('F d, Y H:i:s') }}</p>
    </div>

    <div class="section-title">Opening Balance</div>
    <table>
        <tr>
            <td width="30%"><strong>Opening Cash</strong></td>
            <td class="text-right">KES {{ number_format($openingCash, 2) }}</td>
        </tr>
        @if($dailyCash && $dailyCash->openedByUser)
        <tr>
            <td>Opened by</td>
            <td>{{ $dailyCash->openedByUser->name ?? 'N/A' }}</td>
        </tr>
        @endif
    </table>

    <div class="section-title">Sales by Payment Method</div>
    <table>
        <thead>
            <tr>
                <th>Payment Method</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($salesByMethod as $method => $amount)
                @if($amount > 0)
                    <tr>
                        <td><strong>{{ $method }}</strong></td>
                        <td class="text-right">KES {{ number_format($amount, 2) }}</td>
                    </tr>
                @endif
            @endforeach
            <tr class="bg-gray">
                <td><strong>Total Sales</strong></td>
                <td class="text-right"><strong>KES {{ number_format($totalSales, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Expenses</div>
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Purchases</strong></td>
                <td class="text-right">KES {{ number_format($totalPurchases, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Supplier Payments</strong></td>
                <td class="text-right">KES {{ number_format($totalSupplierPayments, 2) }}</td>
            </tr>
            <tr class="bg-gray">
                <td><strong>Total Expenses</strong></td>
                <td class="text-right"><strong>KES {{ number_format($totalPurchases + $totalSupplierPayments, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Supplier Payments by Method</div>
    <table>
        <thead>
            <tr>
                <th>Payment Method</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($paymentsByMethod as $method => $amount)
                @if($amount > 0)
                    <tr>
                        <td><strong>{{ $method }}</strong></td>
                        <td class="text-right">KES {{ number_format($amount, 2) }}</td>
                    </tr>
                @endif
            @endforeach
            <tr class="bg-gray">
                <td><strong>Total Payments</strong></td>
                <td class="text-right"><strong>KES {{ number_format($totalSupplierPayments, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Account Balances</div>
    <table>
        <thead>
            <tr>
                <th>Account</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($accounts as $account)
                <tr>
                    <td><strong>{{ $account->name }}</strong></td>
                    <td class="text-right">KES {{ number_format($account->balance, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box">
        <div class="section-title" style="border: none; margin-top: 0;">Summary</div>
        <table>
            <tbody>
                <tr>
                    <td width="40%"><strong>Opening Cash</strong></td>
                    <td class="text-right">KES {{ number_format($openingCash, 2) }}</td>
                </tr>
                <tr class="bg-green">
                    <td><strong>Total Sales</strong></td>
                    <td class="text-right">KES {{ number_format($totalSales, 2) }}</td>
                </tr>
                <tr class="bg-red">
                    <td><strong>Total Purchases</strong></td>
                    <td class="text-right">KES {{ number_format($totalPurchases, 2) }}</td>
                </tr>
                <tr class="bg-red">
                    <td><strong>Total Supplier Payments</strong></td>
                    <td class="text-right">KES {{ number_format($totalSupplierPayments, 2) }}</td>
                </tr>
                <tr class="bg-gray">
                    <td><strong>Net Cash Position</strong></td>
                    <td class="text-right">KES {{ number_format($netCash, 2) }}</td>
                </tr>
                <tr class="bg-primary">
                    <td><strong>Closing Balance</strong></td>
                    <td class="text-right"><strong>KES {{ number_format($closingBalance, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>









