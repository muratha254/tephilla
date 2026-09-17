<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $systemName ?? 'Sellix POS' }} | {{ $receipt->number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; background: #fff; }
        h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; text-align: left; }
        th { background: #605ca8; color: #fff; }
        .no-print { margin-bottom: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </p>
    <h2>{{ $companyName ?? 'Sellix POS' }}</h2>
    <h3>Goods Received Note</h3>
    <div class="muted">{{ $receipt->number }} | {{ optional($receipt->received_at)->format('d-m-Y') }}</div>
    <p>
        Purchase: {{ $purchase->number }}<br>
        Supplier: {{ optional($purchase->supplier)->name ?: optional($receipt->supplier)->name }}<br>
        Received by: {{ optional($receipt->user)->name ?: '-' }}
    </p>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Cost</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach($receipt->items as $item)
                @php
                    $value = (float) $item->quantity_received * (float) $item->unit_cost;
                    $total += $value;
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ optional($item->product)->name ?: '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity_received, 4), '0'), '.') }}</td>
                    <td>Ksh {{ number_format((float) $item->unit_cost, 2) }}</td>
                    <td>Ksh {{ number_format($value, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4">Total</th>
                <th>Ksh {{ number_format($total, 2) }}</th>
            </tr>
        </tfoot>
    </table>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
