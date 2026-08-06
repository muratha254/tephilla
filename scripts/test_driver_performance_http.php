<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::query()->first();
if (! $user) {
    echo "No user found\n";
    exit(1);
}

Illuminate\Support\Facades\Auth::login($user);

$request = Illuminate\Http\Request::create('/drivers/performance', 'GET');
$response = $app->handle($request);

echo 'Status: ' . $response->getStatusCode() . PHP_EOL;
$content = $response->getContent();
echo 'Length: ' . strlen($content) . PHP_EOL;

if ($response->getStatusCode() !== 200) {
    echo substr($content, 0, 2000) . PHP_EOL;
    exit(1);
}

$checks = [
    'Driver Performance Breakdown',
    'fleet-report-filters',
    'fleet-driver-report-table',
    'Total Trips',
];

foreach ($checks as $check) {
    echo ($check . ': ' . (str_contains($content, $check) ? 'YES' : 'NO')) . PHP_EOL;
}
