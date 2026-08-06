<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$suppliers = collect(range(1, 200))->map(function ($i) {
    return [
        'supplier_name' => 'SUPPLIER TEST ' . $i,
        'total_pending' => $i * 1000,
    ];
});

$pdf = PDF::loadView('payment.pending_pdf', [
    'suppliers' => $suppliers,
    'totalPending' => 820000,
    'supplierNames' => 'All',
    'filters' => ['start_date' => '2026-05-01', 'end_date' => '2026-05-31'],
    'setting' => App\Models\Setting::first(),
]);
$dompdf = $pdf->getDomPDF();
$dompdf->set_option('isPhpEnabled', true);
$dompdf->render();
$out = storage_path('app/test_pending_pdf.pdf');
file_put_contents($out, $dompdf->output());
echo 'Pages: ' . $dompdf->getCanvas()->get_page_count() . PHP_EOL;
echo "Saved: {$out}\n";
