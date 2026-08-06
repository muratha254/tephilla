<!DOCTYPE html>
<html>
<head>
    <title>Sales Generated Cash Payments Report</title>
    <style>
        @page {
            margin: 20mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.5;
            padding: 0;
            margin: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header h2 {
            font-size: 11pt;
            font-weight: normal;
        }
        .report-info {
            margin-bottom: 15px;
            font-size: 9pt;
        }
        .report-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-info td {
            padding: 5px 10px;
        }
        .report-info td:first-child {
            font-weight: bold;
            width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9pt;
        }
        table thead {
            background-color: #000;
            color: #fff;
        }
        table th {
            padding: 8px 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
        }
        table th:first-child {
            width: 40px;
            text-align: center;
        }
        table th:nth-child(6) {
            text-align: center;
        }
        table th:nth-child(7) {
            text-align: right;
        }
        table th:last-child {
            text-align: right;
        }
        table tbody td {
            padding: 6px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        table tbody td:first-child {
            text-align: center;
        }
        table tbody td:nth-child(6) {
            text-align: center;
        }
        table tbody td:nth-child(7) {
            text-align: right;
        }
        table tbody td:last-child {
            text-align: right;
            font-weight: bold;
        }
        table tfoot {
            background-color: #f0f0f0;
            border-top: 2px solid #000;
        }
        table tfoot td {
            padding: 8px 10px;
            font-weight: bold;
            font-size: 10pt;
            border: 1px solid #000;
        }
        table tfoot td:first-child {
            text-align: right;
        }
        table tfoot td:last-child {
            text-align: right;
            font-size: 11pt;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ str_ireplace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'UTAMADUNI CRAFT CENTRE')) }}</h1>
        <h2>Sales Generated Cash Payments Report</h2>
    </div>

    <div class="report-info">
        <table>
            <tr>
                <td>Period:</td>
                <td>{{ $filters['start_date'] }} to {{ $filters['end_date'] }}</td>
                <td>Supplier:</td>
                <td>{{ $filters['supplier'] }}</td>
            </tr>
            <tr>
                <td>Generated:</td>
                <td>{{ now()->format('d/m/Y H:i') }}</td>
                <td>Total Records:</td>
                <td>{{ $pembelians->count() }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Supplier</th>
                <th>Date</th>
                <th>Item Name</th>
                <th>Shop</th>
                <th>Quantity</th>
                <th>Selling Price</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $itemNo = 1; 
            @endphp
            @foreach($pembelians as $pembelian)
                @php
                    $itemNames = [];
                    $quantities = [];
                    $sellingPrices = [];
                    $calculatedAmounts = [];
                    foreach ($pembelian->details as $detail) {
                        if ($detail->produk) {
                            $itemNames[] = $detail->produk->nama_produk;
                            $quantities[] = $detail->jumlah;
                            $sellingPrice = $detail->produk->harga_jual ?? 0;
                            $sellingPrices[] = number_format($sellingPrice, 2);
                            // Calculate: quantity × selling price
                            $calculatedAmounts[] = number_format($detail->jumlah * $sellingPrice, 2);
                        }
                    }
                    $itemNamesStr = !empty($itemNames) ? implode(', ', $itemNames) : 'N/A';
                    $quantitiesStr = !empty($quantities) ? implode(', ', $quantities) : 'N/A';
                    $sellingPricesStr = !empty($sellingPrices) ? 'Ksh ' . implode(', Ksh ', $sellingPrices) : 'N/A';
                    $calculatedAmountsStr = !empty($calculatedAmounts) ? 'Ksh ' . implode(', Ksh ', $calculatedAmounts) : 'N/A';

                    $shops = [];
                    foreach ($pembelian->details as $detail) {
                        if ($detail->produk && $detail->produk->shop) {
                            $shopName = $detail->produk->shop->shop_name;
                            if (!in_array($shopName, $shops)) {
                                $shops[] = $shopName;
                            }
                        }
                    }
                    $shopNames = !empty($shops) ? implode(', ', $shops) : 'N/A';
                @endphp
                <tr>
                    <td>{{ $itemNo++ }}</td>
                    <td>{{ $pembelian->supplier->nama ?? 'N/A' }}</td>
                    <td>{{ $pembelian->purchasedate2 ? \Carbon\Carbon::parse($pembelian->purchasedate2)->format('d/m/Y') : 'N/A' }}</td>
                    <td>{{ $itemNamesStr }}</td>
                    <td>{{ $shopNames }}</td>
                    <td>{{ $quantitiesStr }}</td>
                    <td>{{ $sellingPricesStr }}</td>
                    <td>{{ $calculatedAmountsStr }}</td>
                </tr>
            @endforeach
            @if($pembelians->isEmpty())
                <tr>
                    <td colspan="8" class="no-data">No purchases found for the selected criteria</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td colspan="7" style="text-align: right;"><strong>TOTAL:</strong></td>
                <td>Ksh {{ number_format($totalAmount, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
