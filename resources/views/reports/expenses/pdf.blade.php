<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Expense Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h2 { margin: 0 0 6px; }
        .meta { margin-bottom: 14px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f3f3f3; text-align: left; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h2>Expense Report</h2>
    <div class="meta">Period: {{ $from }} to {{ $to }}</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Branch</th>
                <th>Expense Code</th>
                <th>Expense Date</th>
                <th>Expense for</th>
                <th class="text-right">Amount</th>
                <th>Note</th>
                <th>Created by</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['index'] }}</td>
                    <td>{{ $row['branch'] }}</td>
                    <td>{{ $row['code'] }}</td>
                    <td>{{ $row['expense_date'] }}</td>
                    <td>{{ $row['expense_for'] }}</td>
                    <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
                    <td>{{ $row['note'] }}</td>
                    <td>{{ $row['created_by'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No expense records found.</td></tr>
            @endforelse
            @if($rows->isNotEmpty())
                <tr>
                    <th colspan="5" class="text-right">Total</th>
                    <th class="text-right">{{ number_format($rows->sum('amount'), 2) }}</th>
                    <th colspan="2"></th>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
