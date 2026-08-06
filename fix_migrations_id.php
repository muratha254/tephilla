<?php
/**
 * One-time fix: add AUTO_INCREMENT to migrations.id so Laravel can insert new migration rows.
 * Run: php fix_migrations_id.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$driver = Illuminate\Support\Facades\DB::getDriverName();
if ($driver !== 'mysql') {
    echo "This fix is for MySQL only. Driver: {$driver}\n";
    exit(1);
}

$col = Illuminate\Support\Facades\DB::selectOne("SHOW COLUMNS FROM migrations WHERE Field = 'id'");
if (!$col) {
    echo "migrations.id column not found.\n";
    exit(1);
}

$extra = $col->Extra ?? '';
if (stripos($extra, 'auto_increment') !== false) {
    echo "migrations.id already has AUTO_INCREMENT.\n";
    exit(0);
}

Illuminate\Support\Facades\DB::statement('ALTER TABLE migrations MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
echo "Fixed: migrations.id now has AUTO_INCREMENT. You can run: php artisan migrate\n";
exit(0);


