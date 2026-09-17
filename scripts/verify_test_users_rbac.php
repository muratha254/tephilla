<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$emails = [
    'test.superadmin@newpos.local',
    'test.admin@newpos.local',
    'test.manager@newpos.local',
    'test.cashier@newpos.local',
    'test.accountant@newpos.local',
    'test.inventory@newpos.local',
    'test.sales@newpos.local',
    'test.hr@newpos.local',
];

echo "TEST USERS\n";
foreach ($emails as $email) {
    $u = User::query()->where('email', $email)->with('role')->first();
    if (! $u) {
        echo "MISSING {$email}\n";
        continue;
    }
    echo sprintf(
        "%s | %s | role=%s | active=%s\n",
        $u->name,
        $u->email,
        optional($u->role)->name,
        $u->is_active ? 'yes' : 'no'
    );
}

$checks = [
    'test.cashier@newpos.local' => [
        'pos.view' => true,
        'users.view' => false,
        'accounting.view' => false,
        'settings.company' => false,
    ],
    'test.inventory@newpos.local' => [
        'inventory.transfer' => true,
        'users.view' => false,
        'hr.payroll' => false,
        'pos.operate' => false,
    ],
    'test.accountant@newpos.local' => [
        'accounting.view' => true,
        'payments.create' => true,
        'users.view' => false,
        'inventory.transfer' => false,
    ],
    'test.hr@newpos.local' => [
        'hr.payroll' => true,
        'hr.attendance' => true,
        'pos.operate' => false,
        'accounting.manage' => false,
    ],
    'test.superadmin@newpos.local' => [
        'users.view' => true,
        'settings.company' => true,
        'accounting.manage' => true,
        'inventory.transfer' => true,
    ],
];

echo "\nPERMISSION MATRIX\n";
$failures = 0;
foreach ($checks as $email => $perms) {
    $u = User::query()->where('email', $email)->first();
    foreach ($perms as $perm => $expected) {
        $actual = $u && $u->hasPermission($perm);
        $ok = $actual === $expected;
        if (! $ok) {
            $failures++;
        }
        echo sprintf(
            "%s %s %s expect=%s got=%s\n",
            $ok ? 'OK' : 'FAIL',
            $email,
            $perm,
            $expected ? 'Y' : 'N',
            $actual ? 'Y' : 'N'
        );
    }
}

// HTTP-level route checks via kernel
$kernelHttp = $app->make(Illuminate\Contracts\Http\Kernel::class);

function hit($kernelHttp, User $user, string $uri): int
{
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setUserResolver(fn () => $user);
    auth()->login($user);
    try {
        $response = $kernelHttp->handle($request);
        $status = $response->getStatusCode();
        $kernelHttp->terminate($request, $response);
        auth()->logout();

        return $status;
    } catch (Throwable $e) {
        auth()->logout();
        echo 'EXC ' . $uri . ' ' . $e->getMessage() . "\n";

        return 500;
    }
}

$routeChecks = [
    ['test.cashier@newpos.local', '/pos', [200]],
    ['test.cashier@newpos.local', '/users', [403]],
    ['test.cashier@newpos.local', '/accounting/journal', [403]],
    ['test.inventory@newpos.local', '/stock/transfers', [200]],
    ['test.inventory@newpos.local', '/hr/payroll', [403]],
    ['test.hr@newpos.local', '/hr/payroll', [200]],
    ['test.hr@newpos.local', '/pos', [403]],
    ['test.accountant@newpos.local', '/accounting/journal', [200]],
    ['test.superadmin@newpos.local', '/sales/credit-notes', [200]],
    ['test.superadmin@newpos.local', '/quotations', [200]],
    ['test.superadmin@newpos.local', '/stock/transfers', [200]],
];

echo "\nROUTE RBAC\n";
foreach ($routeChecks as [$email, $uri, $allowed]) {
    $u = User::query()->where('email', $email)->first();
    if (! $u) {
        echo "FAIL missing user {$email}\n";
        $failures++;
        continue;
    }
    $status = hit($kernelHttp, $u, $uri);
    $ok = in_array($status, $allowed, true);
    if (! $ok) {
        $failures++;
    }
    echo sprintf("%s %s %s -> %s (want %s)\n", $ok ? 'OK' : 'FAIL', $email, $uri, $status, implode('/', $allowed));
}

echo "\nFAILURES={$failures}\n";
exit($failures > 0 ? 1 : 0);
