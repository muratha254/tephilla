<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = app(App\Http\Controllers\PaymentController::class);
$ref = new ReflectionClass($controller);
$build = $ref->getMethod('buildSalesGeneratedCashQuery');
$build->setAccessible(true);

foreach ([
    'no filter' => [],
    'june 2026' => ['start_date' => '2026-06-01', 'end_date' => '2026-06-30'],
    'last 30 days' => ['start_date' => now()->subDays(30)->toDateString(), 'end_date' => now()->toDateString()],
] as $label => $params) {
    $request = Illuminate\Http\Request::create('/x', 'GET', $params);
    $query = $build->invoke($controller, $request);
    $start = microtime(true);
    $count = (clone $query)->count();
    echo "{$label}: {$count} rows in " . round(microtime(true) - $start, 2) . "s\n";
}
