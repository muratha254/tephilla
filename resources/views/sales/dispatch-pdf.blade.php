<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dispatch List {{ $sale->documentNumber() }}</title>
    <style>
        @page { margin: 22px 28px 28px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .logo img { max-height: 52px; max-width: 160px; }
        .brand { font-size: 18px; font-weight: 700; }
        .company { text-align: right; font-size: 11px; line-height: 1.45; text-transform: uppercase; }
        .company .name { font-size: 13px; font-weight: 700; margin-bottom: 2px; }
        .bar td { border: 1px solid #000; padding: 6px 8px; }
        .bar .title { font-weight: 700; letter-spacing: 0.4px; }
        .bar .date { text-align: right; }
        .items { margin-top: 10px; }
        .items th, .items td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        .items th { background: #c9a027; color: #111; font-weight: 700; text-align: left; }
        .col-no { width: 12%; }
        .col-qty { width: 16%; text-align: center; }
        .items .qty { text-align: center; }
        .items .fill td { border-top: 0; }
        .signs { margin-top: 28px; }
        .signs .col { width: 50%; vertical-align: top; padding-right: 18px; }
        .signs .col + .col { padding-right: 0; padding-left: 18px; }
        .sign-row { margin-bottom: 14px; }
        .sign-row table td { padding: 0 0 2px; vertical-align: bottom; }
        .sign-label { width: 42%; white-space: nowrap; padding-right: 6px; }
        .sign-line { border-bottom: 1px solid #111; height: 16px; }
        .footer { text-align: center; margin-top: 36px; font-size: 11px; }
    </style>
</head>
<body>
@php
    $company = $sale->company;
    $profileName = $company->name ?? $profile['companyName'] ?? fleet_system_name();
    $addressLines = preg_split('/\r\n|\r|\n/', (string) ($company->address ?? $profile['companyAddress'] ?? ''));
    $addressLines = array_values(array_filter(array_map('trim', $addressLines), function ($line) {
        return $line !== '' && $line !== '-';
    }));
    $cityLine = trim(implode(', ', array_filter([
        $company->city ?? null,
        $company->country ?? null,
    ])));
    $email = $company->email ?? $profile['companyEmail'] ?? '';
    $website = $company->website ?? $profile['companyWebsite'] ?? '';
    $phone = $company->phone ?? $profile['companyPhone'] ?? '';
    $customer = $sale->customerDisplayName();
    $dispatchNo = str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT);
    $date = optional($sale->sale_date)->format('Y-m-d') ?: now()->format('Y-m-d');
    $logo = $logo_pdf_path ?? ($profile['logo_pdf_path'] ?? null);
    $fillHeight = max(90, 390 - (count($sale->items) * 28));
@endphp
    <table class="head">
        <tr>
            <td width="42%" class="logo">
                @if(!empty($logo) && file_exists($logo))
                    <img src="{{ $logo }}" alt="">
                @else
                    <div class="brand">{{ fleet_system_name() }}</div>
                @endif
            </td>
            <td width="58%" class="company">
                <div class="name">{{ $profileName }}</div>
                @foreach($addressLines as $line)
                    <div>{{ $line }}</div>
                @endforeach
                @if($cityLine !== '')
                    <div>{{ $cityLine }}</div>
                @endif
                @if($email !== '' && $email !== '-')
                    <div>Email: {{ $email }}</div>
                @endif
                @if($website !== '')
                    <div>Website: {{ $website }}</div>
                @endif
                @if($phone !== '' && $phone !== '-')
                    <div>Tel: {{ $phone }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="bar" style="margin-top: 16px;">
        <tr>
            <td class="title" width="70%">DISPATCH LIST</td>
            <td class="date" width="30%">{{ $date }}</td>
        </tr>
        <tr>
            <td>DISPATCH NO. {{ $dispatchNo }}</td>
            <td>CUSTOMER: {{ $customer }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="col-no">NO.</th>
                <th>DETAILS</th>
                <th class="col-qty">QTY</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ strtoupper($item->name) }}</td>
                    <td class="qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                </tr>
            @endforeach
            <tr class="fill">
                <td style="height: {{ $fillHeight }}px;">&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <table class="signs">
        <tr>
            <td class="col">
                <div class="sign-row">
                    <table>
                        <tr>
                            <td class="sign-label">Prepared By:</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
                <div class="sign-row">
                    <table>
                        <tr>
                            <td class="sign-label">Signed By:</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
                <div class="sign-row">
                    <table>
                        <tr>
                            <td class="sign-label">Date:</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
                <div class="sign-row">
                    <table>
                        <tr>
                            <td class="sign-label">Agent:</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td class="col">
                <div class="sign-row">
                    <table>
                        <tr>
                            <td class="sign-label">Dispatched By:</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
                <div class="sign-row">
                    <table>
                        <tr>
                            <td class="sign-label">Transporting<br>Company</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
                <div class="sign-row">
                    <table>
                        <tr>
                            <td class="sign-label">No of Boxes</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">This Document is System Generated.</div>
</body>
</html>
