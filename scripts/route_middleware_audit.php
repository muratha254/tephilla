<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

$fleet = 0;
$auth = 0;
$withPerm = 0;
$authNoPerm = [];

foreach (Route::getRoutes() as $route) {
    $uri = $route->uri();
    $name = (string) $route->getName();
    $mw = $route->gatherMiddleware();
    $mwStr = implode(',', $mw);

    if (stripos($uri, 'fleet') !== false || stripos($name, 'fleet') !== false) {
        $fleet++;
    }

    if (! in_array('auth', $mw, true)) {
        continue;
    }
    $auth++;

    $hasPerm = false;
    foreach ($mw as $m) {
        if (is_string($m) && str_starts_with($m, 'permission:')) {
            $hasPerm = true;
            break;
        }
    }
    if ($hasPerm) {
        $withPerm++;
        continue;
    }

    // intentional: self-service password, OR-permission voids/orders, branch switch
    if (preg_match('#^(settings/password|branch/switch|sales/voids|sales/orders|sales/\{sale\}/void)#', $uri)) {
        continue;
    }
    if (preg_match('#^(user/profile|user/password|user/two-factor|api/user)#', $uri)) {
        continue;
    }

    $authNoPerm[] = ($name ?: $uri) . ' => ' . $uri;
}

echo "auth_routes={$auth}\n";
echo "with_permission={$withPerm}\n";
echo "fleet_routes={$fleet}\n";
echo "auth_without_permission=" . count($authNoPerm) . "\n";
foreach (array_slice($authNoPerm, 0, 20) as $row) {
    echo "  - {$row}\n";
}
