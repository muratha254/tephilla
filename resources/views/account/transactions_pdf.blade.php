<!DOCTYPE html>
<html>
<head>
    <title>Account Transactions - {{ $account->name }}</title>
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
    </style>
</head>
<body>
    <div class="header">
        <h2 style="text-transform: uppercase; margin: 5px 0;">{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }}</h2>
        @if($setting && $setting->alamat)
        <p style="font-size: 11px; color: #666; margin: 3px 0;">{{ $setting->alamat }}</p>
        @endif
        <h3 style="margin: 10px 0;">Account Transactions Report</h3>
        <p><strong>{{ $account->name }} Account</strong></p>
        <p>Period: {{ date('F d, Y', strtotime($startDate)) }} to {{ date('F d, Y', strtotime($endDate)) }}</p>
    </div>

    <div class="summary">
        <p><strong>Account Name:</strong> {{ $account->name }}</p>
        <p><strong>Current Balance:</strong> Ksh {{ number_format($account->balance, 2) }}</p>
        <p><strong>Total Transactions:</strong> {{ $transactions->count() }}</p>
        <p><strong>Total Amount (Period):</strong> Ksh {{ number_format($totalAmount, 2) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Receipt No</th>
                <th>Total Items</th>
                <th class="text-right">Total Price</th>
                <th>Discount</th>
                <th class="text-right">Amount</th>
                <th>Cashier</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $index => $transaction)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ date('Y-m-d H:i', strtotime($transaction->created_at)) }}</td>
                <td>{{ $transaction->receiptno }}</td>
                <td>{{ $transaction->total_item }}</td>
                <td class="text-right">Ksh {{ number_format($transaction->total_harga, 2) }}</td>
                <td>{{ $transaction->diskon }}%</td>
                <td class="text-right">Ksh {{ number_format($transaction->bayar, 2) }}</td>
                <td>{{ $transaction->user->name ?? 'N/A' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center">No transactions found for the selected period.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6" class="text-right">Total:</th>
                <th class="text-right">Ksh {{ number_format($totalAmount, 2) }}</th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Generated on: {{ date('Y-m-d H:i:s') }}</p>
        <p>Report Period: {{ date('F d, Y', strtotime($startDate)) }} to {{ date('F d, Y', strtotime($endDate)) }}</p>
    </div>
</body>
</html>









