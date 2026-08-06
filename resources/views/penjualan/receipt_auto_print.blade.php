<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Receipt - {{ $penjualan->receiptno }}</title>
    
    <style>
        /* ============================================
           THERMAL PRINTER OPTIMIZED STYLES
           Optimized for 58mm and 80mm thermal printers
           ============================================ */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            font-family: "Courier New", "Consolas", monospace;
            font-size: 12px;
            line-height: 1.2;
            width: 100%;
            background: white;
            color: black;
        }
        
        /* Screen view - hidden by default */
        .screen-only {
            display: none;
        }
        
        /* Receipt container */
        .receipt {
            width: 100%;
            max-width: 80mm; /* 80mm printer width */
            margin: 0 auto;
            padding: 5mm;
        }
        
        /* For 58mm printers, adjust width */
        @media print {
            @page {
                margin: 0;
                size: 80mm auto; /* Default to 80mm, adjust to 58mm if needed */
            }
            
            /* 58mm printer support */
            @media (max-width: 58mm) {
                .receipt {
                    max-width: 58mm;
                    padding: 3mm;
                }
            }
            
            html, body {
                width: 80mm;
                margin: 0;
                padding: 0;
            }
            
            /* Hide everything except receipt content */
            body > *:not(.receipt) {
                display: none !important;
            }
            
            .receipt {
                display: block !important;
            }
            
            /* Print optimizations */
            .no-print {
                display: none !important;
            }
            
            .receipt {
                page-break-inside: avoid;
                page-break-after: always;
            }
            .receipt:last-child {
                page-break-after: avoid;
            }
        }
        
        /* Header styles */
        .header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #000;
        }
        
        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        
        .company-address {
            font-size: 10px;
            margin: 2px 0;
        }
        
        .company-contact {
            font-size: 9px;
            margin: 1px 0;
        }
        
        /* Receipt info */
        .receipt-info {
            margin: 8px 0;
            font-size: 10px;
        }
        
        .receipt-info-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        
        .receipt-info-label {
            font-weight: bold;
        }
        
        /* Divider */
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        
        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 10px;
        }
        
        .items-table thead {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        
        .items-table th {
            padding: 4px 2px;
            font-weight: bold;
            text-align: left;
        }
        
        .items-table th.qty,
        .items-table td.qty {
            text-align: center;
            width: 15%;
        }
        
        .items-table th.price,
        .items-table td.price,
        .items-table th.total,
        .items-table td.total {
            text-align: right;
            width: 20%;
        }
        
        .items-table td {
            padding: 3px 2px;
            border-bottom: 1px dashed #ddd;
        }
        
        .item-name {
            word-wrap: break-word;
            word-break: break-word;
            max-width: 45%;
        }
        
        /* Totals section */
        .totals {
            margin: 8px 0;
            font-size: 11px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            padding: 2px 0;
        }
        
        .total-row.subtotal {
            border-top: 1px dashed #000;
            padding-top: 6px;
            margin-top: 8px;
        }
        
        .total-row.grand-total {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 6px 0;
            margin: 8px 0;
            font-weight: bold;
            font-size: 12px;
        }
        
        .total-label {
            font-weight: bold;
        }
        
        .total-value {
            text-align: right;
        }
        
        /* Payment info */
        .payment-info {
            margin: 8px 0;
            font-size: 10px;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px dashed #000;
            font-size: 9px;
        }
        
        .thank-you {
            font-size: 11px;
            font-weight: bold;
            margin: 8px 0;
        }
        
        /* Payment method badge */
        .payment-badge {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #000;
            font-size: 9px;
            margin-left: 4px;
        }
        
        /* Split payment details */
        .split-payment {
            margin-top: 4px;
            padding-left: 8px;
            font-size: 9px;
        }
        
        .split-payment-item {
            margin: 2px 0;
        }
    </style>
</head>
<body>
    <div class="receipt">
        {{-- Company Header --}}
        <div class="header">
            <div class="company-name">{{ str_replace('CENTER', 'CENTRE', strtoupper(optional($setting)->nama_perusahaan ?? 'UTAMADUNI')) }}</div>
            <div class="company-address">{{ strtoupper(optional($setting)->alamat ?? '') }}</div>
            @if(optional($setting)->telp)
            <div class="company-contact">Tel: {{ optional($setting)->telp }}</div>
            @endif
            @if(optional($setting)->email)
            <div class="company-contact">Email: {{ optional($setting)->email }}</div>
            @endif
            @if(optional($setting)->pin_no)
            <div class="company-contact">PIN: {{ optional($setting)->pin_no }}</div>
            @endif
        </div>
        
        {{-- Receipt Information --}}
        <div class="receipt-info">
            <div class="receipt-info-row">
                <span>Date:</span>
                <span>{{ $penjualan->getReceiptDateFormatted() }}</span>
            </div>
            <div class="receipt-info-row">
                <span>Time:</span>
                <span>{{ $penjualan->created_at ? \Carbon\Carbon::parse($penjualan->created_at)->format('H:i:s') : date('H:i:s') }}</span>
            </div>
            <div class="receipt-info-row">
                <span>Receipt No:</span>
                <span class="receipt-info-label">{{ $penjualan->receiptno }}</span>
            </div>
            <div class="receipt-info-row">
                <span>Transaction No:</span>
                <span>{{ str_pad($penjualan->id_penjualan, 10, '0', STR_PAD_LEFT) }}</span>
            </div>
        </div>
        
        <div class="divider"></div>
        
        {{-- Items Table --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th class="item-name">Item(s)</th>
                    <th class="qty">QTY</th>
                    <th class="price">COST</th>
                    <th class="total">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detail as $item)
                <tr>
                    <td class="item-name">@if($item->produk){{ $item->produk->nama_produk }}@else[Missing product #{{ (int) ($item->id_produk ?? 0) }}]@endif</td>
                    <td class="qty">{{ $item->jumlah }}</td>
                    <td class="price">{{ number_format((float) ($item->harga_jual ?? 0), 2) }}</td>
                    <td class="total">{{ number_format((float) ($item->subtotal ?? 0), 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div class="divider"></div>
        
        {{-- Totals Section --}}
        <div class="totals">
            @php
                $receiptTotals = $penjualan->getReceiptBreakdown();
                $subTotal = $receiptTotals['subtotal'];
                $vatAmount = $receiptTotals['vat'];
                $discountAmount = $receiptTotals['discount_amount'];
                $discountLabel = $receiptTotals['discount_label'];
                $discountType = $penjualan->discount_type ?? 'percentage';
            @endphp
            
            <div class="total-row">
                <span>Sub Total:</span>
                <span class="total-value">{{ number_format($subTotal, 2) }}</span>
            </div>
            
            @if($discountAmount > 0)
            <div class="total-row">
                <span>{{ $discountLabel }}:</span>
                <span class="total-value">-{{ number_format($discountAmount, $discountType === 'percentage' ? 0 : 2) }}</span>
            </div>
            @endif
            
            <div class="total-row">
                <span>VAT (16%):</span>
                <span class="total-value">{{ number_format($vatAmount, 2) }}</span>
            </div>
            
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span class="total-value">{{ number_format($receiptTotals['total'], 2) }}</span>
            </div>
        </div>
        
        {{-- Payment Information --}}
        <div class="payment-info">
            @php
                $paymentMethod = $penjualan->payment_method ?? 'Cash';
                $splitDetails = null;
                if ($paymentMethod === 'Split' && $penjualan->payment_split_details) {
                    $splitDetails = json_decode($penjualan->payment_split_details, true);
                }
            @endphp
            
            <div class="payment-row">
                <span>Payment Method:</span>
                <span>
                    {{ $paymentMethod }}
                    @if($paymentMethod === 'Split' && $splitDetails)
                        <span class="payment-badge">SPLIT</span>
                    @endif
                </span>
            </div>
            
            @if($paymentMethod === 'Split' && $splitDetails)
            <div class="split-payment">
                @if(isset($splitDetails['cash']) && $splitDetails['cash'] > 0)
                <div class="split-payment-item">Cash: {{ number_format((float) ($splitDetails['cash'] ?? 0), 2) }}</div>
                @endif
                @if(isset($splitDetails['mpesa']) && $splitDetails['mpesa'] > 0)
                <div class="split-payment-item">Mpesa: {{ number_format((float) ($splitDetails['mpesa'] ?? 0), 2) }}</div>
                @endif
                @if(isset($splitDetails['card']) && $splitDetails['card'] > 0)
                <div class="split-payment-item">Card: {{ number_format((float) ($splitDetails['card'] ?? 0), 2) }}</div>
                @endif
            </div>
            @endif
            
            @if((float) ($penjualan->diterima ?? 0) > 0)
            <div class="payment-row">
                <span>Amount Received:</span>
                <span>{{ number_format((float) ($penjualan->diterima ?? 0), 2) }}</span>
            </div>
            <div class="payment-row">
                <span>Change:</span>
                <span>{{ number_format((float) ($penjualan->diterima ?? 0) - (float) ($penjualan->bayar ?? 0), 2) }}</span>
            </div>
            @endif
        </div>
        
        {{-- Footer --}}
        <div class="footer">
            <div style="margin-bottom: 4px;">Served by {{ ucwords(strtolower(optional($penjualan->user)->name ?? auth()->user()->name ?? 'N/A')) }}</div>
            <div class="thank-you">-- THANK YOU --</div>
            <div>We appreciate your business!</div>
        </div>
    </div>
    
    {{-- Auto-Print Script --}}
    <script>
        (function() {
            function triggerPrint() {
                try { window.print(); } catch (e) {}
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    requestAnimationFrame(function () {
                        requestAnimationFrame(triggerPrint);
                    });
                });
            } else {
                requestAnimationFrame(function () {
                    requestAnimationFrame(triggerPrint);
                });
            }
            
            // Close window after printing (if not cancelled)
            let printed = false;
            
            // Listen for print dialog close
            window.addEventListener('beforeprint', function() {
                printed = false;
            });
            
            window.addEventListener('afterprint', function() {
                printed = true;
                // Close window after a short delay
                setTimeout(function() {
                    // Only close if we're in a popup/new window
                    if (window.opener || window.history.length <= 1) {
                        window.close();
                    } else {
                        // If not a popup, redirect back to POS
                        window.location.href = '{{ route("transaksi.baru") }}';
                    }
                }, 500);
            });
            
            // Fallback: Close after timeout if user doesn't interact
            // This handles cases where print dialog might not fire events
            setTimeout(function() {
                if (!printed && (window.opener || window.history.length <= 1)) {
                    // Only auto-close if it seems like a popup
                    // Don't auto-close main window to avoid disrupting workflow
                    if (window.opener) {
                        window.close();
                    }
                }
            }, 5000);
        })();
    </script>
</body>
</html>



