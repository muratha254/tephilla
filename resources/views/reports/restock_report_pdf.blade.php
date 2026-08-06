<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Restock Report</title>

    <style>
        .text-center { text-align: center; }
        .table {
            width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
            text-align: left;
        }
        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px;">
        <h2 style="margin: 5px 0; text-transform: uppercase;">{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }}</h2>
        @if($setting && $setting->alamat)
        <p style="margin: 5px 0; font-size: 12px; color: #666;">{{ $setting->alamat }}</p>
        @endif
        <h3 style="margin: 10px 0;">Restock Report</h3>
        <p style="margin: 5px 0; font-size: 12px;">Period: {{ $startDate }} - {{ $endDate }}</p>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Product Code</th>
                <th>Product Name</th>
                <th>Supplier</th>
                <th>Quantity Added</th>
                <th>Unit Price</th>
                <th>Total Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($restocks as $restock)
            <tr>
                <td>{{ $restock->date }}</td>
                <td>{{ $restock->product_code }}</td>
                <td>{{ $restock->product_name }}</td>
                <td>{{ $restock->supplier_name }}</td>
                <td>{{ $restock->quantity_added }}</td>
                <td>{{ format_uang($restock->unit_price) }}</td>
                <td>{{ format_uang($restock->total_value) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6"><strong>Total:</strong></td>
                <td><strong>{{ format_uang($totalValue) }}</strong></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
