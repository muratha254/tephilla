<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = app(App\Http\Controllers\FleetReportController::class);
$request = Illuminate\Http\Request::create('/reports/booking/export/pdf', 'GET', [
    'date_from' => '2025-12-31',
    'date_to' => '2026-01-11',
]);

$response = $controller->exportBookingPdf($request);
$content = $response->getContent();
$out = storage_path('app/test_trips_report.pdf');
file_put_contents($out, $content);

echo 'OK bytes=' . strlen($content) . ' file=' . $out . PHP_EOL;
