<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/payment/cash/sales-generated/data', 'GET', [
    'draw' => 1,
    'start' => 0,
    'length' => 25,
    'order' => [['column' => 2, 'dir' => 'desc']],
    'columns' => array_fill(0, 9, ['data' => 'x', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']]),
]);

$controller = app(App\Http\Controllers\PaymentController::class);
$ref = new ReflectionClass($controller);
$build = $ref->getMethod('buildSalesGeneratedCashQuery');
$build->setAccessible(true);

$query = $build->invoke($controller, $request);
$start = microtime(true);
$resp = datatables()->of($query)->addIndexColumn()->make(true);
echo 'Datatables raw: ' . round(microtime(true) - $start, 2) . "s status " . $resp->getStatusCode() . "\n";
