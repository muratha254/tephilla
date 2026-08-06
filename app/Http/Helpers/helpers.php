<?php

function fleet_system_name(): string
{
    return (string) config('fleet.system_name', 'Fleet Management & Tracking Solution');
}

function fleet_system_short_name(): string
{
    return (string) config('fleet.system_short_name', 'Fleet Management');
}

function fleet_document_profile(string $type): array
{
    try {
        return \App\Models\FleetSetting::current()->documentProfile($type);
    } catch (\Throwable $e) {
        $base = fleet_company_profile();

        return array_merge($base, [
            'documentHeader' => '',
            'documentFooter' => $base['invoiceFooter'] ?? '',
            'documentHeaderImageUrl' => null,
            'documentHeaderImagePdfPath' => null,
            'hasDocumentHeaderImage' => false,
            'documentFooterImageUrl' => null,
            'documentFooterImagePdfPath' => null,
            'hasDocumentFooterImage' => false,
            'documentHeaderImageHeightMm' => null,
            'documentFooterImageHeightMm' => null,
            'documentHeaderImageHeightPt' => null,
            'documentFooterImageHeightPt' => null,
            'documentFooterIsCustom' => false,
        ]);
    }
}

function fleet_company_profile(): array
{
    try {
        return \App\Models\FleetSetting::current()->companyProfile();
    } catch (\Throwable $e) {
        return [
            'companyName' => 'Your Company Name',
            'companyAddress' => '-',
            'companyPhone' => '-',
            'companyEmail' => '-',
            'companyWebsite' => '',
            'companyTaxPin' => '',
            'invoiceFooter' => 'Thank you for your business.',
            'logo_url' => null,
            'logo_pdf_path' => null,
        ];
    }
}

function fleet_shared_view_data(int $notificationCount = 11): array
{
    $settings = \App\Models\FleetSetting::current();
    $documentProfile = $settings->companyProfile();

    return [
        'companyName' => fleet_system_name(),
        'systemName' => fleet_system_name(),
        'systemShortName' => fleet_system_short_name(),
        'documentCompanyProfile' => $documentProfile,
        'companyProfile' => $documentProfile,
        'companyLogoUrl' => null,
        'notificationCount' => $notificationCount,
        'fleetSetting' => $settings,
    ];
}

function format_uang ($angka) {
    return number_format((float) ($angka ?? 0), 0, ',', ',');
}

function format_kes($amount, $decimals = 2): string
{
    return 'KSh ' . number_format((float) ($amount ?? 0), $decimals);
}

function kes_symbol(): string
{
    return 'KSh';
}

function format_fleet_date($date, bool $withTime = false): string
{
    if ($date === null || $date === '') {
        return '-';
    }

    try {
        $parsed = $date instanceof \Carbon\CarbonInterface
            ? $date
            : \Carbon\Carbon::parse($date);
    } catch (\Throwable $e) {
        return (string) $date;
    }

    try {
        $settingFormat = \App\Models\FleetSetting::current()->date_format ?? 'd/m/Y';
    } catch (\Throwable $e) {
        $settingFormat = 'd/m/Y';
    }

    $dateFormat = preg_replace('/\s*[HhGgIiSsAa]+.*$/', '', $settingFormat) ?: 'd/m/Y';

    if (! $withTime || ! preg_match('/[HhGgIi]/', $settingFormat)) {
        return $parsed->format($dateFormat);
    }

    return $parsed->format($settingFormat);
}

function parse_fleet_date_input(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    try {
        return \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
    } catch (\Throwable $e) {
        return null;
    }
}

function fleet_date_input_value($date): string
{
    if ($date === null || $date === '') {
        return '';
    }

    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', (string) $date)) {
        return (string) $date;
    }

    return format_fleet_date($date) === '-' ? '' : format_fleet_date($date);
}

function terbilang($angka)
{
    $angka = abs($angka);
    $baca = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'
    ];

    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($angka < 20) {
        $terbilang = ' ' . $baca[$angka];
    } elseif ($angka < 100) {
        $terbilang = ' ' . $tens[(int)($angka / 10)];
        if ($angka % 10 !== 0) {
            $terbilang .= ' ' . $baca[$angka % 10];
        }
    } elseif ($angka < 1000) {
        $terbilang = ' ' . $baca[(int)($angka / 100)] . ' Hundred';
        if ($angka % 100 !== 0) {
            $terbilang .= ' and' . terbilang($angka % 100);
        }
    } elseif ($angka < 1000000) {
        $terbilang = terbilang((int)($angka / 1000)) . ' Thousand';
        if ($angka % 1000 !== 0) {
            $terbilang .= terbilang($angka % 1000);
        }
    } elseif ($angka < 1000000000) {
        $terbilang = terbilang((int)($angka / 1000000)) . ' Million';
        if ($angka % 1000000 !== 0) {
            $terbilang .= terbilang($angka % 1000000);
        }
    } elseif ($angka < 1000000000000) {
        $terbilang = terbilang((int)($angka / 1000000000)) . ' Billion';
        if ($angka % 1000000000 !== 0) {
            $terbilang .= terbilang($angka % 1000000000);
        }
    } else {
        $terbilang = 'Number is too large to convert.';
    }

    return $terbilang;
}
// visit "codeastro" for more projects!
function tanggal_indonesia($tgl, $tampil_hari = true)
{
    $nama_hari  = array(
        'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'
    );
    $nama_bulan = array(1 =>
        'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'
    );

    $tahun   = substr($tgl, 0, 4);
    $bulan   = $nama_bulan[(int) substr($tgl, 5, 2)];
    $tanggal = substr($tgl, 8, 2);
    $text    = '';

    if ($tampil_hari) {
        $urutan_hari = date('w', mktime(0,0,0, substr($tgl, 5, 2), $tanggal, $tahun));
        $hari        = $nama_hari[$urutan_hari];
        $text       .= "$hari, $tanggal $bulan $tahun";
    } else {
        $text       .= "$tanggal $bulan $tahun";
    }
    
    return $text; 
}
// visit "codeastro" for more projects!
function tambah_nol_didepan($value, $threshold = null)
{
    return sprintf("%0". $threshold . "s", $value);
}