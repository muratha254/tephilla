<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fuel Vendors</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #222; margin: 24px; }
        h1 { font-size: 20px; margin: 0 0 6px; }
        p { margin: 0 0 18px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body onload="window.print()">
    <h1>Fuel Vendors</h1>
    <p>{{ $companyName }}</p>
    <table>
        <thead>
            <tr>
                <th>S.No</th>
                <th>Name</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vendors as $index => $vendor)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $vendor->name }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="2">No fuel vendors found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
