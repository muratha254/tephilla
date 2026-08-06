<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Consignments Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #444; padding: 6px; text-align: left; }
        th { background: #f0f0f0; }
        h2 { margin-bottom: 0; }
        .summary { margin-top: 10px; }
        .summary div { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px;">
        <h2 style="margin: 5px 0; text-transform: uppercase;">{{ str_ireplace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }}</h2>
        @if($setting && $setting->alamat)
        <p style="margin: 5px 0; font-size: 11px; color: #666;">{{ $setting->alamat }}</p>
        @endif
        <h3 style="margin: 10px 0;">Consignments Report</h3>
    </div>
    <div class="summary">
        @if($filters['supplier'])
            <div><strong>Supplier:</strong> {{ $filters['supplier'] }}</div>
        @endif
        @if($filters['status'])
            <div><strong>Status:</strong> {{ ucfirst($filters['status']) }}</div>
        @endif
        @if($filters['start_date'] || $filters['end_date'])
            <div><strong>Date Range:</strong> {{ $filters['start_date'] ?? 'Beginning' }} - {{ $filters['end_date'] ?? 'Now' }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Supplier</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Transaction Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($consignments as $index => $item)
                @php
                    $amount = floatval($item->amount ?? 0);
                    $paid = floatval($item->amount_paid ?? 0);
                    $balance = max(0, $amount - $paid);
                    $status = $balance <= 0 ? 'Paid' : ($paid > 0 ? 'Partially Paid' : 'Pending');
                    // Get unit price (buying price) from product
                    $unitPrice = floatval($item->produk->harga_beli ?? 0);
                    // If buying price is not available, calculate from total amount / quantity
                    if ($unitPrice == 0 && ($item->quantity ?? 0) > 0) {
                        $unitPrice = $amount / ($item->quantity ?? 1);
                    }
                    // Format transaction date
                    $transactionDate = $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('d/m/Y') : 'N/A';
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->supplier->nama ?? 'N/A' }}</td>
                    <td>{{ $item->produk->nama_produk ?? 'N/A' }}</td>
                    <td>{{ $item->quantity ?? 0 }}</td>
                    <td>{{ number_format($unitPrice, 2) }}</td>
                    <td>{{ number_format($amount, 2) }}</td>
                    <td>{{ number_format($paid, 2) }}</td>
                    <td>{{ number_format($balance, 2) }}</td>
                    <td>{{ $transactionDate }}</td>
                    <td>{{ $status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center;">No consignments found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h3>Totals</h3>
    <table>
        <tr>
            <th>Total Amount</th>
            <th>Total Paid</th>
            <th>Total Balance</th>
        </tr>
        <tr>
            <td>{{ number_format($totals['total_amount'], 2) }}</td>
            <td>{{ number_format($totals['total_paid'], 2) }}</td>
            <td>{{ number_format($totals['total_balance'], 2) }}</td>
        </tr>
    </table>
</body>
</html>


