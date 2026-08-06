<!DOCTYPE html>
<html>
<head>
    <title>Detailed Sales Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h2 {
            margin: 5px 0;
            color: #333;
            text-transform: uppercase;
        }
        .header h3 {
            margin: 10px 0 5px 0;
            color: #555;
        }
        .info-section {
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: auto;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        table th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary-box {
            background-color: #f9f9f9;
            padding: 10px;
            border: 1px solid #ddd;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .summary-box h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
        @if($setting && $setting->alamat)
        <div style="font-size: 10px; color: #666;">{{ $setting->alamat }}</div>
        @endif
        <h3>Detailed Sales Report{{ $supplierTypeLabel ?? '' }}</h3>
        <div style="font-size: 10px; color: #666;">Period: {{ $periodLabel }}</div>
        <div style="font-size: 9px; color: #888; margin-top: 5px;">Printed on: {{ date('d/m/Y H:i:s') }}</div>
    </div>

    @php
        // Flatten all items from all sales into a single array
        // Allocate sale-level discount to line items proportionally
        $allItems = [];
        $rowNumber = 1;
        foreach ($reportData as $data) {
            $sale = $data['sale'];
            $details = $data['details'];
            $saleDiscountAmount = $sale->getSaleDiscountAmount();
            $detailSubtotalSum = $details->sum('subtotal');

            foreach ($details as $detail) {
                $detailSubtotal = floatval($detail->subtotal);
                $allocatedSaleDiscount = $detailSubtotalSum > 0 && $saleDiscountAmount > 0
                    ? round($detailSubtotal / $detailSubtotalSum * $saleDiscountAmount, 2)
                    : 0;
                $itemDiscountAmount = $detail->harga_jual * $detail->jumlah * (floatval($detail->diskon ?? 0) / 100);
                $totalDiscountAmount = $itemDiscountAmount + $allocatedSaleDiscount;
                $adjustedSubtotal = $detailSubtotal - $allocatedSaleDiscount;

                $saleDiscountType = $sale->discount_type ?? 'percentage';
                $saleDiskon = (int) round($sale->diskon ?? 0);
                $itemDiskon = (int) round($detail->diskon ?? 0);
                $discountPctDisplay = ($saleDiscountType === 'percentage' && $saleDiskon > 0)
                    ? $saleDiskon . '%'
                    : ($itemDiskon > 0 ? $itemDiskon . '%' : '-');

                $allItems[] = [
                    'row_number' => $rowNumber++,
                    'date' => $sale->saledate ? date('d/m/Y', strtotime($sale->saledate)) : date('d/m/Y', strtotime($sale->created_at)),
                    'receiptno' => $sale->receiptno,
                    'product_code' => $detail->produk->kode_produk ?? 'N/A',
                    'product_name' => $detail->produk->nama_produk ?? 'N/A',
                    'quantity' => $detail->jumlah,
                    'unit_price' => $detail->harga_jual,
                    'discount' => $detail->diskon,
                    'discount_amount' => $totalDiscountAmount,
                    'discount_percentage_display' => $discountPctDisplay,
                    'subtotal' => $adjustedSubtotal,
                ];
            }
        }
    @endphp

    @if(count($allItems) > 0)
    <table>
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="8%">Date</th>
                <th width="10%">Receipt No</th>
                <th width="12%">Product Code</th>
                <th width="25%">Product Name</th>
                <th class="text-right" width="8%">Qty</th>
                <th class="text-right" width="10%">Unit Price</th>
                <th class="text-right" width="8%">Discount(%)</th>
                <th class="text-right" width="10%">Discount</th>
                <th class="text-right" width="14%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($allItems as $item)
                <tr>
                    <td class="text-center">{{ $item['row_number'] }}</td>
                    <td>{{ $item['date'] }}</td>
                    <td>{{ $item['receiptno'] }}</td>
                    <td>{{ $item['product_code'] }}</td>
                    <td>{{ $item['product_name'] }}</td>
                    <td class="text-right">{{ number_format($item['quantity'], 0) }}</td>
                    <td class="text-right">Ksh {{ number_format($item['unit_price'], 2) }}</td>
                    <td class="text-right">{{ $item['discount_percentage_display'] ?? '-' }}</td>
                    <td class="text-right">{{ ($item['discount_amount'] ?? 0) > 0 ? 'Ksh ' . number_format(($item['discount_percentage_display'] ?? '-') !== '-' ? round($item['discount_amount'], 0) : $item['discount_amount'], ($item['discount_percentage_display'] ?? '-') !== '-' ? 0 : 2) : '-' }}</td>
                    <td class="text-right">Ksh {{ number_format($item['subtotal'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @php
                $totalQuantity = collect($allItems)->sum('quantity');
                $totalSubtotal = collect($allItems)->sum('subtotal');
            @endphp
            <tr style="background-color: #f5f5f5; font-weight: bold;">
                <td colspan="5" class="text-right"><strong>TOTAL:</strong></td>
                <td class="text-right"><strong>{{ number_format($totalQuantity, 0) }}</strong></td>
                <td colspan="3" class="text-right"><strong>Total Amount:</strong></td>
                <td class="text-right"><strong>Ksh {{ number_format($totalSubtotal, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>
    @else
        <div style="text-align: center; padding: 40px; color: #999;">
            <h3>No sales data available for the selected period.</h3>
        </div>
    @endif

    @if(count($reportData) > 0)
    <div class="summary-box">
        <h4>Summary</h4>
        <table>
            <tr>
                <td width="50%" class="text-right"><strong>Total Number of Sales:</strong></td>
                <td width="50%"><strong>{{ count($reportData) }}</strong></td>
            </tr>
            <tr>
                <td class="text-right"><strong>Total Items Sold:</strong></td>
                <td><strong>{{ number_format($totalItems, 0) }} items</strong></td>
            </tr>
            <tr>
                <td class="text-right"><strong>Total Amount (before tax):</strong></td>
                <td><strong>Ksh {{ number_format($totalSales - $totalTax, 2) }}</strong></td>
            </tr>
            <tr>
                <td class="text-right"><strong>Total Tax (16% VAT):</strong></td>
                <td><strong>Ksh {{ number_format($totalTax, 2) }}</strong></td>
            </tr>
            <tr style="background-color: #e8e8e8; font-weight: bold;">
                <td class="text-right"><strong>Total Sales Amount:</strong></td>
                <td><strong>Ksh {{ number_format($totalSales, 2) }}</strong></td>
            </tr>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>This is a system generated report. No signature required.</p>
    </div>
</body>
</html>

