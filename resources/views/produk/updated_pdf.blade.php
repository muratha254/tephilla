<!DOCTYPE html>
<html>
<head>
    <title>Updated Items Report</title>
    <style>
        @page {
            margin: 20mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.5;
            padding: 0;
            margin: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-info {
            margin-bottom: 15px;
            font-size: 9pt;
        }
        .summary {
            background-color: #f5f5f5;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            padding: 5px 10px;
        }
        .summary td:first-child {
            font-weight: bold;
            width: 200px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9pt;
        }
        table thead {
            background-color: #000;
            color: #fff;
        }
        table th {
            padding: 8px 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
        }
        table th:first-child {
            width: 40px;
            text-align: center;
        }
        table th:nth-child(5), table th:nth-child(6) {
            text-align: right;
        }
        table tbody td {
            padding: 6px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        table tbody td:first-child {
            text-align: center;
        }
        table tbody td:nth-child(5), table tbody td:nth-child(6) {
            text-align: right;
        }
        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'UTAMADUNI CRAFT CENTRE')) }}</h1>
        @if($setting && $setting->alamat)
        <div style="font-size: 10px; color: #666; margin: 5px 0;">{{ $setting->alamat }}</div>
        @endif
        <h2>Updated Items Report</h2>
        <div style="font-size: 9pt; margin-top: 5px;">Stock Increased Items</div>
    </div>

    <div class="report-info">
        <table>
            <tr>
                <td>Report Date:</td>
                <td>{{ date('d/m/Y H:i:s') }}</td>
            </tr>
            @if($startDate || $endDate)
            <tr>
                <td>Date Range:</td>
                <td>
                    @if($startDate && $endDate)
                        {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                    @elseif($startDate)
                        From: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
                    @elseif($endDate)
                        Until: {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                    @endif
                </td>
            </tr>
            @endif
            @if(!empty($shopFilterName))
            <tr>
                <td>Shop:</td>
                <td>{{ $shopFilterName }}</td>
            </tr>
            @endif
            @if(!empty($supplierFilterName))
            <tr>
                <td>Supplier:</td>
                <td>{{ $supplierFilterName }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td>Total Items Updated:</td>
                <td>{{ $totalItemsUpdated }}</td>
            </tr>
            <tr>
                <td>Total Quantity Added:</td>
                <td>{{ number_format($totalQuantityAdded, 0, '.', ',') }}</td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product Code</th>
                <th>Product Name</th>
                <th>Shop</th>
                <th>Previous Stock</th>
                <th>Quantity Added</th>
                <th>Stock after update</th>
                <th>Date Updated</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ !empty($item->kode_produk) ? $item->kode_produk : ($item->item_code ?? '—') }}</td>
                <td>{{ $item->nama_produk }}</td>
                <td>{{ $item->shop_name ?? '—' }}</td>
                <td>{{ number_format((int) ($item->previous_stock ?? 0), 0, '.', ',') }}</td>
                <td>{{ number_format((int) ($item->quantity_added ?? 0), 0, '.', ',') }}</td>
                <td>{{ number_format((int) ($item->current_stock ?? 0), 0, '.', ',') }}</td>
                <td>{{ \Carbon\Carbon::parse($item->stock_updated_at)->format('d/m/Y') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center; padding: 20px;">No updated items found for the selected period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ date('d/m/Y H:i:s') }}
    </div>
</body>
</html>

