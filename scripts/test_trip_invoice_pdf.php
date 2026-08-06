<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$trip = App\Models\FleetTrip::with(['vehicle', 'driver'])->first();

if (! $trip) {
    echo "No trip found.\n";
    exit(1);
}

$pdf = PDF::loadView('fleet.trips.invoice_pdf', [
    'companyName' => 'One Translines Pvt Ltd',
    'companyAddress' => 'Nairobi, Kenya',
    'companyPhone' => '+254 700 000 000',
    'companyEmail' => 'billing@onetranslines.co.ke',
    'trip' => $trip,
    'invoiceDate' => now()->format('d M Y'),
    'generatedAt' => now()->format('Y-m-d H:i'),
])->setPaper('a4', 'portrait');

$path = storage_path('app/test-trip-invoice.pdf');
file_put_contents($path, $pdf->output());

echo 'Generated ' . $trip->invoiceNumber() . ' -> ' . $path . ' (' . filesize($path) . " bytes)\n";
