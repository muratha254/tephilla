<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = DB::getDatabaseName();
$tables = DB::select('SHOW TABLES');
$key = 'Tables_in_' . $database;

foreach ($tables as $table) {
    echo $table->{$key} . PHP_EOL;
}
