<!DOCTYPE html>
<html>
<head>
    <title>Paid Consignment Items Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 5px 0;
        }
        .supplier-info {
            margin-bottom: 15px;
        }
        .supplier-info table {
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background-color: #e7f3ff;
        }
        .footer {
            margin-top: 20px;
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
        @if($setting && $setting->alamat)
        <p style="font-size: 11px; color: #666; margin: 3px 0;">{{ $setting->alamat }}</p>
        @endif
        <h3>Paid Consignment Items Report</h3>
        @if(isset($startDate) || isset($endDate))
        <p style="font-size: 11pt; margin-top: 5px;">
            @if(isset($startDate) && isset($endDate))
                Date Range: {{ date('Y-m-d', strtotime($startDate)) }} to {{ date('Y-m-d', strtotime($endDate)) }}
            @elseif(isset($startDate))
                From Date: {{ date('Y-m-d', strtotime($startDate)) }}
            @elseif(isset($endDate))
                To Date: {{ date('Y-m-d', strtotime($endDate)) }}
            @endif
        </p>
        @endif
    </div>
    
    <div class="supplier-info">
        <table>
            <tr>
                <th width="20%">Supplier Name:</th>
                <td>{{ $supplier->nama }}</td>
                <th width="20%">Telephone:</th>
                <td>{{ $supplier->telepon }}</td>
            </tr>
            <tr>
                <th>Address:</th>
                <td colspan="3">{{ $supplier->alamat }}</td>
            </tr>
            <tr>
                <th>Report Date:</th>
                <td>{{ $exportDate }}</td>
                <th>Total Paid:</th>
                <td class="text-right"><strong>Ksh {{ number_format($totalPaid, 2) }}</strong></td>
            </tr>
        </table>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product Code</th>
                <th>Product Name</th>
                <th>Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total Amount</th>
                <th class="text-right">Amount Paid</th>
                <th class="text-right">Balance</th>
                <th>Status</th>
                <th>Payment Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($paidItems as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->produk->kode_produk ?? 'N/A' }}</td>
                <td>{{ $item->produk->nama_produk ?? 'N/A' }}</td>
                <td>{{ $item->receipt_no ?? 'N/A' }}</td>
                <td>{{ $item->shop_name ?? 'N/A' }}</td>
                <td>{{ $item->quantity ?? 0 }}</td>
                <td class="text-right">Ksh {{ number_format(($item->amount ?? 0) / ($item->quantity ?? 1), 2) }}</td>
                <td class="text-right">Ksh {{ number_format($item->amount ?? 0, 2) }}</td>
                <td class="text-right">Ksh {{ number_format($item->amount_paid ?? 0, 2) }}</td>
                @php
                    // Use calculated balance and status if available, otherwise calculate
                    $balance = isset($item->calculated_balance) ? $item->calculated_balance : (floatval($item->amount ?? 0) - floatval($item->amount_paid ?? 0));
                    $status = isset($item->calculated_status) ? $item->calculated_status : 'Unpaid';
                    if (!isset($item->calculated_status)) {
                        if ($balance <= 0.01) {
                            $status = 'Paid';
                        } elseif (floatval($item->amount_paid ?? 0) > 0) {
                            $status = 'Partially paid';
                        }
                    }
                @endphp
                <td class="text-right">Ksh {{ number_format($balance, 2) }}</td>
                <td>{{ $status }}</td>
                <td>{{ $item->updated_at ? $item->updated_at->format('Y-m-d') : 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="8" class="text-right"><strong>Total Paid:</strong></td>
                <td class="text-right"><strong>Ksh {{ number_format($totalPaid, 2) }}</strong></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
    
    <div class="footer">
        <p>Generated on: {{ $exportDate }}</p>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans');
            $size = 9;
            $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
            $w = $fontMetrics->get_text_width('Page 000 of 000', $font, $size);
            $x = max(8, ($pdf->get_width() - $w) / 2);
            $y = $pdf->get_height() - 20;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.35, 0.35, 0.35));
        }
    </script>
</body>
</html>





















