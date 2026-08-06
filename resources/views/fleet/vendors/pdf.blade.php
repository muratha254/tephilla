<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vehicle Vendors Info</title>
    <style>
        @page { margin: 12mm; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3c8dbc;
        }
        .header h1 {
            font-size: 16pt;
            margin: 0 0 4px;
            color: #2e3d4f;
        }
        .header h2 {
            font-size: 12pt;
            margin: 0;
            color: #3c8dbc;
            font-weight: normal;
        }
        .meta {
            margin-bottom: 12px;
            font-size: 8pt;
            color: #666;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data th {
            background: #f0f4f8;
            color: #2e3d4f;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px 6px;
            border: 1px solid #d7dee8;
            text-align: left;
        }
        table.data td {
            padding: 7px 6px;
            border: 1px solid #e3e8ef;
            vertical-align: top;
            font-size: 8.5pt;
        }
        table.data tbody tr:nth-child(even) {
            background: #fbfcfd;
        }
        .center { text-align: center; }
        .status-active {
            color: #00a65a;
            font-weight: bold;
        }
        .status-inactive {
            color: #dd4b39;
            font-weight: bold;
        }
        .footer {
            margin-top: 14px;
            font-size: 8pt;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        @include('fleet.partials.pdf_report_header')
        <h2>Vehicle Vendors Info</h2>
    </div>

    <div class="meta">
        Generated: {{ $generatedAt }}
        @if ($search)
            | Search: {{ $search }}
        @endif
        | Total vendors: {{ $vendors->count() }}
    </div>

    <table class="data">
        <thead>
            <tr>
                <th class="center" style="width: 40px;">S.No</th>
                <th>Company</th>
                <th>Contact Person</th>
                <th>Mobile</th>
                <th>Date Of Contract</th>
                <th>Contract Doc</th>
                <th>Address</th>
                <th class="center">Is Active</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vendors as $index => $vendor)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $vendor->company }}</td>
                <td>{{ $vendor->contact_person }}</td>
                <td>{{ $vendor->mobile }}</td>
                <td>{{ format_fleet_date($vendor->contract_date) }}</td>
                <td>{{ $vendor->contract_doc ? 'Yes' : '-' }}</td>
                <td>{{ $vendor->address ?: '-' }}</td>
                <td class="center {{ $vendor->is_active ? 'status-active' : 'status-inactive' }}">
                    {{ $vendor->is_active ? 'Active' : 'Inactive' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="center">No vehicle vendors found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Developed By Codeforts. | Version 8.0</div>
</body>
</html>
