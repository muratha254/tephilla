<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixProdukIdProdukAutoIncrement extends Migration
{
    /**
     * Run the migrations.
     * Fix: Field 'id_produk' doesn't have a default value when creating new products during import.
     */
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $column = DB::selectOne("SHOW COLUMNS FROM `produk` WHERE Field = 'id_produk'");
        if ($column && strpos($column->Extra, 'auto_increment') === false) {
            DB::statement('ALTER TABLE `produk` MODIFY `id_produk` INT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    public function down()
    {
        // Not reversible safely
    }
}




