<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::query()->first();
if ($user) {
    Illuminate\Support\Facades\Auth::login($user);
}

$request = Illuminate\Http\Request::create('/settings/general', 'GET');
$response = $app->handle($request);

echo 'Status: ' . $response->getStatusCode() . PHP_EOL;
if ($response->getStatusCode() !== 200) {
    echo substr($response->getContent(), 0, 3000) . PHP_EOL;
    exit(1);
}

$content = $response->getContent();
echo 'Length: ' . strlen($content) . PHP_EOL;
echo (str_contains($content, 'Company Info') ? 'Company Info tab: YES' : 'Company Info tab: NO') . PHP_EOL;
