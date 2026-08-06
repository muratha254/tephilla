<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Payment Report</title>
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
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .brand img {
            height: 40px;
        }
        .period {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
        }
        .footer {
            margin-top: 10px;
            font-size: 11px;
            text-align: right;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            @if(file_exists(public_path('images/utamaduni.png')))
            <img src="{{ public_path('images/utamaduni.png') }}" alt="Logo">
            @endif
            <div>
                <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
            </div>
        </div>
        @if($setting && $setting->alamat)
        <div style="font-size: 11px; color: #666; margin: 3px 0;">{{ $setting->alamat }}</div>
        @endif
        <div>Supplier Payment Report</div>
    </div>

    <div class="period">
        <strong>Period:</strong> {{ date('d/m/Y', strtotime($startDate)) }} - {{ date('d/m/Y', strtotime($endDate)) }}
        @if($supplier)
            <br>
            <strong>Supplier:</strong> {{ $supplier->nama }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference #</th>
                <th>Supplier</th>
                <th class="text-right">Amount</th>
                <th>Payment Method</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>{{ date('Y-m-d', strtotime($payment->date ?? $payment->created_at)) }}</td>
                <td>{{ $payment->reference_number }}</td>
                <td>{{ $payment->supplier->nama ?? '' }}</td>
                <td class="text-right">{{ number_format($payment->amount, 2) }}</td>
                <td>{{ $payment->payment_method ?? '' }}</td>
                <td>{{ $payment->notes ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" class="text-right">Total:</td>
                <td class="text-right">{{ number_format($payments->sum('amount'), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Exported on {{ date('Y-m-d H:i:s') }}
    </div>
</body>
</html>
