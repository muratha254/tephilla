<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixPenjualanIdPenjualanAutoIncrement extends Migration
{
    /**
     * Run the migrations.
     * Fix: Field 'id_penjualan' doesn't have a default value when inserting sales (import).
     */
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $column = DB::selectOne("SHOW COLUMNS FROM `penjualan` WHERE Field = 'id_penjualan'");
        if ($column && strpos($column->Extra, 'auto_increment') === false) {
            DB::statement('ALTER TABLE `penjualan` MODIFY `id_penjualan` INT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    public function down()
    {
        // Not reversible safely
    }
}




