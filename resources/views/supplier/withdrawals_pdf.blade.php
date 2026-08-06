<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Withdrawals Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            padding: 0;
        }
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 6px;
        }
        .brand img {
            height: 40px;
        }
        .period {
            text-align: center;
            margin-bottom: 20px;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            font-size: 10px;
            word-wrap: break-word;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .footer {
            margin-top: 20px;
            font-size: 10px;
            text-align: right;
            color: #666;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            @if(file_exists(public_path('images/utamaduni.png')))
            <img src="{{ public_path('images/utamaduni.png') }}" alt="Logo">
            @endif
            <div>
                <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
            </div>
        </div>
        @if($setting && $setting->alamat)
        <div style="font-size: 10px; color: #666; margin: 3px 0;">{{ $setting->alamat }}</div>
        @endif
        <div style="font-size: 14px; font-weight: bold; margin-top: 5px;">Supplier Withdrawals Report</div>
    </div>

    <div class="period">
        @if($startDate || $endDate)
            <strong>Period:</strong> 
            {{ $startDate ? date('d/m/Y', strtotime($startDate)) : 'All' }} - 
            {{ $endDate ? date('d/m/Y', strtotime($endDate)) : 'All' }}
        @else
            <strong>Period:</strong> All Time
        @endif
        @if($supplier)
            <br>
            <strong>Supplier:</strong> {{ $supplier->nama }}
        @else
            <br>
            <strong>Supplier:</strong> All Suppliers
        @endif
        <br>
        <strong>Generated:</strong> {{ $exportDate }}
    </div>

    @if($withdrawals->count() > 0)
    <table>
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 8%;">Withdrawal #</th>
                <th style="width: 7%;">Receipt No.</th>
                <th style="width: 7%;">Date</th>
                <th style="width: 10%;">Supplier</th>
                <th style="width: 8%;">Product Code</th>
                <th style="width: 10%;">Product Name</th>
                <th style="width: 5%;" class="text-center">Qty</th>
                <th style="width: 6%;" class="text-right">Unit Price</th>
                <th style="width: 7%;" class="text-right">Total Amount</th>
                <th style="width: 7%;" class="text-center">Stock After</th>
                <th style="width: 12%;">Reason</th>
                <th style="width: 5%;">User</th>
            </tr>
        </thead>
        <tbody>
            @foreach($withdrawals as $index => $withdrawal)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $withdrawal->withdrawal_number ?? 'N/A' }}</td>
                <td>{{ !empty($withdrawal->receipt_no) ? $withdrawal->receipt_no : '—' }}</td>
                <td>{{ $withdrawal->withdrawal_date ? date('d/m/Y', strtotime($withdrawal->withdrawal_date)) : 'N/A' }}</td>
                <td>{{ $withdrawal->supplier->nama ?? 'N/A' }}</td>
                <td>{{ $withdrawal->produk->kode_produk ?? 'N/A' }}</td>
                <td>{{ $withdrawal->produk->nama_produk ?? 'N/A' }}</td>
                <td class="text-center">{{ $withdrawal->quantity ?? 0 }}</td>
                <td class="text-right">{{ number_format($withdrawal->unit_price ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($withdrawal->total_amount ?? 0, 2) }}</td>
                <td class="text-center">{{ isset($withdrawal->stock_after) ? $withdrawal->stock_after : ($withdrawal->produk->stok ?? 0) }}</td>
                <td style="word-wrap: break-word;">{{ $withdrawal->reason ?? '-' }}</td>
                <td>{{ $withdrawal->user->name ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="7" class="text-right"><strong>TOTALS:</strong></td>
                <td class="text-center"><strong>{{ number_format($totalQuantity, 0) }}</strong></td>
                <td></td>
                <td class="text-right"><strong>{{ number_format($totalAmount, 2) }}</strong></td>
                <td></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <div><strong>Total Records:</strong> {{ $withdrawals->count() }}</div>
        <div><strong>Total Quantity before withdrawal:</strong> {{ number_format($totalQuantityBefore ?? 0, 0) }}</div>
        <div><strong>Total Quantity withdrawal:</strong> {{ number_format($totalQuantity, 0) }}</div>
        <div><strong>Remaining Quantity:</strong> {{ number_format(($totalQuantityBefore ?? 0) - $totalQuantity, 0) }}</div>
        <div><strong>Total Amount:</strong> <strong>Ksh {{ number_format($totalAmount, 2) }}</strong></div>
    </div>
    @else
    <div class="no-data">
        <p>No withdrawal records found for the selected criteria.</p>
    </div>
    @endif
</body>
</html>

