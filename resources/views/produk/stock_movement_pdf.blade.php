<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock movement — {{ $product['name'] ?? 'Product' }}</title>
    <style>
        @page { margin: 12mm; }
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
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #000;
        }
        .header h1 { font-size: 14pt; font-weight: bold; margin-bottom: 4px; }
        .header h2 { font-size: 12pt; margin: 0 0 6px 0; }
        .current-stock-box {
            margin: 12px 0 14px 0;
            padding: 12px 16px;
            border: 2px solid #2d7a3e;
            background-color: #d4edda;
            border-radius: 4px;
            text-align: center;
        }
        .current-stock-box .label {
            font-size: 9pt;
            font-weight: bold;
            color: #155724;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .current-stock-box .value {
            font-size: 22pt;
            font-weight: bold;
            color: #155724;
            line-height: 1.2;
        }
        .meta {
            font-size: 8pt;
            margin-bottom: 10px;
        }
        .meta table { border-collapse: collapse; width: 100%; }
        .meta td { padding: 2px 8px 2px 0; vertical-align: top; }
        .meta td:first-child { font-weight: bold; width: 120px; }
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
            padding: 5px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
        }
        table.data th.num, table.data td.num { text-align: right; }
        table.data td {
            padding: 4px 4px;
            border: 1px solid #ccc;
            vertical-align: top;
        }
        table.data tbody tr:nth-child(even) { background-color: #f7f7f7; }
        table.data tbody tr.row-balance-matches td.balance-cell {
            background-color: #d4edda !important;
            font-weight: bold;
            color: #155724;
        }
        .footer {
            margin-top: 10px;
            font-size: 7pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ str_replace('CENTER', 'CENTRE', strtoupper(optional($setting)->nama_perusahaan ?? 'Company')) }}</h1>
        @if(optional($setting)->alamat)
            <div style="font-size: 8px; color: #666;">{{ optional($setting)->alamat }}</div>
        @endif
        <h2>Stock movement report</h2>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td>Product</td>
                <td>{{ $product['name'] ?? '—' }}</td>
            </tr>
            <tr>
                <td>Code</td>
                <td>{{ $product['code'] ?? '—' }}</td>
            </tr>
            @if(!empty($product['supplier']))
            <tr>
                <td>Supplier</td>
                <td>{{ $product['supplier'] }}</td>
            </tr>
            @endif
            @if(!empty($product['shop']))
            <tr>
                <td>Shop</td>
                <td>{{ $product['shop'] }}</td>
            </tr>
            @endif
            <tr>
                <td>Generated</td>
                <td>{{ $generatedAt ? $generatedAt->format('d/m/Y H:i') : '' }}</td>
            </tr>
        </table>
    </div>

    @php
        $cs = isset($product['current_stock']) ? (int) $product['current_stock'] : null;
    @endphp

    <div class="current-stock-box">
        <div class="label">Current stock</div>
        <div class="value">{{ $cs !== null ? $cs : '—' }}</div>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:10%;">Date</th>
                <th style="width:8%;">Time</th>
                <th style="width:14%;">Type</th>
                <th style="width:30%;">Details</th>
                <th class="num" style="width:10%;">Change</th>
                <th class="num balance-cell" style="width:10%;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($events as $ev)
                @php
                    $bd = isset($ev['balance_display']) ? $ev['balance_display'] : null;
                    $highlightRow = $loop->last && ($bd !== null && $cs !== null && (int) $bd === (int) $cs);
                    $ch = isset($ev['change']) ? $ev['change'] : null;
                    $chStr = ($ch === null) ? '—' : ($ch > 0 ? '+'.$ch : (string) $ch);
                @endphp
                <tr class="{{ ($highlightRow ? 'row-balance-matches' : '') . (($ev['kind'] ?? '') === 'edit' ? ' row-edit' : '') }}">
                    <td>{{ $ev['date_label'] ?? '' }}</td>
                    <td>{{ $ev['time_label'] ?? '' }}</td>
                    <td>{{ $ev['title'] ?? '' }}</td>
                    <td style="font-size:6.5pt;">{{ $ev['detail'] ?? '' }}</td>
                    <td class="num">{{ $chStr }}</td>
                    <td class="num balance-cell">{{ $bd !== null ? $bd : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:#666;">No movements recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top: 12px; margin-bottom: 0; font-size: 11pt; font-weight: bold; color: #155724; text-align: right;">
        Current stock: {{ $cs !== null ? $cs : '—' }}
    </p>

    <div class="footer">
        Stock movement — {{ $product['code'] ?? '' }} — {{ $generatedAt ? $generatedAt->format('Y-m-d H:i:s') : '' }}
    </div>
</body>
</html>
