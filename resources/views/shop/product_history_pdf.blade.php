<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Product History - {{ $product->nama_produk }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table, th, td { border: 1px solid #000; }
        th, td { padding: 6px; text-align: left; }
        th { background-color: #f0f0f0; }
    </style>
</head>
<body>
    <h3>Product History Report</h3>
    <p>
        <strong>Shop:</strong> {{ $shop->shop_name }}<br>
        <strong>Product:</strong> {{ $product->nama_produk }} ({{ $product->item_code }})<br>
        <strong>Supplier:</strong> {{ optional($product->supplier)->nama ?? 'N/A' }}<br>
        <strong>Date:</strong> {{ now()->format('d/m/Y') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Previous Stock</th>
                <th>Change</th>
                <th>Current Stock</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($history as $entry)
                <tr>
                    <td>{{ $entry->created_at ? \Carbon\Carbon::parse($entry->created_at)->format('d/m/Y') : '-' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $entry->type)) }}</td>
                    <td>{{ $entry->previous_stock }}</td>
                    <td>{{ $entry->restock_amount }}</td>
                    <td>{{ $entry->current_stock }}</td>
                    <td>{{ $entry->notes ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;">No history recorded for this product.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>























