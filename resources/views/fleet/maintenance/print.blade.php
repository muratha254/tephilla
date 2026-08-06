<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Maintenance Job Card - {{ optional($maintenance->vehicle)->displayName() }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #222; margin: 24px; }
        h1 { font-size: 20px; margin: 0 0 6px; }
        .meta { color: #666; font-size: 13px; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; font-size: 13px; }
        th { background: #f5f5f5; }
        .section { margin-top: 18px; }
        .section h2 { font-size: 15px; margin: 0 0 8px; }
    </style>
</head>
<body>
    <h1>Maintenance Job Card</h1>
    <div class="meta">{{ $companyName }} &bull; Printed {{ now()->format('d M Y H:i') }}</div>

    <table>
        <tr><th>Vehicle</th><td>{{ optional($maintenance->vehicle)->displayName() }} ({{ optional($maintenance->vehicle)->registration_number }})</td></tr>
        <tr><th>Status</th><td>{{ $maintenance->statusDisplayLabel() }}</td></tr>
        <tr><th>Schedule</th><td>{{ $maintenance->formattedDateRange() }}</td></tr>
        <tr><th>Mechanic</th><td>{{ $maintenance->mechanic ?: '-' }}</td></tr>
        <tr><th>Priority</th><td>{{ $maintenance->priority }}</td></tr>
        <tr><th>Vendor</th><td>{{ optional($maintenance->vendor)->company ?: '-' }}</td></tr>
        <tr><th>Total Cost</th><td>{{ format_kes($maintenance->total_cost) }}</td></tr>
    </table>

    <div class="section">
        <h2>Service Details</h2>
        <p>{{ $maintenance->service_details }}</p>
    </div>

    <div class="section">
        <h2>Job Card Checklist</h2>
        <table>
            <thead>
                <tr><th>Done</th><th>Task</th></tr>
            </thead>
            <tbody>
                @forelse ($maintenance->checklist ?? [] as $item)
                <tr>
                    <td>{{ ! empty($item['done']) ? 'Yes' : 'No' }}</td>
                    <td>{{ $item['task'] ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="2">No checklist items.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>window.onload = function () { window.print(); };</script>
</body>
</html>
