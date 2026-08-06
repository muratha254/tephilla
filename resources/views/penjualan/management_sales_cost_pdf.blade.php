<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Taken by Management</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 10px; }
        .header { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .header h2 { margin: 4px 0; text-transform: uppercase; }
        .header h3 { margin: 6px 0 3px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d0d0d0; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary { margin-top: 10px; }
        .signature-box { margin-top: 30px; page-break-inside: avoid; }
        .signature-label { font-size: 11px; margin-bottom: 6px; }
        .signature-line { border-bottom: 1px dotted #666; height: 16px; width: 280px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper(optional($setting)->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
        @if(optional($setting)->alamat)
        <div style="font-size: 9px; color: #666;">{{ $setting->alamat }}</div>
        @endif
        <h3>Taken by Management</h3>
        <div>Period: {{ $periodLabel }}</div>
        <div style="font-size: 9px; color: #777;">Printed on: {{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="10%">Date</th>
                <th width="12%">Receipt No</th>
                <th>Product Name(s)</th>
                <th width="8%">Quantity</th>
                <th width="14%">Amount to Pay (Cost Price)</th>
                <th width="12%">Cashier</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="text-center">{{ $row['index'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['receipt'] }}</td>
                    <td>{{ $row['products'] }}</td>
                    <td class="text-right">{{ number_format($row['quantity'], 0) }}</td>
                    <td class="text-right">Ksh {{ number_format($row['cost_total'], 2) }}</td>
                    <td>{{ $row['cashier'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No management sales found for the selected period.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background:#f8f8f8;font-weight:bold;">
                <td colspan="4" class="text-right">TOTAL AMOUNT TO PAY (COST PRICE):</td>
                <td class="text-right">{{ number_format($totalQuantity, 0) }}</td>
                <td class="text-right">Ksh {{ number_format($totalCostAmount, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="signature-box">
        <div class="signature-label"><strong>Management Signature</strong></div>
        <div class="signature-line"></div>
    </div>
</body>
</html>
