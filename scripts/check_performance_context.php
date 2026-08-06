<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'APP_URL: ' . config('app.url') . PHP_EOL;
echo 'route(drivers.performance): ' . route('drivers.performance') . PHP_EOL;
echo 'trips: ' . App\Models\FleetTrip::count() . PHP_EOL;
echo 'drivers: ' . App\Models\FleetDriver::count() . PHP_EOL;
echo 'trip dates: ' . App\Models\FleetTrip::min('start_date') . ' to ' . App\Models\FleetTrip::max('start_date') . PHP_EOL;
