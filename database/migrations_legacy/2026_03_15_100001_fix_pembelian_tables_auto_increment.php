<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixPembelianTablesAutoIncrement extends Migration
{
    /**
     * Run the migrations.
     * Fix: Ensure pembelian and pembelian_detail primary keys are AUTO_INCREMENT
     * so import-created cash purchases are stored and show on Cash Generated Sales.
     */
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach (['pembelian' => 'id_pembelian', 'pembelian_detail' => 'id_pembelian_detail'] as $table => $pk) {
            $column = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = ?", [$pk]);
            if ($column && strpos($column->Extra, 'auto_increment') === false) {
                $type = ($pk === 'id_pembelian_detail') ? 'INT' : 'INT';
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$pk}` {$type} UNSIGNED NOT NULL AUTO_INCREMENT");
            }
        }
    }

    public function down()
    {
        // Not reversible safely
    }
}




