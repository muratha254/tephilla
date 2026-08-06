<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$trip = App\Models\FleetTrip::where('trip_code', 'OT-2026-001')->first();

if (! $trip) {
    echo "Trip not found.\n";
    exit(1);
}

$controller = app(App\Http\Controllers\FleetTripController::class);
$response = $controller->liveMapData($trip);
$data = json_decode($response->getContent(), true);

echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
