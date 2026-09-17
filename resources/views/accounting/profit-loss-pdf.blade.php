<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Profit &amp; Loss Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
        h2 { margin: 0 0 4px; }
        .muted { color: #666; font-size: 11px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #c9a027; color: #111; text-align: left; }
        .num { text-align: right; }
        .section { background: #f4f4f4; font-weight: 700; }
        .total th { background: #eee; }
    </style>
</head>
<body>
    <h2>{{ $companyName ?? fleet_system_name() }}</h2>
    <div class="muted">Profit &amp; Loss Report &mdash; {{ \Carbon\Carbon::parse($from)->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($to)->format('d-m-Y') }}</div>
    <table>
        <thead>
            <tr>
                <th>Particulars</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section"><td colspan="2">Income</td></tr>
            @forelse($report['income'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No income in this period.</td></tr>
            @endforelse
            <tr class="total">
                <th>Total Income</th>
                <th class="num">{{ number_format($report['income_total'], 2) }}</th>
            </tr>
            <tr class="section"><td colspan="2">Expenses</td></tr>
            @forelse($report['expenses'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No expenses in this period.</td></tr>
            @endforelse
            <tr class="total">
                <th>Total Expenses</th>
                <th class="num">{{ number_format($report['expense_total'], 2) }}</th>
            </tr>
            <tr class="total">
                <th>{{ $report['net'] >= 0 ? 'Net Profit' : 'Net Loss' }}</th>
                <th class="num">{{ number_format($report['net'], 2) }}</th>
            </tr>
        </tbody>
    </table>
</body>
</html>
