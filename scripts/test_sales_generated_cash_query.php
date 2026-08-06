<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/payment/cash/sales-generated/data', 'GET', [
    'draw' => 1,
    'start' => 0,
    'length' => 10,
]);

$controller = app(App\Http\Controllers\PaymentController::class);

echo "Testing excluded IDs query...\n";
try {
    $start = microtime(true);
    $ids = app(App\Services\EnsureSaleSupplierLedgerService::class)->unconfirmedDeferredCashPembelianDetailIds();
    echo 'Excluded IDs: ' . count($ids) . ' in ' . round(microtime(true) - $start, 3) . "s\n";
} catch (Throwable $e) {
    echo "Excluded ERROR: " . $e->getMessage() . "\n";
}

echo "Testing buildSalesGeneratedCashQuery...\n";
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('buildSalesGeneratedCashQuery');
$method->setAccessible(true);

try {
    $start = microtime(true);
    $query = $method->invoke($controller, $request);
    $sql = $query->toSql();
    echo "SQL built in " . round(microtime(true) - $start, 3) . "s\n";
    echo substr($sql, 0, 500) . "...\n";

    $start = microtime(true);
    $count = (clone $query)->count();
    echo "Count: {$count} in " . round(microtime(true) - $start, 3) . "s\n";

    $start = microtime(true);
    $rows = (clone $query)->limit(10)->get();
    echo "Rows: " . $rows->count() . " in " . round(microtime(true) - $start, 3) . "s\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nTesting full datatables endpoint...\n";
try {
    $start = microtime(true);
    $resp = $controller->salesGeneratedCashPaymentsData($request);
    echo "Response status: " . $resp->getStatusCode() . " in " . round(microtime(true) - $start, 3) . "s\n";
    echo substr($resp->getContent(), 0, 300) . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
