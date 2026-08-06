<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock List</title>
    <style>
        @page { margin: 14mm; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            color: #000;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .header h2 {
            font-size: 12pt;
            margin: 0 0 6px 0;
        }
        .meta {
            font-size: 8pt;
            margin-bottom: 12px;
        }
        .meta table { border-collapse: collapse; width: 100%; }
        .meta td { padding: 3px 8px 3px 0; vertical-align: top; }
        .meta td:first-child { font-weight: bold; width: 140px; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        table.data thead {
            background-color: #222;
            color: #fff;
        }
        table.data th {
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
        }
        table.data th.num, table.data td.num { text-align: right; }
        table.data th.center, table.data td.center { text-align: center; }
        table.data td {
            padding: 4px 4px;
            border: 1px solid #ccc;
            vertical-align: top;
        }
        table.data tbody tr:nth-child(even) { background-color: #f7f7f7; }
        .footer {
            margin-top: 12px;
            font-size: 7pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ str_replace('CENTER', 'CENTRE', strtoupper(optional($setting)->nama_perusahaan ?? 'UTAMADUNI CRAFT CENTRE')) }}</h1>
        @if(optional($setting)->alamat)
            <div style="font-size: 9px; color: #666;">{{ optional($setting)->alamat }}</div>
        @endif
        <h2>Stock List</h2>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td>Generated</td>
                <td>{{ $generatedAt->format('d/m/Y H:i:s') }}</td>
            </tr>
            <tr>
                <td>Rows</td>
                <td>{{ $rowCount }}</td>
            </tr>
            @if($shopFilterName)
            <tr>
                <td>Shop filter</td>
                <td>{{ $shopFilterName }}</td>
            </tr>
            @endif
            @if($searchTerm)
            <tr>
                <td>Search</td>
                <td>{{ $searchTerm }}</td>
            </tr>
            @endif
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th class="center" style="width:28px;">#</th>
                <th style="width:70px;">Code</th>
                <th>Product Name</th>
                <th style="width:70px;">Shop</th>
                <th style="width:70px;">Supplier</th>
                <th class="num" style="width:58px;">Buy</th>
                <th class="num" style="width:58px;">Sell</th>
                <th class="num" style="width:40px;">Re-Ord</th>
                <th class="num" style="width:44px;">In</th>
                <th class="num" style="width:44px;">Sold</th>
                <th class="num" style="width:44px;">Rem</th>
                <th class="center" style="width:54px;">Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $i => $row)
                @php
                    $code = !empty($row->kode_produk) ? $row->kode_produk : ($row->item_code ?? '-');
                    $reorder = (float) ($row->reorder_level ?? $row->reorder ?? 0);
                    $itemIn = (int) round((float) ($row->item_in ?? 0));
                    $sold = (int) ($row->items_sold ?? 0);
                    $rem = (int) ($row->stok ?? 0);
                    $d = $row->date_in ?? $row->created_at;
                    $dateStr = '-';
                    if ($d) {
                        try {
                            $dateStr = \Carbon\Carbon::parse($d)->format('d/m/Y');
                        } catch (\Throwable $e) {
                            $dateStr = '-';
                        }
                    }
                @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $code }}</td>
                    <td>{{ $row->nama_produk ?? '-' }}</td>
                    <td>{{ $row->shop_name ?? '-' }}</td>
                    <td>{{ $row->supplier_name ?? '-' }}</td>
                    <td class="num">{{ format_uang($row->harga_beli ?? 0) }}</td>
                    <td class="num">{{ format_uang($row->harga_jual ?? 0) }}</td>
                    <td class="num">{{ format_uang($reorder) }}</td>
                    <td class="num">{{ format_uang($itemIn) }}</td>
                    <td class="num">{{ format_uang($sold) }}</td>
                    <td class="num">{{ format_uang($rem) }}</td>
                    <td class="center">{{ $dateStr }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Stock List — {{ $rowCount }} product(s)
    </div>
</body>
</html>
