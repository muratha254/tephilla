<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Note {{ $sale->documentNumber() }}</title>
    <style>
        @page { margin: 22px 28px 28px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .logo img { max-height: 52px; max-width: 160px; }
        .brand { font-size: 18px; font-weight: 700; }
        .company { text-align: right; font-size: 11px; line-height: 1.45; text-transform: uppercase; }
        .company .doc-title { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .company .name { font-size: 12px; font-weight: 700; margin-bottom: 2px; }
        .bar { margin-top: 16px; }
        .bar td { border: 1px solid #000; padding: 6px 8px; width: 50%; }
        .items { margin-top: 10px; }
        .items th, .items td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        .items th { background: #A2502B; color: #fff; font-weight: 700; text-align: left; }
        .col-qty { width: 18%; text-align: center; }
        .col-uom { width: 16%; text-align: center; }
        .items .qty, .items .uom { text-align: center; }
        .items .fill td { border-top: 0; }
        .ship-title { margin-top: 18px; border: 1px solid #000; border-bottom: 0; padding: 6px 8px; font-weight: 700; }
        .ship th, .ship td { border: 1px solid #000; padding: 6px 8px; }
        .ship th { text-align: left; font-weight: 700; }
        .ship .blank td { height: 36px; }
        .signs { margin-top: 28px; }
        .signs .col { width: 50%; vertical-align: top; padding-right: 18px; }
        .signs .col + .col { padding-right: 0; padding-left: 18px; }
        .sign-row { margin-bottom: 14px; }
        .sign-row table td { padding: 0 0 2px; vertical-align: bottom; }
        .sign-label { width: 38%; white-space: nowrap; padding-right: 6px; }
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
    $deliveryNo = str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT);
    $date = optional($sale->sale_date)->format('d-M-Y') ?: now()->format('d-M-Y');
    $logo = $logo_pdf_path ?? ($profile['logo_pdf_path'] ?? null);
    $fillHeight = max(90, 280 - (count($sale->items) * 28));
    $lpo = '';
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
                <div class="doc-title">DELIVERY NOTE</div>
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

    <table class="bar">
        <tr>
            <td>NAME: {{ $customer }}</td>
            <td>DELIVERY No: {{ $deliveryNo }}</td>
        </tr>
        <tr>
            <td>LPO No: {{ $lpo }}</td>
            <td>DATE: {{ $date }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>ITEM DESCRIPTION</th>
                <th class="col-qty">QUANTITY</th>
                <th class="col-uom">UOM</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                @php
                    $unit = optional(optional($item->product)->unit);
                    $uom = strtoupper($unit->short_name ?: $unit->name ?: 'PCS');
                @endphp
                <tr>
                    <td>{{ strtoupper($item->name) }}</td>
                    <td class="qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                    <td class="uom">{{ $uom }}</td>
                </tr>
            @endforeach
            <tr class="fill">
                <td style="height: {{ $fillHeight }}px;">&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <div class="ship-title">Shipping Details:</div>
    <table class="ship">
        <thead>
            <tr>
                <th>Driver Name</th>
                <th>Driver ID</th>
                <th>Driver Phone</th>
                <th>Vehicle Reg no</th>
            </tr>
        </thead>
        <tbody>
            <tr class="blank">
                <td>&nbsp;</td>
                <td>&nbsp;</td>
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
                            <td class="sign-label">Date:</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td class="col">
                <div class="sign-row">
                    <table>
                        <tr>
                            <td class="sign-label">Received By:</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
                <div class="sign-row">
                    <table>
                        <tr>
                            <td class="sign-label">Comment:</td>
                            <td class="sign-line">&nbsp;</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">{{ $profile['invoiceFooter'] ?? 'Thank you for your business.' }}</div>
</body>
</html>
