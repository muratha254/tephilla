<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$trip = App\Models\FleetTrip::query()->latest('id')->first();
if (! $trip) {
    echo "NO TRIP\n";
    exit(1);
}

$data = array_merge(fleet_document_profile('invoice'), [
    'trip' => $trip,
    'generatedAt' => now()->format('d M Y H:i'),
]);

$pdf = PDF::loadView('fleet.trips.invoice_pdf', $data)->setPaper('a4', 'portrait');
$pdf->getDomPDF()->getOptions()->setIsPhpEnabled(true);

$out = storage_path('app/test_invoice.pdf');
file_put_contents($out, $pdf->output());

$dompdf = $pdf->getDomPDF();
$dompdf->render();
$pageCount = $dompdf->getCanvas()->get_page_count();

echo "OK trip={$trip->id} pages={$pageCount} file={$out}\n";
