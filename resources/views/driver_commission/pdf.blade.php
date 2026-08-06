<!DOCTYPE html>
<html>
<head>
    <title>Service Fee Details - {{ $penjualan->receiptno }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
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
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h3 {
            background-color: #f0f0f0;
            padding: 8px;
            margin: 0 0 10px 0;
            border-left: 4px solid #333;
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
        .text-right {
            text-align: right;
        }
        .summary-box {
            background-color: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            margin-top: 20px;
        }
        .summary-box h4 {
            margin-top: 0;
            color: #28a745;
        }
        .highlight {
            background-color: #fff3cd;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
        @if($setting && $setting->alamat)
        <div style="font-size: 11px; color: #666;">{{ $setting->alamat }}</div>
        @endif
        <h3 style="margin-top: 10px;">Service Fee Details</h3>
    </div>

    <div class="info-section">
        <table>
            <tr>
                <th width="25%">Receipt No:</th>
                <td>{{ $penjualan->receiptno }}</td>
                <th width="25%">Date:</th>
                <td>{{ $penjualan->saledate ? date('Y-m-d', strtotime($penjualan->saledate)) : tanggal_indonesia($penjualan->created_at, false) }}</td>
            </tr>
            <tr>
                <th>Cashier:</th>
                <td>{{ $penjualan->user->name ?? 'N/A' }}</td>
                <th>Total Items:</th>
                <td><strong>{{ $penjualan->total_item }} items</strong></td>
            </tr>
        </table>
    </div>

    <div class="info-section">
        <h3>Items Purchased</h3>
        <table>
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th>Product Code</th>
                    <th>Product Name</th>
                    <th class="text-right" width="12%">Quantity</th>
                    <th class="text-right" width="15%">Unit Price</th>
                    <th class="text-right" width="15%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalQty = 0;
                @endphp
                @foreach($details as $index => $detail)
                    @php
                        $totalQty += $detail->jumlah;
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $detail->produk->kode_produk ?? 'N/A' }}</td>
                        <td>{{ $detail->produk->nama_produk ?? 'N/A' }}</td>
                        <td class="text-right">{{ format_uang($detail->jumlah) }}</td>
                        <td class="text-right">Ksh {{ format_uang($detail->harga_jual) }}</td>
                        <td class="text-right">Ksh {{ format_uang($detail->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Total:</th>
                    <th class="text-right">{{ format_uang($totalQty) }} items</th>
                    <th></th>
                    <th class="text-right">Ksh {{ format_uang($penjualan->total_harga) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="summary-box">
        <h4>Service Fee Calculation</h4>
        <table>
            <tr>
                <th width="40%">Subtotal (Before VAT):</th>
                <td class="text-right"><strong>Ksh {{ format_uang($subtotalBeforeVat) }}</strong></td>
            </tr>
            <tr>
                <th>VAT (16%):</th>
                <td class="text-right">Ksh {{ format_uang($penjualan->tax ?? 0) }}</td>
            </tr>
            <tr>
                <th>Total Payable:</th>
                <td class="text-right"><strong>Ksh {{ format_uang($penjualan->bayar) }}</strong></td>
            </tr>
            <tr>
                <th>Commission Rate:</th>
                <td class="text-right"><strong>{{ $commissionRate }}%</strong></td>
            </tr>
            <tr class="highlight">
                <th>Service Fee:</th>
                <td class="text-right" style="font-size: 16px; color: #28a745;"><strong>Ksh {{ format_uang($driverCommission) }}</strong></td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
        <p>Report generated on {{ date('Y-m-d H:i:s') }}</p>
        <p>UTAMA POS System - Service Fee Report</p>
    </div>
</body>
</html>




