<!DOCTYPE html>
<html>
<head>
    <title>Sales List Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 10px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h2 { margin: 5px 0; color: #333; text-transform: uppercase; }
        .header h3 { margin: 10px 0 5px 0; color: #555; }
        .info-section { margin-bottom: 15px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; page-break-inside: auto; }
        table th, table td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        table th { background-color: #f0f0f0; font-weight: bold; font-size: 9px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary-box { background-color: #f9f9f9; padding: 10px; border: 1px solid #ddd; margin-top: 20px; page-break-inside: avoid; }
        .summary-box h4 { margin-top: 0; margin-bottom: 10px; color: #333; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 8px; text-align: center; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
        @if($setting && $setting->alamat)
        <div style="font-size: 9px; color: #666;">{{ $setting->alamat }}</div>
        @endif
        <h3>Sales List Report</h3>
        <div style="font-size: 9px; color: #666;">Filters: {{ $filterLabel }}</div>
        <div style="font-size: 8px; color: #888; margin-top: 5px;">Printed on: {{ date('d/m/Y H:i:s') }}</div>
    </div>

    @if(count($sales) > 0)
    <table>
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="8%">Date</th>
                <th width="10%">Shop Codes</th>
                <th width="10%">Receipt No</th>
                <th width="6%">Qty</th>
                <th width="10%">Total Price</th>
                <th width="6%">Discount</th>
                <th width="10%">Total Pay</th>
                <th width="10%">Payment</th>
                <th width="8%">Receipt Status</th>
                <th width="12%">Cashier</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sales as $index => $sale)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>@if($sale->saledate){{ \Carbon\Carbon::parse($sale->saledate)->format('d/m/Y') }}@elseif($sale->created_at){{ \Carbon\Carbon::parse($sale->created_at)->format('d/m/Y') }}@else N/A @endif</td>
                <td>{{ $sale->shop_codes_text ?? 'N/A' }}</td>
                <td>{{ $sale->receiptno }}</td>
                <td class="text-right">{{ number_format($sale->total_item, 0) }}</td>
                <td class="text-right">Ksh {{ number_format(floatval($sale->total_harga), 2) }}</td>
                <td class="text-right">{{ number_format(floatval($sale->diskon ?? 0), 0) }}%</td>
                <td class="text-right">Ksh {{ number_format(floatval($sale->bayar), 2) }}</td>
                <td class="text-center">{{ $sale->payment_method ?? 'Cash' }}</td>
                <td class="text-center">{{ ucfirst($sale->receipt_status_text ?? 'pending') }}</td>
                <td>{{ optional($sale->user)->name ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f5f5f5; font-weight: bold;">
                <td colspan="4" class="text-right"><strong>TOTAL:</strong></td>
                <td class="text-right"><strong>{{ number_format($totalQuantity, 0) }}</strong></td>
                <td colspan="2" class="text-right"><strong>Total Pay:</strong></td>
                <td class="text-right"><strong>Ksh {{ number_format($totalAmount, 2) }}</strong></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary-box">
        <h4>Summary</h4>
        <table>
            <tr>
                <td width="50%" class="text-right"><strong>Total Number of Sales:</strong></td>
                <td width="50%"><strong>{{ count($sales) }}</strong></td>
            </tr>
            <tr>
                <td class="text-right"><strong>Total Items Sold:</strong></td>
                <td><strong>{{ number_format($totalQuantity, 0) }} items</strong></td>
            </tr>
            <tr>
                <td class="text-right"><strong>Total Discount (approx):</strong></td>
                <td><strong>Ksh {{ number_format($totalDiscount, 2) }}</strong></td>
            </tr>
            <tr style="background-color: #e8e8e8; font-weight: bold;">
                <td class="text-right"><strong>Total Sales Amount:</strong></td>
                <td><strong>Ksh {{ number_format($totalAmount, 2) }}</strong></td>
            </tr>
        </table>
    </div>
    @else
    <div style="text-align: center; padding: 40px; color: #999;">
        <h3>No sales data for the selected filters.</h3>
    </div>
    @endif

    <div class="footer">
        <p>This is a system generated report. No signature required.</p>
    </div>
</body>
</html>
