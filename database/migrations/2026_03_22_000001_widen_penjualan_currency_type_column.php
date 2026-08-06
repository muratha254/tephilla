<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow multiple currencies (comma-separated) e.g. KSH,USD
     */
    public function up()
    {
        if (! Schema::hasColumn('penjualan', 'currency_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE penjualan MODIFY currency_type VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE penjualan ALTER COLUMN currency_type TYPE VARCHAR(255)');
        }
        // sqlite: column type flexibility is usually sufficient; skip if not needed
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (! Schema::hasColumn('penjualan', 'currency_type')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE penjualan MODIFY currency_type VARCHAR(10) NULL');
        }
    }
};
