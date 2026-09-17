<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixPenjualanDetailIdAutoIncrement extends Migration
{
    /**
     * Run the migrations.
     * Fix: Field 'id_penjualan_detail' doesn't have a default value when inserting sales details (import).
     */
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $column = DB::selectOne("SHOW COLUMNS FROM `penjualan_detail` WHERE Field = 'id_penjualan_detail'");
        if ($column && strpos($column->Extra, 'auto_increment') === false) {
            DB::statement('ALTER TABLE `penjualan_detail` MODIFY `id_penjualan_detail` INT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    public function down()
    {
        // Not reversible safely
    }
}




