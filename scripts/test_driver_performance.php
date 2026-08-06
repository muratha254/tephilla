<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $controller = app()->make(App\Http\Controllers\FleetDriverController::class);
    $request = Illuminate\Http\Request::create('/drivers/performance', 'GET');
    $response = $controller->performance($request);

    echo 'View: ' . $response->name() . PHP_EOL;

    $html = $response->render();
    echo 'HTML length: ' . strlen($html) . PHP_EOL;
    echo substr($html, 0, 500) . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
