<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payment = App\Models\FleetTripPayment::query()->latest('id')->first();
if (! $payment) {
    echo "NO PAYMENT\n";
    exit(1);
}

$data = array_merge(fleet_document_profile('receipt'), [
    'payment' => $payment,
    'trip' => $payment->trip,
    'generatedAt' => now()->format('d M Y H:i'),
    'tripTotal' => $payment->trip ? $payment->trip->totalAmount() : 0,
    'tripPaid' => $payment->trip ? $payment->paidAfterPayment() : 0,
    'tripRemaining' => $payment->trip ? $payment->remainingAfterPayment() : 0,
    'customerOutstanding' => $payment->customer ? round((float) $payment->customer->outstanding_payment, 2) : null,
]);

$pdf = PDF::loadView('fleet.payments.receipt_pdf', $data)->setPaper('a4', 'portrait');
$pdf->getDomPDF()->getOptions()->setIsPhpEnabled(true);

$out = storage_path('app/test_receipt.pdf');
file_put_contents($out, $pdf->output());

$dompdf = $pdf->getDomPDF();
$dompdf->render();
$pageCount = $dompdf->getCanvas()->get_page_count();

echo "OK payment={$payment->id} pages={$pageCount} file={$out}\n";
