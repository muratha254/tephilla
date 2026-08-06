<!DOCTYPE html>
<html>
<head>
    <title>{{ $reportType == 'received_orders' ? 'Received Orders' : 'Purchase Orders' }} Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            padding: 0;
        }
        .info {
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
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
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $reportType == 'received_orders' ? 'Received Orders' : 'Purchase Orders' }} Report</h2>
    </div>

    <div class="info">
        <p><strong>Date Range:</strong> {{ date('Y-m-d', strtotime($startDate)) }} to {{ date('Y-m-d', strtotime($endDate)) }}</p>
        @if($supplier)
            <p><strong>Supplier:</strong> {{ $supplier->nama }}</p>
        @else
            <p><strong>Supplier:</strong> All Suppliers</p>
        @endif
        <p><strong>Generated:</strong> {{ date('Y-m-d H:i:s') }}</p>
    </div>

    @if($reportType == 'received_orders')
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference #</th>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Items</th>
                    <th>Total Quantity</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivedOrders as $receipt)
                <tr>
                    <td>{{ $receipt->received_date ? date('Y-m-d', strtotime($receipt->received_date)) : '-' }}</td>
                    <td>{{ $receipt->reference_number }}</td>
                    <td>{{ $receipt->batch->po_number ?? '-' }}</td>
                    <td>{{ $receipt->batch->supplier->nama ?? 'N/A' }}</td>
                    <td>
                        @foreach($receipt->items as $item)
                            {{ $item->product->nama_produk ?? 'N/A' }} ({{ $item->quantity }})
                            @if(!$loop->last), @endif
                        @endforeach
                    </td>
                    <td class="text-right">{{ $receipt->items->sum('quantity') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center">No received orders found.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total:</strong></td>
                    <td class="text-right"><strong>{{ $receivedOrders->sum(function($r) { return $r->items->sum('quantity'); }) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    @else
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>PO Number</th>
                    <th>Reference #</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Total Quantity</th>
                    <th>Received</th>
                    <th>Remaining</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseOrders as $order)
                <tr>
                    <td>{{ $order->order_date ? date('Y-m-d', strtotime($order->order_date)) : '-' }}</td>
                    <td>{{ $order->po_number }}</td>
                    <td>{{ $order->reference_number ?? '-' }}</td>
                    <td>
                        @php
                            $uniqueSuppliers = $order->items->map(function($item) use ($order) {
                                if ($item->supplier_id && $item->supplier) {
                                    return $item->supplier->nama;
                                } elseif ($item->product && $item->product->supplier) {
                                    return $item->product->supplier->nama;
                                }
                                return $order->supplier->nama ?? 'N/A';
                            })->unique()->filter();
                        @endphp
                        @if($uniqueSuppliers->count() > 1)
                            Multiple Suppliers
                        @else
                            {{ $uniqueSuppliers->first() ?? $order->supplier->nama ?? 'N/A' }}
                        @endif
                    </td>
                    <td>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                    <td class="text-right">{{ $order->items->sum('quantity') }}</td>
                    <td class="text-right">{{ $order->items->sum('received_quantity') }}</td>
                    <td class="text-right">{{ $order->items->sum('quantity') - $order->items->sum('received_quantity') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center">No purchase orders found.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total:</strong></td>
                    <td class="text-right"><strong>{{ $purchaseOrders->sum(function($o) { return $o->items->sum('quantity'); }) }}</strong></td>
                    <td class="text-right"><strong>{{ $purchaseOrders->sum(function($o) { return $o->items->sum('received_quantity'); }) }}</strong></td>
                    <td class="text-right"><strong>{{ $purchaseOrders->sum(function($o) { return $o->items->sum('quantity') - $o->items->sum('received_quantity'); }) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="footer">
        <p>Generated on {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>









