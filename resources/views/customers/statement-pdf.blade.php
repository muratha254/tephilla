<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Statement</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
        h2 { margin: 0 0 8px; }
        .muted { color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #A2502B; color: #fff; text-align: left; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h2>{{ $profile['companyName'] ?? fleet_system_name() }}</h2>
    <div class="muted">Customer Statement &mdash; {{ now()->format('d-m-Y') }}</div>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th>Phone</th>
                <th>Category</th>
                <th class="num">Credit Limit</th>
                <th class="num">Credit Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->phone }}</td>
                    <td>{{ optional($customer->category)->name }}</td>
                    <td class="num">{{ number_format((float) $customer->credit_limit, 2) }}</td>
                    <td class="num">{{ number_format($customer->creditAmount(), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No customers selected.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
