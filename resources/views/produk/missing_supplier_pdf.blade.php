<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Products Without Supplier</title>
    <style>
        @page { margin: 18mm; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        .header h1 { font-size: 15pt; margin: 0 0 6px 0; }
        .meta { font-size: 8.5pt; color: #333; margin-bottom: 12px; }
        .summary {
            background: #f5f5f5;
            border: 1px solid #ccc;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 9pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        thead { background: #222; color: #fff; }
        th, td {
            border: 1px solid #999;
            padding: 6px 8px;
            vertical-align: top;
        }
        th { text-align: left; font-weight: bold; }
        td.num { text-align: right; }
        td.cen { text-align: center; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        .footer {
            margin-top: 16px;
            font-size: 7.5pt;
            color: #666;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'UTAMADUNI CRAFT CENTRE')) }}</h1>
        @if($setting && $setting->alamat)
            <div style="font-size: 8.5pt; color: #555;">{{ $setting->alamat }}</div>
        @endif
    </div>

    <div class="meta">
        <strong>Report:</strong> Products without supplier &nbsp;|&nbsp;
        <strong>Generated:</strong> {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="summary">
        <strong>Total products:</strong> {{ $total }}
        @if(!empty($shopFilterName))
            &nbsp;|&nbsp; <strong>Shop:</strong> {{ $shopFilterName }}
        @endif
        @if(!empty($categoryFilterName))
            &nbsp;|&nbsp; <strong>Category filter:</strong> {{ $categoryFilterName }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:36px;" class="cen">#</th>
                <th>Item code</th>
                <th>Product name</th>
                <th>Shop</th>
                <th style="width:72px;">Cost (KES)</th>
                <th style="width:72px;">Sell (KES)</th>
                <th style="width:50px;">Stock</th>
                <th style="width:50px;">Reorder</th>
                <th>Why listed</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $row)
                @php
                    if ($row->id_supplier === null) {
                        $reason = 'Supplier not set';
                    } elseif ((int) $row->id_supplier === 0) {
                        $reason = 'Supplier id is 0';
                    } else {
                        $reason = 'Supplier #'.(string) $row->id_supplier.' missing';
                    }
                @endphp
                <tr>
                    <td class="cen">{{ $i + 1 }}</td>
                    <td>{{ $row->item_code ?? '—' }}</td>
                    <td>{{ $row->nama_produk ?? '' }}</td>
                    <td>{{ $row->shop_name ?? '—' }}</td>
                    <td class="num">{{ number_format((float) ($row->harga_beli ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($row->harga_jual ?? 0), 2) }}</td>
                    <td class="num">{{ $row->stok ?? 0 }}</td>
                    <td class="num">{{ $row->reorder ?? '—' }}</td>
                    <td>{{ $reason }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;">No products match the current filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Products without a valid supplier assignment — assign suppliers on the No Supplier screen or via Enter Stock.
    </div>
</body>
</html>
