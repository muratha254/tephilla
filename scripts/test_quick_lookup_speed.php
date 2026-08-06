<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = app(App\Http\Controllers\ProdukController::class);
$request = Illuminate\Http\Request::create('/produk/quick-lookup', 'GET', [
    'item_code' => 'A-TEST',
    'shop_id' => 1,
]);

$start = microtime(true);
for ($i = 0; $i < 3; $i++) {
    $controller->quickLookup($request);
}
echo '3 lookups: ' . round((microtime(true) - $start) * 1000) . "ms\n";
