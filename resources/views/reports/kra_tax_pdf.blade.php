<!DOCTYPE html>
<html>
<head>
    <title>KRA Tax Report - {{ date('Y-m-d', strtotime($startDate)) }} to {{ date('Y-m-d', strtotime($endDate)) }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            width: 60px;
            height: 60px;
        }
        .header h2 {
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
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .summary {
            font-weight: bold;
            background-color: #e0e0e0;
        }
        .period {
            text-align: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(file_exists(public_path('images/utamaduni.png')))
        <img src="{{ public_path('images/utamaduni.png') }}" alt="Logo">
        @endif
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
        @if($setting && $setting->alamat)
        <p style="font-size: 11px; color: #666; margin: 3px 0;">{{ $setting->alamat }}</p>
        @endif
        <h3>KRA Tax Report - VAT Returns</h3>
    </div>

    <div class="period">
        <strong>Period:</strong> {{ date('d/m/Y', strtotime($startDate)) }} - {{ date('d/m/Y', strtotime($endDate)) }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Receipt Number</th>
                <th>Subtotal (Before Tax)</th>
                <th>Tax Amount (16% VAT)</th>
                <th>Total Sale Amount</th>
                <th>Tax Rate</th>
            </tr>
        </thead>
        <tbody>
            @php
                use Illuminate\Support\Facades\Schema;
            @endphp
            @foreach($sales as $index => $sale)
                @php
                    $total = floatval($sale->bayar ?? $sale->total_harga ?? 0);
                    if (Schema::hasColumn('penjualan', 'tax') && $sale->tax) {
                        $tax = floatval($sale->tax);
                    } else {
                        $tax = round($total * (16 / 116), 2);
                    }
                    $subtotal = $total - $tax;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ date('Y-m-d', strtotime($sale->created_at)) }}</td>
                    <td>{{ $sale->receiptno ?? 'N/A' }}</td>
                    <td>Ksh {{ number_format($subtotal, 2) }}</td>
                    <td>Ksh {{ number_format($tax, 2) }}</td>
                    <td>Ksh {{ number_format($total, 2) }}</td>
                    <td>16%</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="summary">
                <td colspan="3" style="text-align: right;"><strong>TOTAL:</strong></td>
                <td><strong>Ksh {{ number_format($totalSubtotal, 2) }}</strong></td>
                <td><strong>Ksh {{ number_format($totalTax, 2) }}</strong></td>
                <td><strong>Ksh {{ number_format($totalSales, 2) }}</strong></td>
                <td><strong>16%</strong></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 30px;">
        <p><strong>Note:</strong> This report shows VAT (Value Added Tax) collected at 16% on all sales during the specified period.</p>
        <p><strong>Generated:</strong> {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>

