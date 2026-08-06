<!DOCTYPE html>
<html>
<head>
    <title>Cash Payment Receipt - {{ $payment->reference_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
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
            font-size: 20px;
        }
        .header h3 {
            margin: 3px 0;
            font-size: 14px;
        }
        .info-section {
            margin-bottom: 15px;
        }
        .info-section h3 {
            margin: 10px 0 5px 0;
            font-size: 16px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
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
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'UTAMADUNI CRAFT CENTRE')) }}</h2>
        <h3>CASH PAYMENT RECEIPT</h3>
    </div>

    <div class="info-section">
        <h3>Supplier: {{ $payment->supplier->nama ?? 'N/A' }}</h3>
        <table>
            <tr>
                <th width="30%">Reference Number</th>
                <td>{{ $payment->reference_number }}</td>
            </tr>
            <tr>
                <th>Payment Date</th>
                <td>{{ $payment->date ? \Carbon\Carbon::parse($payment->date)->format('d/m/Y') : 'N/A' }}</td>
            </tr>
            <tr>
                <th>Payment Method</th>
                <td>{{ $payment->payment_method ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Amount Paid</th>
                <td><strong>Ksh {{ number_format($payment->amount, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="info-section">
        <h3>Purchase Details</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Purchase Date</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php $itemNo = 1; $grandTotal = 0; @endphp
                @foreach($pembelianDetails as $pembelian)
                    @foreach($pembelian->details as $detail)
                        <tr>
                            <td>{{ $itemNo++ }}</td>
                            <td>{{ $pembelian->purchasedate2 ? \Carbon\Carbon::parse($pembelian->purchasedate2)->format('d/m/Y') : 'N/A' }}</td>
                            <td>{{ $detail->produk->nama_produk ?? 'Unknown' }}</td>
                            <td>{{ $detail->jumlah }}</td>
                            <td class="text-right">Ksh {{ number_format($detail->harga_beli, 2) }}</td>
                            <td class="text-right">Ksh {{ number_format($detail->subtotal, 2) }}</td>
                        </tr>
                        @php $grandTotal += $detail->subtotal; @endphp
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total Amount:</strong></td>
                    <td class="text-right"><strong>Ksh {{ number_format($grandTotal, 2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="5" class="text-right"><strong>Amount Paid:</strong></td>
                    <td class="text-right"><strong>Ksh {{ number_format($payment->amount, 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        <p><strong>Generated on:</strong> {{ now()->format('d/m/Y H:i:s') }}</p>
        <p>{{ $setting->alamat ?? '' }}</p>
        <p>Tel: {{ $setting->telepon ?? '' }}</p>
    </div>
</body>
</html>














