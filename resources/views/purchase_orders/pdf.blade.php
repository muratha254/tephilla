<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        .no-border td { border: none; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Purchase Order</h2>
        <p>
            PO #: {{ $purchaseOrder->po_number }} |
            Date: {{ optional($purchaseOrder->order_date)->format('d/m/Y') }} |
            Status: {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}
        </p>
    </div>

    <table class="no-border">
        <tr>
            <td>
                <strong>Supplier</strong><br>
                {{ $purchaseOrder->supplier->nama ?? 'N/A' }}<br>
                {{ $purchaseOrder->supplier->alamat ?? '' }}<br>
                {{ $purchaseOrder->supplier->telepon ?? '' }}
            </td>
            <td>
                <strong>Ship To</strong><br>
                {{ $purchaseOrder->product->shop->shop_name ?? 'Main Warehouse' }}<br>
                Item: {{ $purchaseOrder->product->nama_produk }}<br>
                Item Code: {{ $purchaseOrder->product->item_code }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Ordered Qty</th>
                <th>Received Qty</th>
                <th>Remaining</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $purchaseOrder->product->nama_produk }}</td>
                <td>{{ number_format($purchaseOrder->quantity) }}</td>
                <td>{{ number_format($purchaseOrder->received_quantity) }}</td>
                <td>{{ number_format($purchaseOrder->remaining_quantity) }}</td>
                <td>{{ number_format($purchaseOrder->price, 2) }}</td>
                <td>{{ number_format($purchaseOrder->quantity * $purchaseOrder->price, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>







