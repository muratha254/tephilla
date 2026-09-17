<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Statement</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #222; }
        h2 { margin: 0 0 6px; }
        .meta { margin-bottom: 12px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f3f3f3; text-align: left; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h2>{{ $profile['companyName'] ?? fleet_system_name() }}</h2>
    <div class="meta">
        Customer Statement ({{ $reportType === 'summary' ? 'Summary' : 'Detailed' }}) — {{ $from }} to {{ $to }}
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Reference</th>
                <th>Description</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['index'] }}</td>
                    <td>{{ $row['customer'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="text-right">{{ number_format($row['debit'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['credit'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No statement records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
