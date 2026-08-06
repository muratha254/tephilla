<!DOCTYPE html>
<html>
<head>
    <title>Payments - {{ date('Y-m-d', strtotime($startDate)) }} to {{ date('Y-m-d', strtotime($endDate)) }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 15px; }
        .header img { width: 60px; height: 60px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f0f0f0; }
        tfoot td { font-weight: bold; background: #e9e9e9; }
    </style>
    </head>
<body>
    <div class="header">
        @if(file_exists(public_path('images/utamaduni.png')))
        <img src="{{ public_path('images/utamaduni.png') }}" alt="Logo">
        @endif
        <h2>{{ str_replace('CENTER', 'CENTRE', strtoupper($setting->nama_perusahaan ?? 'COMPANY NAME')) }}</h2>
        @if($setting && $setting->alamat)
        <p style="font-size: 11px; color: #666; margin: 3px 0;">{{ $setting->alamat }}</p>
        @endif
        <h3>Payment List</h3>
        <div><strong>Period:</strong> {{ date('d/m/Y', strtotime($startDate)) }} - {{ date('d/m/Y', strtotime($endDate)) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Mop</th>
                <th>Supplier</th>
                <th>Phone</th>
                <th>Amount</th>
                <th>Unique id</th>
            </tr>
        </thead>
        <tbody>
            @php($total = 0)
            @foreach($payments as $index => $p)
            @php($total += (float) $p->amount)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ date('Y-m-d', strtotime($p->date)) }}</td>
                <td>{{ $p->type ?? 'Cash' }}</td>
                <td>{{ $p->supplier->nama ?? '' }}</td>
                <td>{{ $p->supplier->telepon ?? '' }}</td>
                <td>Ksh {{ number_format($p->amount, 2) }}</td>
                <td>{{ $p->uniqid }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">Total Payment</td>
                <td colspan="2">Ksh {{ number_format($totalAmount ?? $total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>













