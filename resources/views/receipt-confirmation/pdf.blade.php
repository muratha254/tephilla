<!DOCTYPE html>
<html>
<head>
    <title>Receipt Confirmation Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header img {
            width: 60px;
            height: 60px;
        }
        .header h2 {
            margin: 5px 0;
            color: #333;
        }
        .header h3 {
            margin: 5px 0;
            color: #666;
        }
        .info-section {
            margin-bottom: 10px;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        tfoot td {
            font-weight: bold;
            background-color: #e9e9e9;
        }
        .status-pending {
            color: #f39c12;
            font-weight: bold;
        }
        .status-confirmed {
            color: #27ae60;
            font-weight: bold;
        }
        .status-defect {
            color: #e74c3c;
            font-weight: bold;
        }
        .status-review {
            color: #3498db;
            font-weight: bold;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(file_exists(public_path('images/utamaduni.png')))
        <img src="{{ public_path('images/utamaduni.png') }}" alt="Logo">
        @endif
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
        @if($setting && $setting->alamat)
        <p style="font-size: 10px; color: #666; margin: 3px 0;">{{ $setting->alamat }}</p>
        @endif
        <h3>Receipt Confirmation Report</h3>
        <div class="info-section">
            @if($startDate != 'all' && $endDate != 'all')
            <strong>Period:</strong> {{ date('d/m/Y', strtotime($startDate)) }} - {{ date('d/m/Y', strtotime($endDate)) }}
            @else
            <strong>Period:</strong> All Records
            @endif
            @if($statusFilter != 'all')
            | <strong>Status:</strong> {{ ucfirst($statusFilter) }}
            @endif
            | <strong>Generated:</strong> {{ date('d/m/Y H:i:s') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="9%">Receipt Number</th>
                <th width="10%">Shop Name</th>
                <th width="5%">Items</th>
                <th width="22%">Items Name</th>
                <th width="9%">Total Amount</th>
                <th width="9%">Date of Sale</th>
                <th width="10%">Cashier</th>
                <th width="10%">Status</th>
            </tr>
        </thead>
        <tbody>
            @if(count($reportData) > 0)
                @foreach($reportData as $index => $data)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $data['receipt_number'] }}</td>
                    <td>{{ $data['shop_name'] }}</td>
                    <td class="text-center">{{ $data['items_sold'] }}</td>
                    <td style="font-size: 9px;">{{ substr($data['items_name'], 0, 70) }}{{ strlen($data['items_name']) > 70 ? '...' : '' }}</td>
                    <td class="text-right">Ksh {{ number_format($data['total_amount'], 2) }}</td>
                    <td>{{ $data['date_of_sale'] }}</td>
                    <td>{{ $data['cashier'] ?? 'N/A' }}</td>
                    <td class="text-center">
                        @if($data['status'] == 'Pending')
                            <span class="status-pending">{{ $data['status'] }}</span>
                        @elseif($data['status'] == 'Confirmed' || $data['status'] == 'Confirmed with Edit')
                            <span class="status-confirmed">{{ $data['status'] }}</span>
                        @elseif($data['status'] == 'Defect')
                            <span class="status-defect">{{ $data['status'] }}</span>
                        @elseif($data['status'] == 'Review')
                            <span class="status-review">{{ $data['status'] }}</span>
                        @else
                            {{ $data['status'] }}
                        @endif
                    </td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="9" class="text-center">No records found</td>
                </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"><strong>Total</strong></td>
                <td class="text-center"><strong>{{ $totalItems ?? 0 }}</strong></td>
                <td colspan="3"></td>
                <td><strong>Total Amount:</strong></td>
                <td class="text-right"><strong>Ksh {{ number_format($totalAmount ?? 0, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 20px; font-size: 9px; text-align: center; color: #666;">
        <p>This report was generated on {{ date('d/m/Y H:i:s') }}</p>
        <p>Total Records: {{ count($reportData) }}</p>
    </div>
</body>
</html>

