<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/payment/cash/sales-generated/data', 'GET', [
    'draw' => 1,
    'start' => 0,
    'length' => 25,
    'start_date' => '2026-06-01',
    'end_date' => '2026-06-30',
]);

$controller = app(App\Http\Controllers\PaymentController::class);
$start = microtime(true);
$resp = $controller->salesGeneratedCashPaymentsData($request);
echo 'June datatables: ' . round(microtime(true) - $start, 2) . "s\n";
echo substr($resp->getContent(), 0, 200) . "\n";
