<?php

/**
 * Clear all business data while preserving admin user accounts.
 *
 * Usage: php scripts/clear_database_keep_admin.php [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$database = DB::getDatabaseName();
$key = 'Tables_in_' . $database;
$tables = array_map(
    fn ($row) => $row->{$key},
    DB::select('SHOW TABLES')
);

$skipTruncate = [
    'migrations',
    'users',
];

$admins = User::query()
    ->whereRaw('LOWER(role) = ?', ['admin'])
    ->get(['id', 'name', 'email', 'role']);

if ($admins->isEmpty()) {
    fwrite(STDERR, "ERROR: No admin users found (role=admin). Aborting to protect login access.\n");
    exit(1);
}

echo ($dryRun ? '[DRY RUN] ' : '') . "Database: {$database}\n";
echo "Admin accounts kept (" . $admins->count() . "):\n";
foreach ($admins as $admin) {
    echo "  - #{$admin->id} {$admin->name} <{$admin->email}>\n";
}

$nonAdminCount = User::query()
    ->whereRaw('LOWER(role) != ? OR role IS NULL', ['admin'])
    ->count();

$toTruncate = array_values(array_filter($tables, fn ($table) => ! in_array($table, $skipTruncate, true)));

echo "\nTables to truncate (" . count($toTruncate) . "):\n";
foreach ($toTruncate as $table) {
    echo "  - {$table}\n";
}

echo "\nNon-admin users to delete: {$nonAdminCount}\n";

if ($dryRun) {
    echo "\nDry run complete. No changes made.\n";
    exit(0);
}

DB::statement('SET FOREIGN_KEY_CHECKS=0');

try {
    foreach ($toTruncate as $table) {
        DB::table($table)->truncate();
        echo "Truncated {$table}\n";
    }

    User::query()
        ->whereRaw('LOWER(role) != ? OR role IS NULL', ['admin'])
        ->delete();

    echo "Deleted {$nonAdminCount} non-admin user(s)\n";

    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    echo "\nDone. Admin login preserved. All business data cleared.\n";
} catch (Throwable $e) {
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
