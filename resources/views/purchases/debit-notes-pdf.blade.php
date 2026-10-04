<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Debit Notes Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; margin: 28px 32px; }
        .header { width: 100%; margin-bottom: 28px; }
        .header td { vertical-align: top; }
        .logo { font-size: 28px; font-weight: 700; letter-spacing: -0.5px; }
        .logo-sub { color: #A2502B; font-size: 18px; }
        .logo img { max-height: 56px; max-width: 180px; }
        .company { text-align: right; font-size: 12px; line-height: 1.45; }
        .company strong { font-size: 13px; }
        .title { text-align: center; font-size: 20px; font-weight: 700; letter-spacing: 1px; margin: 10px 0 18px; text-decoration: underline; }
        .dates { border: 1px solid #000; width: 260px; padding: 6px 10px; line-height: 1.6; margin-bottom: 18px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #000; padding: 6px 8px; }
        table.data th { text-align: center; font-weight: 700; }
        .right { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="42%">
                @if(!empty($logo_pdf_path) && file_exists($logo_pdf_path))
                    <img src="{{ $logo_pdf_path }}" alt="{{ $systemName ?? 'TEPHILLA SYSTEM' }}">
                @else
                    <div class="logo">{{ $systemName ?? 'TEPHILLA SYSTEM' }}</div>
                @endif
            </td>
            <td class="company">
                <strong>{{ strtoupper($companyName ?? optional($company)->name ?? 'TEPHILLA SYSTEM') }}</strong><br>
                @if(!empty($companyProfile['companyAddress']) && $companyProfile['companyAddress'] !== '-')
                    {{ $companyProfile['companyAddress'] }}<br>
                @endif
                @if(!empty(optional($company)->city))
                    {{ $company->city }}<br>
                @endif
                Email: {{ $companyProfile['companyEmail'] ?? optional($company)->email }}<br>
                Website: {{ $companyProfile['companyWebsite'] ?? optional($company)->website }}<br>
                Tel: {{ $companyProfile['companyPhone'] ?? optional($company)->phone }}<br>
                Pin: {{ $companyProfile['companyTaxPin'] ?? optional($company)->tax_pin }}
            </td>
        </tr>
    </table>

    <div class="title">DEBIT NOTES REPORT</div>

    <div class="dates">
        FROM DATE: {{ $fromDate }}<br>
        TO DATE: &nbsp;&nbsp; {{ $toDate }}
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>DATE</th>
                <th>COMPANY NAME</th>
                <th>INV NO</th>
                <th>TOTAL BILL</th>
                <th>EXCLUSIVE VAT</th>
                <th>VAT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($notes as $note)
                <tr>
                    <td>{{ $note->date }}</td>
                    <td>{{ $note->company_name }}</td>
                    <td>{{ $note->inv_no }}</td>
                    <td class="right">Ksh {{ number_format($note->total, 2) }}</td>
                    <td class="right">Ksh {{ number_format($note->exclusive, 2) }}</td>
                    <td class="right">Ksh {{ number_format($note->vat, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endforelse
            <tr>
                <td></td>
                <td></td>
                <td class="center"><strong>TOTAL</strong></td>
                <td class="right"><strong>Ksh {{ number_format($totals['bill'] ?? 0, 2) }}</strong></td>
                <td class="right"><strong>Ksh {{ number_format($totals['exclusive'] ?? 0, 2) }}</strong></td>
                <td class="right"><strong>Ksh {{ number_format($totals['vat'] ?? 0, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
