<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
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
    <h2>{{ $title }}</h2>
    <div class="meta">Period: {{ $from }} to {{ $to }}</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ !empty($monthly) ? 'Month' : 'Date' }}</th>
                <th class="text-right">Invoices</th>
                <th class="text-right">Taxable Amount</th>
                <th class="text-right">VAT</th>
                <th class="text-right">Zero Rated</th>
                <th class="text-right">Gross</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row[ !empty($monthly) ? 'month' : 'date' ] }}</td>
                    <td class="text-right">{{ number_format($row['invoices']) }}</td>
                    <td class="text-right">{{ number_format($row['taxable'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['vat'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['zero_rated'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No records found.</td></tr>
            @endforelse
            @if($rows->isNotEmpty())
                <tr>
                    <th colspan="2" class="text-right">Totals</th>
                    <th class="text-right">{{ number_format($rows->sum('invoices')) }}</th>
                    <th class="text-right">{{ number_format($rows->sum('taxable'), 2) }}</th>
                    <th class="text-right">{{ number_format($rows->sum('vat'), 2) }}</th>
                    <th class="text-right">{{ number_format($rows->sum('zero_rated'), 2) }}</th>
                    <th class="text-right">{{ number_format($rows->sum('gross'), 2) }}</th>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
