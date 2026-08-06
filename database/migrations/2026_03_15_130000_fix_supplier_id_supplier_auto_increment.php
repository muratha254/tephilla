<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixSupplierIdSupplierAutoIncrement extends Migration
{
    /**
     * Run the migrations.
     * Fix: Field 'id_supplier' doesn't have a default value when creating new suppliers during sales import.
     * MySQL allows only one AUTO_INCREMENT column and it must be defined as a key.
     */
    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $column = DB::selectOne("SHOW COLUMNS FROM `supplier` WHERE Field = 'id_supplier'");
        if (!$column || strpos($column->Extra, 'auto_increment') !== false) {
            return;
        }
        // If another column has AUTO_INCREMENT, remove it first (MySQL allows only one per table)
        $columns = DB::select("SHOW COLUMNS FROM `supplier`");
        foreach ($columns as $col) {
            if ($col->Field !== 'id_supplier' && strpos($col->Extra ?? '', 'auto_increment') !== false) {
                $type = $col->Type;
                $null = (strtolower($col->Null ?? '') === 'yes') ? 'NULL' : 'NOT NULL';
                DB::statement("ALTER TABLE `supplier` MODIFY `{$col->Field}` {$type} {$null}");
                break;
            }
        }
        // Ensure id_supplier is the primary key (required for AUTO_INCREMENT in MySQL)
        $indexes = DB::select("SHOW INDEX FROM `supplier` WHERE Key_name = 'PRIMARY'");
        $hasPkOnId = false;
        foreach ($indexes as $idx) {
            if ($idx->Column_name === 'id_supplier') {
                $hasPkOnId = true;
                break;
            }
        }
        if (!$hasPkOnId) {
            DB::statement('ALTER TABLE `supplier` ADD PRIMARY KEY (`id_supplier`)');
        }
        DB::statement('ALTER TABLE `supplier` MODIFY `id_supplier` INT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down()
    {
        // Not reversible safely
    }
}




