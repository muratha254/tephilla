<!DOCTYPE html>
<html>
<head>
    <title>Cash Payment History Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 18px;
        }
        .header h3 {
            margin: 3px 0;
            font-size: 14px;
        }
        .filters {
            margin-bottom: 15px;
            font-size: 10px;
        }
        .filters table {
            width: 100%;
            border-collapse: collapse;
        }
        .filters td {
            padding: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            font-size: 10px;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #000;
            font-size: 10px;
        }
        .summary {
            margin-top: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #000;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'UTAMADUNI CRAFT CENTRE')) }}</h2>
        <h3>CASH PAYMENT HISTORY REPORT</h3>
    </div>

    <div class="filters">
        <table>
            <tr>
                <td><strong>Report Period:</strong> {{ $filters['start_date'] }} to {{ $filters['end_date'] }}</td>
                <td><strong>Supplier:</strong> {{ $filters['supplier'] }}</td>
                <td><strong>Payment Method:</strong> {{ $filters['payment_method'] }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Reference</th>
                <th>Supplier</th>
                <th>Phone</th>
                <th class="text-right">Amount</th>
                <th>Payment Date</th>
                <th>Payment Method</th>
                <th class="text-center">Purchases</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $itemNo = 1; 
                $totalAmount = 0; 
            @endphp
            @foreach($payments as $payment)
                @php
                    $pembelianCount = 0;
                    if ($payment->notes) {
                        $notes = json_decode($payment->notes, true);
                        if (isset($notes['pembelian_ids']) && is_array($notes['pembelian_ids'])) {
                            $pembelianCount = count($notes['pembelian_ids']);
                        }
                    }
                    $totalAmount += $payment->amount;
                @endphp
                <tr>
                    <td>{{ $itemNo++ }}</td>
                    <td>{{ $payment->reference_number }}</td>
                    <td>{{ $payment->supplier->nama ?? 'N/A' }}</td>
                    <td>{{ $payment->supplier->telepon ?? 'N/A' }}</td>
                    <td class="text-right">Ksh {{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->date ? \Carbon\Carbon::parse($payment->date)->format('d/m/Y') : 'N/A' }}</td>
                    <td>{{ $payment->payment_method ?? 'N/A' }}</td>
                    <td class="text-center">{{ $pembelianCount }}</td>
                </tr>
            @endforeach
            @if($payments->isEmpty())
                <tr>
                    <td colspan="8" class="text-center">No payments found</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right"><strong>Total:</strong></td>
                <td class="text-right"><strong>Ksh {{ number_format($totalAmount, 2) }}</strong></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary">
        <p><strong>Summary:</strong></p>
        <p>Total Payments: {{ $payments->count() }}</p>
        <p>Total Amount: Ksh {{ number_format($totalAmount, 2) }}</p>
    </div>

    <div class="footer">
        <p><strong>Generated on:</strong> {{ now()->format('d/m/Y H:i:s') }}</p>
        <p>{{ $setting->alamat ?? '' }}</p>
        <p>Tel: {{ $setting->telepon ?? '' }}</p>
    </div>
</body>
</html>














