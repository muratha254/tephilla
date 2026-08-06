<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Consignment List</title>
    <style>
        @page { margin: 8mm 10mm 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 14px; margin: 0; padding: 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.supplier-list { margin-top: 4px; margin-bottom: 0; }
        th, td { border: 1px solid #444; text-align: left; }
        th { background: #f0f0f0; padding: 4px 8px; font-size: 13px; }
        h2 { margin: 0; font-size: 16px; }
        h3 { margin: 3px 0; font-size: 14px; }
        .report-header { margin-bottom: 3px; border-bottom: 1px solid #333; padding-bottom: 3px; text-align: center; }
        .summary { margin-top: 2px; font-size: 12px; }
        .summary div { margin-bottom: 1px; }
        tbody td {
            padding: 2px 8px 11px 8px;
            vertical-align: top;
        }
        td.row-num-cell {
            vertical-align: middle;
            font-size: 13px;
            text-align: center;
        }
        td.supplier-name-cell .printed-name {
            display: block;
            font-size: 15px;
            font-style: italic;
            line-height: 1.15;
        }
        td.supplier-name-cell .write-area {
            display: block;
            height: 11pt;
            margin-top: 2pt;
        }
        td.amount-cell {
            text-align: right;
            vertical-align: middle;
            font-size: 16px;
            font-weight: bold;
            white-space: nowrap;
        }
        th:nth-child(1), td:nth-child(1) { width: 6%; }
        th:nth-child(3), td:nth-child(3) { width: 24%; }
        .totals-section { margin-top: 8px; }
        table.totals-table td,
        table.totals-table th { padding: 6px 8px; font-size: 14px; }
    </style>
</head>
<body>
    @php
        $allSuppliers = collect($suppliers)->values();
        $firstPageSize = 15;
        $supplierPages = collect();
        if ($allSuppliers->isNotEmpty()) {
            $supplierPages->push($allSuppliers->take($firstPageSize));
            foreach ($allSuppliers->slice($firstPageSize)->chunk(18) as $chunk) {
                $supplierPages->push($chunk);
            }
        }
        $rowNumber = 0;
        $lastPageIndex = max(0, $supplierPages->count() - 1);
    @endphp

    @forelse($supplierPages as $pageIndex => $pageSuppliers)
        @if($pageIndex === 0)
            <div>
                <div class="report-header">
                    <h2 style="text-transform: uppercase;">{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }}</h2>
                    @if($setting && $setting->alamat)
                    <p style="margin: 1px 0; font-size: 10px; color: #666;">{{ $setting->alamat }}</p>
                    @endif
                    <h3>Consignment List</h3>
                </div>
                <div class="summary">
                    <div><strong>Printing Date:</strong> {{ now()->format('d/m/Y H:i:s') }}</div>
                    @if(!empty($filters['supplier_name']))
                        <div><strong>Supplier Name:</strong> {{ $filters['supplier_name'] }}</div>
                    @endif
                    @if(!empty($filters['start_date']) || !empty($filters['end_date']))
                        <div><strong>Date Range:</strong>
                            @if($filters['start_date'])
                                {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }}
                            @else
                                Beginning
                            @endif
                             -
                            @if($filters['end_date'])
                                {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
                            @else
                                Now
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <table class="supplier-list"@if($pageIndex < $lastPageIndex) style="page-break-after: always;"@endif>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Supplier Name</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pageSuppliers as $supplier)
                    @php $rowNumber++; @endphp
                    <tr>
                        <td class="row-num-cell">{{ $rowNumber }}</td>
                        <td class="supplier-name-cell">
                            <span class="printed-name">{{ $supplier['supplier_name'] ?? 'N/A' }}</span>
                            <div class="write-area">&nbsp;</div>
                        </td>
                        <td class="amount-cell">Ksh {{ number_format(round((float) ($supplier['total_pending'] ?? 0)), 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <div class="report-header">
            <h2 style="text-transform: uppercase;">{{ str_replace('CENTER', 'CENTRE', $setting->nama_perusahaan ?? 'COMPANY NAME') }}</h2>
            <h3>Consignment List</h3>
        </div>
        <table class="supplier-list">
            <tbody>
                <tr>
                    <td colspan="3" style="text-align:center;">No pending consignments found.</td>
                </tr>
            </tbody>
        </table>
    @endforelse

    @if($supplierPages->isNotEmpty())
        <div class="totals-section">
            <h3>Totals</h3>
            <table class="totals-table">
                <tr>
                    <th>Total Amount</th>
                </tr>
                <tr>
                    <td>Ksh {{ number_format(round((float) ($totalPending ?? 0)), 0) }}</td>
                </tr>
            </table>
        </div>
    @endif

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans');
            $size = 9;
            $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
            $w = $fontMetrics->get_text_width('Page 000 of 000', $font, $size);
            $x = max(8, ($pdf->get_width() - $w) / 2);
            $y = $pdf->get_height() - 20;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.35, 0.35, 0.35));
        }
    </script>
</body>
</html>
