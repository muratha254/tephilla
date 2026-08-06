<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Small Note</title>

    <?php
    $style = '
    <style>
        * {
            font-family: "consolas", sans-serif;
        }
        p {
            display: block;
            margin: 3px;
            font-size: 10pt;
        }
        table td {
            font-size: 9pt;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
        }
        table.items thead {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        table.items th {
            padding: 4px 2px;
            font-weight: bold;
            font-size: 9pt;
        }
        table.items td {
            padding: 3px 2px;
            border-bottom: 1px dashed #ddd;
        }
        table.items .item-name {
            text-align: left;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        table.items th.text-right,
        table.items td.text-right {
            padding-left: 2px;
        }
        table.items .rate-column {
            padding-right: 2px;
        }

        table.items .item-name {
            word-wrap: break-word;
            word-break: break-word;
            max-width: 50%;
        }
        body {
            width: 58mm;
            margin: 0 auto;
            padding: 2mm;
        }
        .receipt-copy {
            page-break-after: auto;
        }
        .receipt-separator {
            margin: 15px 0;
            padding: 10px 0;
            text-align: center;
            border-top: 2px dashed #000;
            border-bottom: 2px dashed #000;
        }
        .receipt-separator-text {
            font-size: 8pt;
            margin: 5px 0;
        }

        @media print {
            @page {
                margin: 0;
                size: 58mm 
    ';
    ?>
    <?php 
    $style .= 
        ! empty($_COOKIE['innerHeight'])
            ? $_COOKIE['innerHeight'] .'mm auto; }'
            : 'auto; }';
    ?>
    <?php
    $style .= '
            html, body {
                width: 58mm;
                margin: 0;
                padding: 2mm;
            }
            .btn-print,
            .no-print {
                display: none !important;
            }
        }
    </style>
    ';
    ?>

    {!! $style !!}
</head>
<body>
    <button class="btn-print" style="position: absolute; right: 1rem; top: rem;" onclick="window.print()">Print</button>
    
    @php
        $receiptTotals = $penjualan->getReceiptBreakdown();
        $subTotal = $receiptTotals['subtotal'];
        $vatAmount = $receiptTotals['vat'];
        $discountAmount = $receiptTotals['discount_amount'];
        $discountLabel = $receiptTotals['discount_label'];
    @endphp

    <div class="receipt-copy">
        @if(strtolower((string) request()->query('copy', '')) === 'company')
            <p class="text-center" style="margin: 0 0 8px; font-size: 10pt; font-weight: bold; letter-spacing: 0.05em;">COMPANY COPY</p>
        @endif
        <div class="text-center">
            <h3 style="margin-bottom: 5px;">{{ str_replace('CENTER', 'CENTRE', strtoupper(optional($setting)->nama_perusahaan ?? 'STORE')) }}</h3>
            <p>{{ strtoupper(optional($setting)->alamat ?? '') }}</p>
            <p>Tel: 0722205028</p>
            <p>E-mail: info@utamaduni.co.ke</p>
            <p>PIN NO: P000605273Y</p>
        </div>
        <br>
        <div>
            <p style="float: left;">{{ $penjualan->getReceiptDateFormatted() }}</p>
        </div>
        <div class="clear-both" style="clear: both;"></div>
        <p>Receipt No: {{ $penjualan->receiptno }}</p>
        <p>Transaction No: {{ tambah_nol_didepan($penjualan->id_penjualan, 10) }}</p>
        <p class="text-center">============================</p>
        
        <br>
        <table class="items">
            <thead>
                <tr>
                    <th class="item-name" style="width: 45%;">Item(s)</th>
                    <th class="text-center" style="width: 12%;">QTY</th>
                    <th class="text-right rate-column" style="width: 20%;">COST</th>
                    <th class="text-right" style="width: 23%;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail as $item)
                    <tr>
                        <td class="item-name" style="word-wrap: break-word; word-break: break-word;">@if($item->produk){{ $item->produk->nama_produk }}@else[Missing product #{{ (int) ($item->id_produk ?? 0) }}]@endif</td>
                        <td class="text-center">{{ $item->jumlah }}</td>
                        <td class="text-right rate-column">{{ format_uang($item->harga_jual) }}</td>
                        <td class="text-right">{{ format_uang($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="text-center">----------------------------</p>

        <table width="100%" style="border: 0;">
            <tr>
                <td>SUB TOTAL:</td>
                <td class="text-right">{{ format_uang($subTotal) }}</td>
            </tr>
            @if($discountAmount > 0)
            <tr>
                <td>{{ $discountLabel }}:</td>
                <td class="text-right">-{{ format_uang($discountAmount) }}</td>
            </tr>
            @endif
            <tr>
                <td>V.A.T(16%):</td>
                <td class="text-right">{{ format_uang($vatAmount) }}</td>
            </tr>
            <tr>
                <td>TOTAL:</td>
                <td class="text-right">{{ format_uang($receiptTotals['total']) }}</td>
            </tr>
        </table>

        <p class="text-center">============================</p>
        <p class="text-center">Served by {{ ucwords(strtolower(optional($penjualan->user)->name ?? auth()->user()->name ?? 'N/A')) }}</p>
        <p class="text-center">-- THANK YOU --</p>
    </div>

    @php
        $posPrintChain = (string) request()->query('pos_print_chain', '');
        $posNewSaleUrl = route('transaksi.baru');
        $posCompanyChainUrl = route('transaksi.nota_kecil', $penjualan->id_penjualan) . '?copy=company&pos_print_chain=2';
    @endphp
    @if($posPrintChain === '1')
        <p class="text-center no-print" style="margin-top:12px;font-size:9pt;">
            <a href="{{ $posCompanyChainUrl }}">Company copy did not load automatically? Open it here</a>
        </p>
    @endif
    <script>
        (function () {
            var body = document.body;
            var html = document.documentElement;
            var height = Math.max(
                body.scrollHeight, body.offsetHeight,
                html.clientHeight, html.scrollHeight, html.offsetHeight
            );
            document.cookie = 'innerHeight=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            document.cookie = 'innerHeight=' + ((height + 50) * 0.264583);
            function triggerPrint() {
                try { window.print(); } catch (e) {}
            }
            requestAnimationFrame(function () {
                requestAnimationFrame(triggerPrint);
            });

            // POS: one popup only (browsers block a second window.open). After customer print, load company copy here.
            var chain = @json($posPrintChain);
            var companyUrl = @json($posCompanyChainUrl);
            var newSaleUrl = @json($posNewSaleUrl);

            if (chain === '1') {
                var went = false;
                function goCompany() {
                    if (went) return;
                    went = true;
                    window.location.replace(companyUrl);
                }
                window.addEventListener('afterprint', goCompany);
            }
            if (chain === '2') {
                function finishPosChain() {
                    try {
                        if (window.opener && !window.opener.closed) {
                            window.opener.location.href = newSaleUrl;
                        }
                    } catch (e) {}
                    try { window.close(); } catch (e2) {}
                }
                window.addEventListener('afterprint', finishPosChain);
            }
        })();
    </script>
</body>
</html>
